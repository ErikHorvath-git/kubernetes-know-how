# Šablóna 2 — Backend + Frontend v Kubernetes

## Popis aplikácie (funkcionalita)

Dvoj-podová aplikácia: **frontend (nginx)** servuje statickú stránku a robí reverse-proxy
na **backend (PHP + Apache)**, ktorý poskytuje JSON API a ukladá nahraté súbory na PVC.

- **Frontend** — `index.html` so statickým UI, fetch na `/api/...`.
- **Backend** — `index.php` JSON API:
  - `GET /api/` → zoznam nahratých súborov z `/var/www/html/data`.
  - `POST /api/` → upload nového súboru (multipart/form-data).
- **Bonus** — `health.php` (liveness) a `ready.php` (readiness).

Aplikácia ukazuje **dva najpoužívanejšie spôsoby konzumácie ConfigMap**:
- `be-config` cez `envFrom` (premenné prostredia v BE pode),
- `fe-config` cez `volumes.configMap` (nginx config ako súbor v FE pode).

## Postup nasadenia

```bash
# 1. (voliteľne) premenuj namespace
sed -i 's/exam-meno/exam-jano/g' k8s/*.yaml

# 2. Build oboch image-ov
docker build -f Dockerfile.be -t exam-be:latest .
docker build -f Dockerfile.fe -t exam-fe:latest .
minikube image load exam-be:latest
minikube image load exam-fe:latest

# 3. Apply manifestov
kubectl apply -f k8s/

# 4. Over
kubectl -n exam-jano get all,pvc
```

Cieľový stav: oba pody **Running**, oba deploymenty **1/1 READY**, pvc **Bound**.

## Ako aplikáciu testovať

```bash
# Možnosť 1 — Minikube tunel
minikube service exam-fe-svc -n exam-jano

# Možnosť 2 — port-forward
kubectl -n exam-jano port-forward svc/exam-fe-svc 30080:80
# → http://localhost:30080
```

Funkčný test:

1. Otvor `http://localhost:30080` — uvidíš FE s upload formulárom.
2. Pri načítaní FE zavolá `GET /api/` (cez nginx proxy na BE) → zoznam súborov v JSON-e.
3. Nahraj súbor cez formulár → `POST /api/` → BE ho zapíše na PVC.
4. **Dôkaz perzistencie:** `kubectl -n exam-jano delete pods --all`, počkaj na nové pody,
   refresh — súbory sú stále zobrazené.

## Stručné vysvetlenie použitých komponentov

| Komponent | Súbor | Účel |
|---|---|---|
| **Namespace** | `namespace.yaml` | Izolovaný priestor `exam-jano` pre všetky objekty. |
| **ConfigMap `be-config`** | `configmap-be.yaml` | Env premenné pre BE (názov, limity). Použité cez `envFrom`. |
| **ConfigMap `fe-config`** | `configmap-fe.yaml` | nginx config (`default.conf`) s `proxy_pass` na BE. Mountuje sa ako súbor. |
| **PersistentVolume (PV)** | `pv.yaml` | Fyzický disk (hostPath `/mnt/data/...`) pre nahraté súbory. |
| **PersistentVolumeClaim (PVC)** | `pvc.yaml` | Žiadosť o miesto z PV, mountuje sa do BE podu na `/var/www/html/data`. |
| **Deployment BE** | `deployment-be.yaml` | PHP+Apache pod, PVC mount, env z `be-config`, probes. |
| **Deployment FE** | `deployment-fe.yaml` | nginx pod, config z `fe-config`, bez PVC (stateless). |
| **Service BE** | `service-be.yaml` | **ClusterIP** — interná služba, dostupná len v klastri ako `exam-be-svc:80`. |
| **Service FE** | `service-fe.yaml` | **NodePort 30080** — verejný vstup zvonku klastra (`http://localhost:30080`). |
| **Dockerfile.be** | `Dockerfile.be` | PHP 8 + Apache image s BE kódom. |
| **Dockerfile.fe** | `Dockerfile.fe` | nginx image so statickými FE súbormi. |

### Tok požiadavky

```
prehliadač:30080
    → exam-fe-svc (NodePort)
    → FE pod (nginx)
    → /api/* proxy_pass
    → exam-be-svc (ClusterIP)
    → BE pod (PHP/Apache)
    → PVC → /mnt/data/...
```
