# Šablóna 1 — Jednoduché zadanie (1 deployment, 1 ConfigMap)

PHP web-aplikácia v Kubernetes (Minikube). Pokrýva všetky body zo zadania (60 + bonusy).

> Detailný návod: **`navod-zadanie.pdf`** (12 sekcií).
> Stručné príkazy v poradí: **`postup.txt`**.

## Čo to je

Jeden Deployment (PHP+Apache), jedna ConfigMap, PV+PVC, NodePort Service, voliteľne InitContainer + probes. Jeden image `exam-app:latest`.

## Architektúra

```
prehliadač:30080 → exam-svc (NodePort) → exam-app pod (PHP/Apache) → PVC → /mnt/data/...
```

## Štruktúra

```
sablona-cvicne-zadanie/
├── README.md                       ← tento súbor
├── navod-zadanie.pdf               ← detailný návod (kompletné vysvetlenie)
├── postup.txt                      ← príkazy v poradí
├── cvicne-zadanie.pdf              ← zadanie úlohy
├── Dockerfile                      ← univerzálny PHP image
├── src/
│   ├── index-upload.php            ← Variant A: upload súborov
│   ├── index-logger.php            ← Variant B: API logger
│   ├── index-template.php          ← Variant C: InitContainer + šablóna
│   ├── index-cms.php               ← Variant D: Markdown CMS
│   ├── index-upload-init.php       ← A+C COMBO (najviac bodov)
│   ├── health.php                  ← liveness probe
│   └── ready.php                   ← readiness probe
└── k8s/
    ├── namespace.yaml
    ├── configmap.yaml              ← všetky varianty v jednej (zakomentované)
    ├── pv.yaml
    ├── pvc.yaml
    ├── deployment.yaml             ← univerzálny, InitContainer odkomentuješ
    ├── deployment-upload-init.yaml ← pre A+C COMBO (hotový)
    └── service.yaml                ← NodePort 30080
```

## Kroky v poradí

### 1. Pripravenie pracovného adresára

```bash
mkdir -p ~/exam/{src,k8s}
cd ~/exam
cp /cesta/k/sablona-cvicne-zadanie/Dockerfile ./
cp /cesta/k/sablona-cvicne-zadanie/k8s/*.yaml ./k8s/
```

### 2. Výber variantu

Vyber jeden a skopíruj ako `src/index.php`:

```bash
# A — upload súborov
cp /cesta/k/sablona-cvicne-zadanie/src/index-upload.php ./src/index.php

# B — API logger
cp /cesta/k/sablona-cvicne-zadanie/src/index-logger.php ./src/index.php

# C — InitContainer + šablóna  (POVINNÉ odkomentovať InitContainer v deployment.yaml)
cp /cesta/k/sablona-cvicne-zadanie/src/index-template.php ./src/index.php

# D — Markdown CMS
cp /cesta/k/sablona-cvicne-zadanie/src/index-cms.php ./src/index.php

# A+C COMBO — používa hotový deployment-upload-init.yaml (najviac bodov)
cp /cesta/k/sablona-cvicne-zadanie/src/index-upload-init.php ./src/index.php
cp /cesta/k/sablona-cvicne-zadanie/k8s/deployment-upload-init.yaml ./k8s/deployment.yaml
```

### 3. Premenovanie namespace na svoje meno

```bash
sed -i 's/exam-test1/exam-meno/g' k8s/*.yaml
# alebo manuálne uprav každý YAML
```

### 4. Bonus — probes

```bash
cp /cesta/k/sablona-cvicne-zadanie/src/health.php ./src/
cp /cesta/k/sablona-cvicne-zadanie/src/ready.php  ./src/
# v k8s/deployment.yaml odkomentuj sekcie readinessProbe a livenessProbe
```

### 5. Build image-u + load do Minikube

```bash
docker build -t exam-app:latest -f Dockerfile .
minikube image load exam-app:latest
```

### 6. Apply do Kubernetes

```bash
kubectl apply -f k8s/
kubectl -n exam-meno get all,pvc
```

Cieľový stav: pod **Running**, deployment **1/1**, pvc **Bound**.

### 7. Otvorenie aplikácie

```bash
minikube service exam-svc -n exam-meno
# alebo:
kubectl -n exam-meno port-forward svc/exam-svc 8080:80
# → http://localhost:8080
```

### 8. Dôkaz perzistencie (povinný pri obhajobe)

```bash
# 1. niečo nahraj cez UI
# 2. zabi pod
kubectl -n exam-meno delete pods --all

# 3. počkaj na nový pod
kubectl -n exam-meno get pods -w

# 4. refreshni — dáta sú tam
```

### 9. Upratanie

```bash
kubectl delete -f k8s/
kubectl delete pv exam-pv
docker rmi exam-app:latest
```

## Najčastejšie chyby

| Chyba | Fix |
|---|---|
| `ImagePullBackOff` | `minikube image load exam-app:latest` + `imagePullPolicy: IfNotPresent` |
| `pvc Pending` | `storageClassName` v PV a PVC musí byť rovnaký |
| `Permission denied` | InitContainer musí robiť `chown -R 33:33 /data` |
| `localhost:30080` neodpovedá | `minikube service` alebo `port-forward` |
| `port already allocated` | zmeň `nodePort` (30000-32767) |

Detaily v `navod-zadanie.pdf` (sekcia 9).
