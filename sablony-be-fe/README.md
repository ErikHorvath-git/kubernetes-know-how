# Šablóna 2 — BE + FE + 2 ConfigMapy + nginx

PHP backend + nginx frontend v Kubernetes. Ukazuje 2 deploymenty, 2 ConfigMaps (každá s iným patternom), reverse-proxy cez nginx.

> Detailný návod: **`navod-be-fe.pdf`** (9 sekcií).

## Architektúra

```
prehliadač
    │ http://localhost:30080
    ▼
exam-fe-svc (NodePort)        ← service-fe.yaml
    │
    ▼
FE pod (nginx)                ← deployment-fe.yaml
    │  nginx config z fe-config (mountnutý ako súbor)
    │  /api/* proxy_pass
    ▼
exam-be-svc (ClusterIP)       ← service-be.yaml
    │
    ▼
BE pod (PHP/Apache)           ← deployment-be.yaml
    │  env z be-config (envFrom)
    │  PVC mount → /var/www/html/data
    ▼
exam-pvc → exam-pv → /mnt/data/exam-meno
```

## Štruktúra

```
sablony-be-fe/
├── README.md
├── Dockerfile.be              ← BE image (PHP + Apache)
├── Dockerfile.fe              ← FE image (nginx)
├── src-be/
│   ├── index.php              ← JSON API (GET zoznam, POST upload)
│   ├── health.php             ← liveness probe
│   └── ready.php              ← readiness probe
├── src-fe/
│   └── index.html             ← statický web, fetch('/api/')
└── k8s/
    ├── namespace.yaml
    ├── configmap-be.yaml      ← Pattern 1: envFrom (env vars)
    ├── configmap-fe.yaml      ← Pattern 2: mounted file (nginx.conf)
    ├── pv.yaml
    ├── pvc.yaml
    ├── deployment-be.yaml     ← s PVC, InitContainer, probes
    ├── deployment-fe.yaml     ← bez PVC
    ├── service-be.yaml        ← ClusterIP (interná)
    └── service-fe.yaml        ← NodePort (verejná, 30080)
```

## Prečo dve ConfigMapy a dva spôsoby?

| ConfigMap | Spôsob konzumácie | Kde |
|---|---|---|
| `be-config` | `envFrom` → env premenné | BE container — PHP `getenv()` |
| `fe-config` | `volumes.configMap` → súbor | FE container — `/etc/nginx/conf.d/default.conf` |

Ukazuje obidva najpoužívanejšie patterns ako prepojiť ConfigMap s podom.

## Spustenie

```bash
# 1. Zmeň namespace na svoje meno
sed -i 's/exam-meno/exam-jano/g' k8s/*.yaml

# 2. Build oboch image-ov
docker build -f Dockerfile.be -t exam-be:latest .
docker build -f Dockerfile.fe -t exam-fe:latest .
minikube image load exam-be:latest
minikube image load exam-fe:latest

# 3. Apply
kubectl apply -f k8s/

# 4. Over
kubectl -n exam-jano get all,pvc

# 5. Otvor
minikube service exam-fe-svc -n exam-jano
# alebo
kubectl -n exam-jano port-forward svc/exam-fe-svc 8080:80
# → http://localhost:8080
```

## Mapa zhôd (čo s čím musí sedieť)

```
deployment-be   labels.app: exam-be ────► service-be   selector.app: exam-be
deployment-fe   labels.app: exam-fe ────► service-fe   selector.app: exam-fe

deployment-be   envFrom.name: be-config ──► configmap-be   metadata.name: be-config
deployment-fe   volumes.configMap.name: fe-config ──► configmap-fe   metadata.name: fe-config

deployment-be   claimName: exam-pvc ──► pvc   metadata.name: exam-pvc
pvc             storageClassName: manual ──► pv   storageClassName: manual

nginx config (fe-config)   proxy_pass http://exam-be-svc:80/ ──► service-be   metadata.name: exam-be-svc
```

## Test

1. Otvor v prehliadači — uvidíš FE s formulárom.
2. FE pri loade pošle `GET /api/` → nginx proxy → BE → JSON s zoznamom súborov.
3. Nahraj súbor cez form → `POST /api/` → BE upload → súbor v PVC.
4. **Dôkaz perzistencie:** `kubectl delete pods --all -n exam-jano`, počkaj na nové pody, refresh — súbory tam stále sú.
