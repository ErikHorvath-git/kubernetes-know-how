# Šablóna 3 — Frontend + Backend + Databáza v Kubernetes

## Popis aplikácie (funkcionalita)

Trojvrstvová aplikácia: **frontend (nginx)** → **backend (PHP+Apache, PDO)** →
**databáza (MariaDB)**. Súbory nahraté z FE prechádzajú cez BE a ukladajú sa ako
BLOB do MariaDB, ktorá ich perzistuje na PVC.

- **Frontend** — `index.html` s upload formulárom a tabuľkou súborov načítaných z BE.
- **Backend** — `index.php` JSON API:
  - `POST /api/` → `INSERT … LONGBLOB` cez PDO do MariaDB.
  - `GET /api/` → `SELECT id, name, size, created_at` z DB → JSON.
- **Databáza** — MariaDB 11, schéma sa inicializuje z `init.sql` (ConfigMap).
- **Bonus** — `health.php` (liveness, PHP žije) a `ready.php` (readiness, ping DB).

**Kľúčový rozdiel oproti šablóne 2:** PVC je pripojený na **DB pod**, nie na BE.
BE je stateless (môže sa škálovať), state žije v DB.

## Postup nasadenia

```bash
# 1. (voliteľne) premenuj namespace
sed -i 's/exam-meno/exam-jano/g' k8s/*.yaml

# 2. Build BE a FE image-ov (DB image MariaDB stiahne Kubernetes z Docker Hubu)
docker build -f Dockerfile.be -t exam-be:latest .
docker build -f Dockerfile.fe -t exam-fe:latest .
minikube image load exam-be:latest
minikube image load exam-fe:latest

# 3. Apply manifestov
kubectl apply -f k8s/

# 4. Sleduj kým sa všetko nahodí (DB trvá ~30s na prvý štart)
kubectl -n exam-jano get pods -w
```

Cieľový stav: tri pody **Running** (FE, BE, DB), všetky deploymenty **1/1 READY**,
pvc **Bound**.

## Ako aplikáciu testovať

```bash
# Možnosť 1 — Minikube tunel
minikube service exam-fe-svc -n exam-jano

# Možnosť 2 — port-forward
kubectl -n exam-jano port-forward svc/exam-fe-svc 30080:80
# → http://localhost:30080
```

Funkčný test:

1. Otvor `http://localhost:30080` — uvidíš upload form a (na začiatku) prázdnu tabuľku.
2. Nahraj súbor → `POST /api/` → BE urobí `INSERT … LONGBLOB` → DB zapíše na `/var/lib/mysql` → PVC.
3. Refresh → `GET /api/` → BE vráti `SELECT` z DB → tabuľka sa zobrazí.
4. **Dôkaz perzistencie #1 — zabi BE pod:**
   ```bash
   kubectl -n exam-jano delete pod -l app=exam-be
   ```
   BE sa obnoví, súbory zostávajú (sú v DB).
5. **Dôkaz perzistencie #2 — zabi DB pod:**
   ```bash
   kubectl -n exam-jano delete pod -l app=exam-db
   ```
   DB sa obnoví, primountuje to isté PVC, dáta tam stále sú.
6. **Priamo do DB:**
   ```bash
   kubectl -n exam-jano exec -it deploy/exam-db -- \
     mariadb -uappuser -papppass123 examdb -e "SELECT id, name, size, created_at FROM uploads;"
   ```

## Stručné vysvetlenie použitých komponentov

| Komponent | Súbor | Účel |
|---|---|---|
| **Namespace** | `namespace.yaml` | Izolovaný priestor `exam-jano` pre celú aplikáciu. |
| **ConfigMap `be-config`** | `configmap-be.yaml` | Env pre BE — `DB_HOST`, `DB_NAME`, `DB_USER`, app nastavenia. |
| **ConfigMap `fe-config`** | `configmap-fe.yaml` | nginx `default.conf` s `proxy_pass` na BE. |
| **ConfigMap `db-init`** | `configmap-db-init.yaml` | `init.sql` — `CREATE TABLE uploads` pri prvom štarte DB. |
| **Secret `db-secret`** | `secret-db.yaml` | DB heslá (`MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`). Použité v BE aj DB. |
| **PersistentVolume (PV)** | `pv.yaml` | Fyzický disk (hostPath `/mnt/data/...`) pre dáta DB. |
| **PersistentVolumeClaim (PVC)** | `pvc.yaml` | Žiadosť o miesto, mountuje sa do **DB podu** na `/var/lib/mysql`. |
| **Deployment FE** | `deployment-fe.yaml` | nginx pod, config z `fe-config`, bez PVC. |
| **Deployment BE** | `deployment-be.yaml` | PHP+Apache+pdo_mysql pod, env z `be-config` + `db-secret`, bez PVC (stateless). |
| **Deployment DB** | `deployment-db.yaml` | MariaDB 11 pod, **s PVC**, init.sql z `db-init`, `strategy: Recreate`. |
| **Service FE** | `service-fe.yaml` | **NodePort 30080** — verejný vstup (`http://localhost:30080`). |
| **Service BE** | `service-be.yaml` | **ClusterIP** — interná služba `exam-be-svc:80`. |
| **Service DB** | `service-db.yaml` | **ClusterIP** — interná služba `exam-db-svc:3306`. |
| **Dockerfile.be** | `Dockerfile.be` | PHP 8 + Apache + `pdo_mysql` extension. |
| **Dockerfile.fe** | `Dockerfile.fe` | nginx image s FE kódom. |

### ConfigMap vs Secret

| Citlivosť | Typ | Príklad |
|---|---|---|
| App nastavenia, DB host/name/user | **ConfigMap** | `be-config` |
| nginx config, init SQL | **ConfigMap** (mount ako súbor) | `fe-config`, `db-init` |
| **DB heslá** | **Secret** | `db-secret` |

### Tok požiadavky

```
prehliadač:30080
    → exam-fe-svc (NodePort)
    → FE pod (nginx)
    → /api/* proxy_pass
    → exam-be-svc (ClusterIP :80)
    → BE pod (PHP/PDO)
    → exam-db-svc (ClusterIP :3306)
    → DB pod (MariaDB)
    → PVC → /var/lib/mysql → /mnt/data/...
```
