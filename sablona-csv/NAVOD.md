# Návod na spustenie a overenie

Kompletný postup: nasadenie → prístup cez NodePort (bez obchádzania) → dôkaz perzistencie.

---

## 1. Predpoklady

```bash
# beží minikube
minikube status
```

> Priečinok na node-e netreba pripravovať — PV má `type: DirectoryOrCreate`,
> takže kubelet ho vytvorí sám a InitContainer mu nastaví vlastníctvo (`chown 33:33`).

## 2. Nasadenie

```bash
# najprv namespace, potom všetko ostatné
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/
```

## 3. Kontrola, že všetko beží

```bash
# všetky objekty v namespace
kubectl get all,cm,pvc -n exam-horvath

# PV je cluster-wide (bez -n), musí byť STATUS=Bound
kubectl get pv exam-pv

# pod musí byť Running 1/1
kubectl get pods -n exam-horvath -w

# čo spravil InitContainer (vytvoril priečinky, nakopíroval sample CSV)
kubectl logs -n exam-horvath -l app=exam-app -c init-data
```

## 4. Prístup k aplikácii cez NodePort

> **Pozor:** `kubectl port-forward` NodePort **obchádza** — tuneluje cez API server
> priamo do podu, takže Service/NodePort vôbec netestuje. Nižšie sú varianty,
> ktoré idú **skutočne cez NodePort 30080**.

```bash
# variant A — priamo na IP node-u (najčistejší dôkaz, že NodePort funguje)
minikube ip
curl http://$(minikube ip):30080/
# alebo otvor http://<minikube-ip>:30080/ v prehliadači

# variant B — minikube vytvorí tunel NA NodePort (neobchádza ho)
minikube service exam-svc -n exam-horvath --url
# vypíše napr. http://127.0.0.1:42173 -> tunel končí na node:30080
```

### Variant C — socat: stabilná localhost URL, prevádzka ide cez NodePort

S docker driverom beží klaster v kontajneri — NodePort 30080 je otvorený na
minikube IP (napr. `192.168.49.2:30080`), NIE na hostiteľskom localhoste.
socat spraví most: `localhost:<host-port>` → `minikube-ip:30080`.

```bash
# inštalácia (raz)
sudo dnf install -y socat        # Fedora / RHEL
sudo apt install -y socat        # Debian / Ubuntu

# spustenie tunela na pozadí
socat TCP-LISTEN:3000,fork,reuseaddr TCP:$(minikube ip):30080 &
#    TCP-LISTEN:3000           počúva na localhost:3000 (port ľubovoľný)
#    fork                      child proces pre každé spojenie
#    reuseaddr                 netreba čakať na uvoľnenie portu
#    TCP:<minikube-ip>:30080   cieľ MUSÍ sedieť s nodePort v service.yaml

# test
curl -I http://localhost:3000
# -> HTTP/1.1 200 OK
# v prehliadači: http://localhost:3000
```

Tok dát (NodePort sa neobchádza):

```
browser http://localhost:3000
   -> socat (host :3000)
   -> $(minikube ip):30080      <- reálny NodePort
   -> Service exam-svc
   -> Pod exam-app
```

Správa bežiaceho socat-u:

```bash
jobs -l                   # vidieť background joby
ss -tlnp | grep :3000     # overiť, že počúva
kill %1                   # zabiť po skončení (alebo kill <PID>)
```

Časté chyby:

| Chyba                  | Príčina / riešenie                                      |
|------------------------|---------------------------------------------------------|
| Address already in use | na porte 3000 už niečo beží → iný port alebo kill       |
| Connection refused     | minikube je stopnutý → `minikube start`                 |
| visí bez odpovede      | pod ešte štartuje → `kubectl get pods -n exam-horvath`  |

## 5. Dôkaz perzistencie

Princíp: aplikácia pri prvom spracovaní CSV zapíše do `output/` hlavičku
`# PROCESSED: <timestamp> rows=N`. Ak dáta prežijú reštart podu, po reštarte
uvidíme **ten istý timestamp** so statusom „už spracované".

### 5.1 Pripojenie do podu a pohľad do priečinkov

```bash
# meno podu do premennej
POD=$(kubectl -n exam-horvath get pod -l app=exam-app -o jsonpath='{.items[0].metadata.name}')

# interaktívny shell v pode
kubectl -n exam-horvath exec -it $POD -- bash

# vnútri podu:
ls -la /var/www/html/data/input/
ls -la /var/www/html/data/output/
head -1 /var/www/html/data/output/sample1.csv     # <- riadok "# PROCESSED: ..."
exit
```

To isté bez interaktívneho shellu:

```bash
kubectl -n exam-horvath exec $POD -- ls -la /var/www/html/data/input /var/www/html/data/output
kubectl -n exam-horvath exec $POD -- head -1 /var/www/html/data/output/sample1.csv
```

### 5.2 Vlastný testovací súbor

```bash
# vytvor nový CSV priamo v input/ (sme bez upload UI, ide to cez exec)
kubectl -n exam-horvath exec $POD -- sh -c \
  'printf "city,temp\nNitra,21\nTrnava,19\n" > /var/www/html/data/input/test.csv'

# refreshni UI -> test.csv má status "spracované teraz · <timestamp>"
# zapamätaj/odfoť si timestamp:
kubectl -n exam-horvath exec $POD -- head -1 /var/www/html/data/output/test.csv
```

### 5.3 Zabitie podu a overenie

```bash
# zabi pod (Deployment automaticky vytvorí nový)
kubectl -n exam-horvath delete pods --all

# počkaj, kým je nový pod Running 1/1
kubectl -n exam-horvath get pods -w
```

Po nábehu nového podu:

```bash
# POZOR: nový pod = nové meno, treba znova
POD=$(kubectl -n exam-horvath get pod -l app=exam-app -o jsonpath='{.items[0].metadata.name}')

# 1) súbory stále existujú
kubectl -n exam-horvath exec $POD -- ls -la /var/www/html/data/input /var/www/html/data/output

# 2) timestamp je PÔVODNÝ -> nový pod nič nespracoval znova,
#    našiel hotový výsledok na PVC
kubectl -n exam-horvath exec $POD -- head -1 /var/www/html/data/output/test.csv

# 3) initContainer preskočil kopírovanie sample CSV, lebo input/ nebol prázdny
kubectl -n exam-horvath logs $POD -c init-data
```

V UI (`http://<minikube-ip>:30080/`) má `test.csv` status
**„už spracované · # PROCESSED: <pôvodný timestamp> ..."** — to je dôkaz,
že dáta prežili reštart podu.

### 5.4 Pohľad na dáta priamo na node (nezávisle od podu)

```bash
# dáta ležia na hostPath aj keď pod nebeží
minikube ssh -- ls -la /mnt/data/exam-horvath/input /mnt/data/exam-horvath/output
minikube ssh -- head -1 /mnt/data/exam-horvath/output/test.csv
```

## 6. Cleanup

```bash
kubectl delete namespace exam-horvath
kubectl delete pv exam-pv     # PV je cluster-wide, namespace ho nezmaže

# voliteľne aj dáta na node
minikube ssh -- "sudo rm -rf /mnt/data/exam-horvath"
```
