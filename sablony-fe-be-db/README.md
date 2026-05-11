# Šablóna 3 — FE + BE + DB (3 pody, PVC pri DB)

Rozšírenie šablóny 2 o oddelenú databázu vo vlastnom pode. Upload zo FE prechádza
cez BE a končí ako BLOB v MariaDB, ktorá ho ukladá na perzistentné PVC.

## Architektúra

```
prehliadač
    │ http://localhost:30080
    ▼
exam-fe-svc (NodePort)           ← service-fe.yaml
    │
    ▼
FE pod (nginx)                   ← deployment-fe.yaml
    │  nginx config z fe-config
    │  /api/* proxy_pass
    ▼
exam-be-svc (ClusterIP :80)      ← service-be.yaml
    │
    ▼
BE pod (PHP/Apache, PDO)         ← deployment-be.yaml
    │  envFrom: be-config + db-secret
    │  ŽIADNY PVC — stateless
    │  PDO mysql:host=exam-db-svc
    ▼
exam-db-svc (ClusterIP :3306)    ← service-db.yaml
    │
    ▼
DB pod (MariaDB 11)              ← deployment-db.yaml
    │  envFrom: db-secret + env MYSQL_DATABASE/USER
    │  /docker-entrypoint-initdb.d/init.sql z db-init configmap
    │  PVC mount → /var/lib/mysql
    ▼
exam-pvc → exam-pv → /mnt/data/exam-meno
```

**Kľúčový rozdiel oproti šablóne 2:** PVC je pripojený na **DB pod**, nie na BE.
BE je stateless (môžeš ho škálovať), state žije v DB.

## Štruktúra

```
sablony-fe-be-db/
├── README.md
├── Dockerfile.be              ← PHP + Apache + pdo_mysql
├── Dockerfile.fe              ← nginx
├── src-be/
│   ├── index.php              ← JSON API (PDO INSERT BLOB, SELECT metadata)
│   ├── health.php             ← liveness (PHP žije)
│   └── ready.php              ← readiness (DB ping)
├── src-fe/
│   └── index.html             ← upload form + tabuľka súborov z DB
└── k8s/
    ├── namespace.yaml
    ├── configmap-be.yaml      ← env: APP_*, DB_HOST/NAME/USER
    ├── configmap-fe.yaml      ← nginx default.conf (proxy_pass)
    ├── configmap-db-init.yaml ← init.sql (CREATE TABLE uploads)
    ├── secret-db.yaml         ← MYSQL_ROOT_PASSWORD, MYSQL_PASSWORD
    ├── pv.yaml
    ├── pvc.yaml
    ├── deployment-fe.yaml     ← bez PVC
    ├── deployment-be.yaml     ← bez PVC, envFrom CM + Secret
    ├── deployment-db.yaml     ← S PVC + init.sql z ConfigMap
    ├── service-fe.yaml        ← NodePort 30080
    ├── service-be.yaml        ← ClusterIP
    └── service-db.yaml        ← ClusterIP 3306
```

## ConfigMap vs Secret — kedy čo

| Citlivosť | Typ | Kde |
|---|---|---|
| App nastavenia (názov, limity) | ConfigMap `be-config` | env do BE |
| DB host/name/user (nie heslo) | ConfigMap `be-config` | env do BE |
| nginx config (text súbor) | ConfigMap `fe-config` | volume mount do FE |
| Init SQL schéma | ConfigMap `db-init` | volume mount do DB |
| **DB heslá** | **Secret `db-secret`** | env do BE aj DB |

## Spustenie

```bash
# 1. Premenuj namespace
sed -i 's/exam-meno/exam-jano/g' k8s/*.yaml

# 2. Build oboch image-ov (DB image neťaháš sám — MariaDB stiahne k8s)
docker build -f Dockerfile.be -t exam-be:latest .
docker build -f Dockerfile.fe -t exam-fe:latest .
minikube image load exam-be:latest
minikube image load exam-fe:latest

# 3. Apply
kubectl apply -f k8s/

# 4. Sleduj kým sa všetko nahodí (DB trvá ~30s na prvý štart)
kubectl -n exam-jano get pods -w

# 5. Otvor
minikube service exam-fe-svc -n exam-jano
# alebo
kubectl -n exam-jano port-forward svc/exam-fe-svc 8080:80
# → http://localhost:8080
```

## Mapa zhôd (čo s čím musí sedieť)

```
deployment-fe   labels.app: exam-fe ────► service-fe   selector.app: exam-fe
deployment-be   labels.app: exam-be ────► service-be   selector.app: exam-be
deployment-db   labels.app: exam-db ────► service-db   selector.app: exam-db

nginx (fe-config) proxy_pass http://exam-be-svc:80/ ──► service-be   metadata.name: exam-be-svc

be-config  DB_HOST: exam-db-svc ──► service-db   metadata.name: exam-db-svc
be-config  DB_NAME: examdb ──────► deployment-db env MYSQL_DATABASE: examdb
be-config  DB_USER: appuser ─────► deployment-db env MYSQL_USER: appuser

db-secret  MYSQL_PASSWORD ───────► používa BE (PDO connect) AJ DB (heslo pre appuser)

deployment-db  claimName: exam-pvc ──► pvc   metadata.name: exam-pvc
pvc            storageClassName: manual ──► pv   storageClassName: manual
```

## Test toku dát

1. **Otvor FE** v prehliadači — uvidíš upload form a prázdnu tabuľku.
2. **Nahraj súbor** → FE pošle `POST /api/` → nginx proxy → BE → `INSERT … LONGBLOB` → DB → zápis na `/var/lib/mysql` → PVC.
3. **Refresh** → BE spraví `SELECT id, name, size, created_at` → JSON → FE tabuľka.
4. **Dôkaz perzistencie #1 — zabi BE pod:**
   ```bash
   kubectl -n exam-jano delete pod -l app=exam-be
   ```
   BE pod sa obnoví, súbory zostanú (sú v DB, nie v BE).
5. **Dôkaz perzistencie #2 — zabi DB pod:**
   ```bash
   kubectl -n exam-jano delete pod -l app=exam-db
   ```
   DB pod sa obnoví, primountuje to isté PVC, dáta tam stále sú.
6. **Pozri obsah DB priamo:**
   ```bash
   kubectl -n exam-jano exec -it deploy/exam-db -- \
     mariadb -uappuser -papppass123 examdb -e "SELECT id, name, size, created_at FROM uploads;"
   ```

## Prečo NIE StatefulSet pre tento prípad

Pre produkčnú DB by si chcel `StatefulSet` (stabilný hostname, `volumeClaimTemplates`,
ordered start/stop). Pre cvičenie s 1 replikou stačí `Deployment` s `strategy: Recreate` —
zabezpečí, že nový pod sa nespustí pokým starý nepustí PVC (ReadWriteOnce = 1 reader).

## Časté chyby

| Príznak | Príčina |
|---|---|
| BE `ready.php` vracia 503 | DB ešte štartuje, alebo heslo v secret-db ≠ to čo BE číta |
| `CrashLoopBackOff` na DB pri 2. apply | hesla v secret-db si zmenil ale PVC drží stare — buď zmaž PVC alebo nemeň heslo |
| `(loading...)` v FE navždy | nginx proxy_pass meno ≠ service-be meno (preklep) |
| Upload prejde, ale tabuľka prázdna | init.sql sa nespustil — PVC nebol prázdny pri prvom štarte |
| `client intended to send too large body` | chýba `client_max_body_size 20M` v nginx configu |
