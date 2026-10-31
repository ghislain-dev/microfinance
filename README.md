# Microfinance — gestion financière pour coopératives, tontines et microfinance

Règles, structure et planification : voir [`CLAUDE.md`](CLAUDE.md).
Décisions d'architecture : [`docs/adr/`](docs/adr/).

## Démarrage (développement)

```bash
docker compose up -d                  # MySQL 8.4 (port 3307) + Redis
composer install
cp .env.example .env && php artisan key:generate
# dans .env : APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false, DB_PASSWORD=...
php artisan migrate --seed            # rôles + démo (en local : <role>@demo.test / password)
npm ci && npm run build               # Bootstrap (ou npm run dev pendant le développement)
php artisan queue:work                # envoi des codes de connexion (e-mail en file d'attente)
```

Créer une organisation réelle et son premier administrateur :

```bash
php artisan organisation:creer COOPEC-GOMA "Coopec Goma"
```

En production, lancer le planificateur chaque minute (contrôle des soldes à 2 h) :

```cron
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

## Vérifications (obligatoires avant chaque commit)

```bash
./vendor/bin/pint
./vendor/bin/phpstan analyse
composer audit
php artisan test          # SQLite en mémoire
composer test:mysql       # tests du groupe « mysql » sur la base microfinance_test (conteneur)
```
