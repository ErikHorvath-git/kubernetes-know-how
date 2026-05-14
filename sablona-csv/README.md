# Cvičné zadanie — Spracovanie vstupných údajov v Kubernetes

Jednoduchá PHP aplikácia, ktorá v prostredí Kubernetes:

- načíta CSV súbory z priečinka `input/`,
- pridá k nim hlavičku s timestampom a počtom dátových riadkov (`# PROCESSED: ...`),
- uloží transformovaný výsledok do priečinka `output/`,
- zobrazí welcome text z ConfigMapu a tabuľky CSV cez webové UI.

## Štruktúra projektu

```
.
├── README.md                           # tento súbor
├── rozbor.pdf                          # detailný rozbor každého YAML/URL/príkazu
├── src/
│   ├── index.php                       # hlavná stránka + spracovanie CSV
│   ├── health.php                      # livenessProbe endpoint -> "OK"
│   └── ready.php                       # readinessProbe endpoint -> "READY"
└── k8s/
    ├── namespace.yaml                  # Namespace exam-horvath
    ├── configmap.yaml                  # ConfigMap app-config (env + sample CSV)
    ├── pv.yaml                         # PersistentVolume (hostPath)
    ├── pvc.yaml                        # PersistentVolumeClaim
    ├── deployment-upload-init.yaml     # Deployment + InitContainer
    └── service.yaml                    # Service NodePort :30080
```

## Predpoklady

- Beží `minikube` (alebo iný Kubernetes klaster s podporou `hostPath`).
- Pre `hostPath` najprv vytvor priečinok na node-e:
  ```bash
  minikube ssh -- "sudo mkdir -p /mnt/data/exam-horvath && sudo chmod 777 /mnt/data/exam-horvath"
  ```

## Postup nasadenia

```bash
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/

# kontrola
kubectl get all,cm,pvc,pv -n exam-horvath
kubectl logs -n exam-horvath -l app=exam-app -c init-data
```

## Testovanie

```bash
# variant A - minikube otvorí URL v prehliadači
minikube service exam-svc -n exam-horvath

# variant B - port forward (stabilná URL)
kubectl port-forward -n exam-horvath svc/exam-svc 30080:80
# potom v prehliadači:
#   http://localhost:30080/         <- hlavné UI
#   http://localhost:30080/ready.php
#   http://localhost:30080/health.php
```

## Vysvetlenie komponentov

| Komponent     | Súbor                              | Účel                                                                |
|---------------|------------------------------------|---------------------------------------------------------------------|
| Namespace     | `namespace.yaml`                   | Logické oddelenie projektu (`exam-horvath`).                        |
| ConfigMap     | `configmap.yaml`                   | Konfigurácia (`APP_NAME`, `INPUT_DIR`, `OUTPUT_DIR`, `LOG_LEVEL`, `PROCESS_MODE`, `WELCOME_TEXT`) + sample CSV súbory. |
| PV + PVC      | `pv.yaml` + `pvc.yaml`             | Zdieľané úložisko (`hostPath` 1Gi) medzi InitContainerom a hlavným kontajnerom. |
| InitContainer | `deployment-upload-init.yaml`      | Pripraví `input/` + `output/`, nakopíruje `welcome.txt` a sample CSV (idempotentne). |
| Deployment    | `deployment-upload-init.yaml`      | Spustí hlavný kontajner `php:8.2-apache` + namountuje PVC do `/var/www/html/data`. |
| Service       | `service.yaml`                     | Sprístupní aplikáciu cez NodePort `30080`.                          |

### Bonus

- **`readinessProbe`** (`/ready.php`) — overuje, či je PVC writable.
- **`livenessProbe`** (`/health.php`) — overuje, či Apache odpovedá.

## Cleanup

```bash
kubectl delete namespace exam-horvath
kubectl delete pv exam-pv
```

## Detailný rozbor

Pre **rozbor každého YAML súboru pole po poli**, vrátane URL a všetkých `kubectl` príkazov, pozri `rozbor.pdf` v koreni projektu.
