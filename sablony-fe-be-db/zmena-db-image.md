# Návod: výmena DB image (MariaDB → MongoDB)

⚠ **Toto NIE JE iba zmena `image:` riadku.** MariaDB a Mongo používajú úplne iný protokol (SQL vs document), takže treba meniť: DB deployment, init script, BE Dockerfile (mongo PHP rozšírenie), BE PHP kód (PDO → `MongoDB\Client`), env premenné, probe príkazy.

Tu je úplný checklist. Príklady predpokladajú `mongo:7` image.

## 1. `k8s/deployment-db.yaml`

| Čo | Pôvodne (MariaDB) | Po (Mongo) |
|---|---|---|
| `image:` (r. 27) | `mariadb:11` | `mongo:7` |
| `containerPort` (r. 30) | `3306` | `27017` |
| env premenné (r. 32–36) | `MYSQL_DATABASE`, `MYSQL_USER` | `MONGO_INITDB_ROOT_USERNAME` (z secret), `MONGO_INITDB_ROOT_PASSWORD` (z secret), `MONGO_INITDB_DATABASE: examdb` |
| `envFrom secret-db` (r. 38–39) | obsahuje `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD` | premenovať na `MONGO_INITDB_ROOT_USERNAME`, `MONGO_INITDB_ROOT_PASSWORD` |
| `volumeMounts mountPath` (r. 44) | `/var/lib/mysql` | `/data/db` ← **mongo ukladá sem** |
| `mountPath` init (r. 46) | `/docker-entrypoint-initdb.d` | `/docker-entrypoint-initdb.d` ← **rovnaké**, ale mongo načítava `.js`/`.sh`, nie `.sql` |
| `readinessProbe.exec` (r. 49) | `mariadb -u$MYSQL_USER … 'SELECT 1' examdb` | `mongosh --quiet --eval "db.adminCommand('ping').ok" \| grep 1` |
| `livenessProbe.httpGet.port` (r. 54) | `3306` (pozn. má httpGet — to v skutočnosti pre DB nedáva zmysel, mali by byť `tcpSocket`) | `27017` *(lepšie zmeniť na `tcpSocket: { port: 27017 }`)* |

## 2. `k8s/service-db.yaml`

| Riadok | Pôvodne | Po |
|---|---|---|
| 11 | `port: 3306` | `port: 27017` |
| 12 | `targetPort: 3306` | `targetPort: 27017` |

DNS meno `exam-db-svc` zostáva — BE sa pripája na `exam-db-svc:27017`.

## 3. `k8s/secret-db.yaml`

Premenovať kľúče:

```yaml
stringData:
  MONGO_INITDB_ROOT_USERNAME: "root"
  MONGO_INITDB_ROOT_PASSWORD: "rootpass123"
  # heslo pre appuser ak budeš robiť non-root usera v init scripte:
  MONGO_APP_PASSWORD: "apppass123"
```

## 4. `k8s/configmap-be.yaml`

| Pôvodne | Po |
|---|---|
| `DB_HOST: "exam-db-svc"` | ostáva |
| `DB_NAME: "examdb"` | ostáva (mongo database) |
| `DB_USER: "appuser"` | ostáva, ale musíš ho **vytvoriť v init scripte** (mongo defaultne nevytvára usera ako MariaDB) |
| — | pridať `DB_PORT: "27017"` |

## 5. `k8s/configmap-db-init.yaml` — prepísať SQL na JS

MariaDB načítava `*.sql`. Mongo načítava `*.js` z toho istého adresára (`/docker-entrypoint-initdb.d/`).

```yaml
data:
  init.js: |
    db = db.getSiblingDB('examdb');
    db.createCollection('uploads');
    db.uploads.createIndex({ created_at: 1 });
    db.createUser({
      user: 'appuser',
      pwd: process.env.MONGO_APP_PASSWORD,
      roles: [{ role: 'readWrite', db: 'examdb' }]
    });
```

Pozn.: `process.env` v mongo init JS funguje len ak premennú prepustíš do podu — pridaj `MONGO_APP_PASSWORD` do `envFrom: secretRef: db-secret`.

## 6. `Dockerfile.be` — vymeniť PHP rozšírenie

```dockerfile
FROM php:8.2-apache

# pôvodne: RUN docker-php-ext-install pdo pdo_mysql

# nové — PECL mongodb rozšírenie:
RUN apt-get update && apt-get install -y libssl-dev pkg-config \
 && pecl install mongodb \
 && docker-php-ext-enable mongodb \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# composer balík pre vyšší level API (MongoDB\Client) je vhodný, ale ide aj cez raw extension
# COPY composer.json . && composer install   # ak používaš composer

COPY src-be/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
```

Bez composer balíka `mongodb/mongodb` máš len low-level `MongoDB\Driver\Manager`. Pre `MongoDB\Client` (pohodlnejšie) treba composer.

## 7. `src-be/index.php` — prepísať PDO na Mongo

```php
// PÔVODNE (PDO/MariaDB):
// $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
// $stmt = $pdo->prepare("INSERT INTO uploads (name, size, content) VALUES (?, ?, ?)");

// NOVÉ (Mongo low-level driver, bez composer-u):
$uri = "mongodb://{$dbUser}:{$dbPass}@{$dbHost}:27017/{$dbName}";
$manager = new MongoDB\Driver\Manager($uri);

// INSERT
$bulk = new MongoDB\Driver\BulkWrite;
$bulk->insert([
    'name'       => $name,
    'size'       => $size,
    'content'    => new MongoDB\BSON\Binary($content, MongoDB\BSON\Binary::TYPE_GENERIC),
    'created_at' => new MongoDB\BSON\UTCDateTime(),
]);
$manager->executeBulkWrite("{$dbName}.uploads", $bulk);

// SELECT
$query = new MongoDB\Driver\Query([], ['sort' => ['created_at' => -1]]);
$cursor = $manager->executeQuery("{$dbName}.uploads", $query);
foreach ($cursor as $doc) { /* ... */ }
```

**Upozornenia:**
- Hardcodované `MYSQL_PASSWORD` v `getenv('MYSQL_PASSWORD')` treba premenovať na `MONGO_APP_PASSWORD` (alebo čokoľvek čo dáš do secret-u).
- `ready.php` ak pinguje DB cez `SELECT 1` → zmeniť na `$manager->executeCommand($dbName, new MongoDB\Driver\Command(['ping' => 1]))`.

## 8. PVC zostáva — ale **vyčistiť obsah**

`exam-pvc` je rovnaký, ale staré MariaDB dáta (`/var/lib/mysql`) v ňom prekážajú. Pred prvým štartom mongo:

```sh
kubectl -n exam-meno delete pvc exam-pvc
kubectl apply -f k8s/pvc.yaml -f k8s/pv.yaml
```

Inak mongo nájde neznámy obsah v `/data/db` a buď ho ignoruje (init sa nespustí), alebo spadne.

## Sumár čo treba urobiť

1. `secret-db.yaml` — premenovať kľúče.
2. `configmap-db-init.yaml` — prepísať SQL → JS, premenovať `init.sql` → `init.js`.
3. `deployment-db.yaml` — image, port, env, mountPath, probes.
4. `service-db.yaml` — port 3306 → 27017.
5. `configmap-be.yaml` — pridať `DB_PORT`.
6. `Dockerfile.be` — `pdo_mysql` → `pecl install mongodb`.
7. `src-be/index.php` + `ready.php` — prepísať PDO → MongoDB driver.
8. Vyčistiť PVC.
9. Rebuild BE image, `kubectl apply -f k8s/`.

## Tip pre skúšku

Ak ti zadanie povie iba *"vymeň DB za Mongo"* a nemáš čas na celé prepísanie BE kódu, **napíš to do dokumentácie/komentárov** a aspoň:
- prepni image + porty + env + secret + init script (k8s časť funkčne dobehne)
- BE bude padať na pripojení — to je očakávané, dôležitý je infra prep

Skúšajúci zvyčajne hodnotí **k8s manifesty**, nie kompletné prepísanie aplikačného kódu.
