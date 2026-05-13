# Šablóna 1 — PHP web-aplikácia v Kubernetes

## Popis aplikácie (funkcionalita)

Jednoduchá PHP web-aplikácia bežiaca v PHP + Apache kontajneri v Minikube. Aplikácia
ukladá dáta používateľa na perzistentný disk (PVC), takže prežijú reštart podu.

K dispozícii sú štyri varianty `index.php` (vyber si jeden):

- **Variant A — `index-upload.php`** — upload súborov cez formulár, výpis nahratých súborov.
- **Variant B — `index-logger.php`** — REST API logger, GET/POST endpointy, zápis do logu.
- **Variant C — `index-template.php`** — InitContainer pripraví šablónu, PHP ju renderuje.
- **Variant D — `index-cms.php`** — Markdown CMS, súbory `.md` z PVC sa renderujú ako HTML.
- **A+C COMBO — `index-upload-init.php`** — upload + InitContainer (najviac bodov).

Bonus: liveness probe (`health.php`) a readiness probe (`ready.php`).

## Postup nasadenia

```bash
# 1. Build image-u
docker build -t exam-app:latest -f Dockerfile .
minikube image load exam-app:latest

# 2. (voliteľne) premenuj namespace na svoje meno
sed -i 's/exam-test1/exam-meno/g' k8s/*.yaml

# 3. Apply všetkých manifestov
kubectl apply -f k8s/

# 4. Over stav
kubectl -n exam-meno get all,pvc
```

Cieľový stav: pod **Running**, deployment **1/1 READY**, pvc **Bound**.

## Ako aplikáciu testovať

```bash
# Možnosť 1 — priamo cez NodePort (Minikube otvorí tunel)
minikube service exam-svc -n exam-meno

# Možnosť 2 — port-forward
kubectl -n exam-meno port-forward svc/exam-svc 30080:80
# → http://localhost:30080
```

Funkčný test:

1. Otvor `http://localhost:30080` v prehliadači.
2. Použi UI podľa zvoleného variantu (nahraj súbor / zavolaj API / pozri šablónu).
3. **Dôkaz perzistencie:** `kubectl -n exam-meno delete pods --all`, počkaj na nový pod,
   refresh — dáta sú stále tam (žijú v PVC, nie v pode).

## Stručné vysvetlenie použitých komponentov

| Komponent | Súbor | Účel |
|---|---|---|
| **Namespace** | `namespace.yaml` | Izolovaný menný priestor `exam-meno` pre všetky objekty úlohy. |
| **ConfigMap** | `configmap.yaml` | Konfiguračné hodnoty pre PHP (názov app, limity, šablóna). Mountuje sa do podu ako env premenné alebo súbor. |
| **PersistentVolume (PV)** | `pv.yaml` | Fyzický disk v klastri (hostPath `/mnt/data/...`). Existuje nezávisle od podu. |
| **PersistentVolumeClaim (PVC)** | `pvc.yaml` | Žiadosť o miesto z PV. Deployment ju mountuje do `/var/www/html/data`. |
| **Deployment** | `deployment.yaml` | Spravuje pod s PHP+Apache kontajnerom. Voliteľne obsahuje InitContainer a probes. |
| **Service (NodePort)** | `service.yaml` | Vystavuje pod von z klastra na porte **30080** (`http://localhost:30080`). |
| **Dockerfile** | `Dockerfile` | Postavený PHP 8 + Apache image s aplikačným kódom. |

### Tok požiadavky

```
prehliadač:30080 → exam-svc (NodePort) → exam-app pod (PHP/Apache) → PVC → /mnt/data/...
```
