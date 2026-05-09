# Kubernetes cvičná úloha — šablóny

Dve šablóny pre cvičnú úlohu *"Nasadenie vlastnej aplikácie do Kubernetes"*. Každá je samostatne použiteľná.

## Šablóny

### [sablona-cvicne-zadanie/](sablona-cvicne-zadanie/) — jednoduchá verzia

1 Deployment, 1 ConfigMap, 1 Service (NodePort). Pokrýva všetky body zo zadania.

- **PHP + Apache** v jednom kontajneri
- 4 varianty PHP kódu (upload / logger / template / CMS) + A+C combo
- Voliteľný InitContainer + probes
- Detailný návod v `navod-zadanie.pdf`

### [sablony-be-fe/](sablony-be-fe/) — rozšírená verzia

2 Deploymenty, 2 ConfigMapy, 2 Services. Pridáva architektúru BE + FE + nginx reverse-proxy.

- **BE** = PHP + Apache (JSON API), píše do PVC
- **FE** = nginx (statický web + reverse-proxy `/api/` na BE)
- 2 ConfigMaps demonštrujú **dva rôzne patterns** (envFrom vs mounted file)
- Detailný návod v `navod-be-fe.pdf`

## Ktorú vybrať

| Situácia | Šablóna |
|---|---|
| Štandardné zadanie (60 bodov + bonusy) | **sablona-cvicne-zadanie** |
| Zadanie vyžaduje 2+ Deploymenty alebo 2+ ConfigMapy | **sablony-be-fe** |
| Chcem ukázať pokročilé veci (multi-tier, DNS, reverse-proxy) | **sablony-be-fe** |

## Predpoklady

- Minikube
- `kubectl`
- Docker
- Linux/WSL/macOS

## Quick start

```bash
# Vyberiem si šablónu
cd sablona-cvicne-zadanie    # alebo: cd sablony-be-fe

# Ďalšie kroky → README.md v zložke
cat README.md
```
