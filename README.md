# Openstead Laravel starter

A Laravel 12 application with Blade rendering, Vite assets, a migration,
MySQL access and private uploads. Create it from [Openstead](https://openstead.tech)'s
Starter Projects catalogue to configure a private managed MySQL database and
application secrets. The database starts automatically; complete the selected
application service checkout to start its build. `composer.lock` and
`package-lock.json` lock its dependencies. The
Dockerfile uses PHP 8.4 and installs production Composer dependencies without
running migration scripts during the image build.

## Deployment settings

| Setting | Value |
| --- | --- |
| Application root | Repository root (`.`) |
| Service type | Web service |
| Build method | Dockerfile |
| Dockerfile / context | `Dockerfile` / `.` |
| Build / start commands | Leave empty |
| Pre-deploy command | `php artisan migrate --force` |
| Port | `8000` |
| Health check | `/health` |
| Persistent disk mount | `/app/storage` |
| Replicas | `1` while using a local persistent disk |

Supply `DB_HOST`, `DB_PORT`,
`DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` from the managed service's private
connection. Set `DB_CONNECTION=mysql`, `APP_DEBUG=false`, an HTTPS `APP_URL`, and
a stable random `APP_KEY` in the service's secret variables. Generate an APP_KEY
once with `php artisan key:generate --show` in a trusted local shell and store it
as a secret; do not run key generation on every deployment.

Add a persistent disk at `/app/storage` if your application stores uploads or
local files that must survive replacement; persistent storage is priced separately.
The entrypoint initializes an empty mounted disk, switches to `www-data`, and caches
configuration using runtime variables. Migrations run once in pre-deploy, not
on every web-process start. No database password or APP_KEY is built into the
image. User uploads are outside `/app/public` and never executable or public.

## Acceptance endpoints

Set a random `ACCEPTANCE_TOKEN` of at least 32 characters for the acceptance run.
Pass it in the `X-Runivo-Acceptance` header. Never put it in a URL, screenshot,
shared log, or committed file. Remove the variable and redeploy after testing.
Missing/short tokens disable all acceptance endpoints.

- `GET /`: Blade page with Vite-built CSS and JavaScript.
- `GET /health`: minimal health result; returns 503 if MySQL or the migrated
  acceptance table is unavailable. No credentials or connection details.
- `POST /acceptance/records`: authenticated multipart fields `marker` (at most
  120 characters) and `file` (plain text, at most 64 KiB). Returns a generated
  `id`, the marker, `filePresent`, and `fileSha256`. No caller-selected SQL or path.
- `GET /acceptance/records/{id}`: authenticated read of that marker and upload hash.
- `PATCH /acceptance/records/{id}`: authenticated update of `marker`; the upload
  remains unchanged so a database-only restore can be verified independently.
- `DELETE /acceptance/records/{id}`: authenticated removal of that test record/file.

## Full infrastructure acceptance

1. Deploy against a fresh managed MySQL database. Require the pre-deploy
   migration to succeed, HTTP `/health` to pass, and CSS/JS assets to return 200.
2. Confirm unauthorized read/write calls return 401. Upload a unique text file
   with the temporary token; keep its returned ID, marker and local SHA-256.
3. Replace the web container through a normal redeploy. Read the same record;
   both the MySQL value and upload hash must match.
4. Replace the database container while preserving its managed disk. Require
   `/health` and the same authenticated read to recover with unchanged data.
5. Create a managed database backup containing the record. Change its marker
   with the authenticated PATCH endpoint, restore the backup through the
   managed restore operation, and read the original marker again. Keep the
   upload file intact when testing database restoration: database backups do
   not contain the application's separate upload disk.
6. Verify private uploads cannot be fetched via `/storage/...`, remove the
   temporary token, and suspend/remove only dedicated acceptance resources.

Record actual deployment IDs and results. A unit test, SQLite run, or successful
image build alone does **not** establish MySQL persistence or backup restoration.

## Local framework preflight

With PHP 8.4+ and the required extensions plus `pdo_sqlite`, run:

```sh
composer install
npm ci
npm run build
php tests/preflight.php
```

This boots the real Laravel kernel, migrates an isolated in-memory SQLite
database, and tests auth, validation, upload hash/path isolation, reads/deletion,
health, and the Blade/Vite page. It never substitutes for the MySQL acceptance
above. Docker must be available to validate the production image locally.
