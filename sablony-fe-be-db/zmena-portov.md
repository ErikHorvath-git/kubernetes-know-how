# Návod: zmena interných portov FE→70, BE→90 (+ DB)

Príklad nižšie zmení FE (nginx) z `80` na `70` a BE (php:apache) z `80` na `90`. DB (MariaDB) port sa zvyčajne **nemení** — viď koniec.

Sú **2 vrstvy** portov ktoré treba zladiť:
1. **Port v aplikácii/kontajneri** — kde fakticky proces počúva.
2. **k8s manifesty** — `containerPort`, `targetPort`, `proxy_pass` (musia sa zhodovať s číslom 1).

## FE (nginx) → 70

| Súbor | Riadok | Pôvodne | Zmena |
|---|---|---|---|
| `Dockerfile.fe` | 5 | `EXPOSE 80` | `EXPOSE 70` *(len informatívne)* |
| `k8s/configmap-fe.yaml` | 10 | `listen 80;` | `listen 70;` ← **toto fakticky prepne nginx** |
| `k8s/deployment-fe.yaml` | 30 | `- containerPort: 80` | `- containerPort: 70` |
| `k8s/service-fe.yaml` | 12 | `targetPort: 80` | `targetPort: 70` |

`service-fe.yaml` má aj `port: 80` (r. 11) a `nodePort: 30080` (r. 13) — **toto je externé** (vstup do klastra). Nemusíš meniť, pokiaľ chceš ponechať `localhost:30080`.

## BE (apache+php) → 90

| Súbor | Riadok | Pôvodne | Zmena |
|---|---|---|---|
| `Dockerfile.be` | 10 | `EXPOSE 80` | `EXPOSE 90` *(len informatívne!)* |
| `k8s/deployment-be.yaml` | 22 | `- containerPort: 80` | `- containerPort: 90` |
| `k8s/deployment-be.yaml` | 35, 41 | probes `port: 80` | `port: 90` (readiness + liveness) |
| `k8s/service-be.yaml` | 12 | `targetPort: 80` | `targetPort: 90` |
| `k8s/configmap-fe.yaml` | 26 | `proxy_pass http://exam-be-svc:80/;` | iba ak meníš aj service `port` BE — viď nižšie |

`service-be.yaml` riadok 11 `port: 80` je port **Service-y** (`exam-be-svc:80`). Máš dve možnosti:

- **A) Nechať Service na 80** — `configmap-fe.yaml:26` ostáva `exam-be-svc:80`. Service preloží 80 → targetPort 90 do podu. Najmenej zmien.
- **B) Zmeniť aj Service na 90** — `service-be.yaml:11` `port: 80` → `90`, a `configmap-fe.yaml:26` `exam-be-svc:80` → `exam-be-svc:90`.

## ⚠ Dôležité: `EXPOSE` a apache nestačí

`EXPOSE` v Dockerfile je **iba dokumentačné** — nezmení na čom apache/nginx fakticky počúva.

- **FE nginx**: lieta cez `configmap-fe.yaml` (`listen 70;`), takže OK ✓
- **BE apache**: štandardný `php:8.2-apache` image počúva napevno na **80**. Samotná zmena `EXPOSE 90` + `containerPort: 90` spôsobí, že pod sa neviem dohovoriť — kubelet bude probovať port 90, ale apache stále počúva 80.

Aby BE fakticky počúval na 90, pridaj do `Dockerfile.be` pred `EXPOSE`:

```dockerfile
RUN sed -i 's/Listen 80/Listen 90/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:90>/' /etc/apache2/sites-available/000-default.conf
```

## DB (MariaDB) — port 3306

DB port **NEodporúčam meniť**. MariaDB image (`mariadb:*`) má 3306 napevno zadrôtované do konfigurácie + klienti (PDO, `mariadb` CLI) ho očakávajú ako default. Ak by si trval na zmene, musíš upraviť:

| Súbor | Riadok | Poznámka |
|---|---|---|
| `k8s/deployment-db.yaml` | 30 | `- containerPort: 3306` + treba do podu pridať `MYSQL_TCP_PORT` env alebo custom `my.cnf` |
| `k8s/deployment-db.yaml` | 54 | probe `port: 3306` |
| `k8s/service-db.yaml` | 11, 12 | `port` a `targetPort` |
| `k8s/configmap-be.yaml` | – | pridať `DB_PORT: "<novy>"` a v BE PHP kóde čítať `getenv('DB_PORT')` v PDO DSN (teraz tam port nie je) |

**Reálne**: nechaj 3306. Service `exam-db-svc:3306` ostáva, BE sa pripája cez DNS meno + default port.

## Rebuild + redeploy

1. `docker build -f Dockerfile.fe -t <obraz-fe> .` a `docker build -f Dockerfile.be -t <obraz-be> .` + push
2. `kubectl apply -f k8s/` (alebo `kubectl rollout restart deployment/<meno>` ak používaš `:latest`)

## Cesta paketu (kontrola)

```
localhost:30080  ──►  service-fe (nodePort)
                 ──►  pod FE :70 (nginx listen 70)
                 ──►  /api/ → proxy_pass exam-be-svc:80 (Service port BE)
                 ──►  pod BE :90 (apache Listen 90)
                 ──►  PDO → exam-db-svc:3306
                 ──►  pod DB :3306 (mariadb)               ✓
```
