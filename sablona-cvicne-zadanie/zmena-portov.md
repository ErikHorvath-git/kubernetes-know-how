# Návod: zmena interného portu appky

Táto šablóna má **jednu appku** (php:8.2-apache) — niet FE/BE rozdelenia. Príklad nižšie zmení vnútorný port z `80` na `70`.

Sú **2 vrstvy** portov ktoré treba zladiť:
1. **Port v aplikácii/kontajneri** — kde fakticky proces (apache) počúva.
2. **k8s manifesty** — `containerPort`, `targetPort` (musia sa zhodovať s číslom 1).

## Súbory na úpravu

| Súbor | Riadok | Pôvodne | Zmena |
|---|---|---|---|
| `Dockerfile` | 8 | `EXPOSE 80` | `EXPOSE 70` *(len informatívne!)* |
| `k8s/deployment.yaml` | 63 | `- containerPort: 80` | `- containerPort: 70` |
| `k8s/deployment.yaml` | 78, 84 | probe `port: 80` | `port: 70` *(ak odkomentuješ liveness/readiness)* |
| `k8s/deployment-upload-init.yaml` | 49 | `- containerPort: 80` | `- containerPort: 70` *(ak používaš upload variant)* |
| `k8s/service.yaml` | 27 | `targetPort: 80` | `targetPort: 70` |

`service.yaml` riadok 26 `port: 80` je port **Service-y** v klastri (`exam-svc:80`). To nie je nutné meniť — `port` a `targetPort` môžu byť rôzne (Service prepíše). Ak chceš mať konzistentné, zmeň aj `port: 80` → `70`.

`nodePort: 30080` (riadok 28) je **externý** vstup z `<NodeIP>:30080` — netreba meniť.

## ⚠ Dôležité: `EXPOSE` a apache nestačí

`EXPOSE` v Dockerfile je **iba dokumentačné** — nezmení na čom apache fakticky počúva.

Štandardný `php:8.2-apache` image počúva napevno na **80**. Samotná zmena `EXPOSE 70` + `containerPort: 70` spôsobí, že pod sa neviem dohovoriť — kubelet bude probovať port 70, ale apache stále počúva 80.

Aby apache fakticky počúval na 70, pridaj do `Dockerfile` pred `EXPOSE`:

```dockerfile
RUN sed -i 's/Listen 80/Listen 70/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:70>/' /etc/apache2/sites-available/000-default.conf
```

## Rebuild + redeploy

1. `docker build -t exam-app:latest .`
2. `kubectl apply -f k8s/` (alebo `kubectl rollout restart deployment/exam-app -n exam-test1` ak meníš iba YAML)

## Cesta paketu (kontrola)

```
localhost:3001  ──►  kubectl port-forward svc/exam-svc 3001:80  (port Service)
                 ──►  svc:80 → pod:70 (targetPort = containerPort)
                 ──►  apache počúva na 70  ✓
```
