# Inventário IT

[Português](README.md) · **English**

> **Status: work in progress.** The database, API, importer, login, and company and login management are done, but the day-to-day management screens (asset, line, printer lists…) are not built yet. Not production-ready. See [Project status](#project-status).

A browser-only **demo** with fictitious data lives in [`demo/index.html`](demo/index.html): open it in a browser (or publish it with GitHub Pages) to try the screens. Data stays in that browser only.

A self-hosted web app to manage the IT equipment of several companies: laptops, monitors, phones, printers, servers, NAS, mobile lines and accessories. Every item belongs to a company, has a full history and can be checked out to a person, a location or another asset.

The API is **Snipe-IT compatible**: same routes, fields and responses. Scripts and tools built for Snipe-IT (for example the SnipeitPS PowerShell module) work by changing only the URL and the token.

The user interface is in **European Portuguese**. The full documentation is in the [Portuguese README](README.md); this page is a summary.

| | |
| --- | --- |
| Backend | Laravel 12, PHP 8.3 |
| Database | PostgreSQL 16 |
| Server | PHP-FPM + nginx in one container ([serversideup/php](https://serversideup.net/open-source/docker-php/)) |
| Install | Docker Compose |

## Features

- **Multi-company.** Assets, accessories, people and locations belong to a company. Asset tags carry the company prefix (`CNT-00042`) with their own numbering.
- **Assets** with manufacturer, model, category (inherited from the model), status, serial, purchase and warranty data.
- **Check-out / check-in** to a person, a location or another asset, with Snipe-IT status types (`deployable`, `pending`, `undeployable`, `archived`).
- **Printers** (IP, hostname, type, page counter…), **servers and NAS** (name, type, model, IP, location).
- **Mobile lines** (number, plan, PIN/PUK, activation date). PIN/PUK are encrypted at rest and every view is logged. A line follows the phone it is checked out to.
- **Accessories** with stock and minimum stock alerts.
- **Custom fields** in fieldsets, with validation and optional encryption.
- **Immutable history** of every change, check-out, check-in, transfer and PIN/PUK view.
- **Incomplete assets.** Assets with no manufacturer, a generic model or no serial are flagged until fixed (dashboard and `?incomplete=1` in the API).
- **Import / export** (CSV and Excel) with automatic file-type detection, a row-by-row preview, auto-creation of companies, categories, manufacturers, models, people and locations, and undo within 24 hours. Snipe-IT asset exports are recognised and routed by category.
- **Logins** with two access levels (*Pode mexer* = can edit, *Só consulta* = read-only), password reset by e-mail and e-mail invites.
- **API tokens** created in the app, either **read-only** or **read and write**, with an expiry date.
- **Daily database backups.**

## Quick start

You need Docker with the Compose plugin.

```bash
cp .env.example .env
# edit .env: set DB_PASSWORD, ADMIN_EMAIL and ADMIN_PASSWORD
docker compose run --rm --no-deps -e AUTORUN_ENABLED=false app php artisan key:generate --show
# copy the base64:... value into APP_KEY in .env, and keep a copy somewhere safe
docker compose up -d --build
docker compose exec app php artisan db:seed
```

Open <http://localhost:8080>.

> Keep a copy of `APP_KEY` outside the server. It encrypts PIN/PUK values; without it they cannot be recovered, not even from backups.

Optional demo data (fictitious): `docker compose exec app php artisan db:seed --class=DemoSeeder`. Do not use it in production.

## API

Base URL `/api/v1`, `Authorization: Bearer <token>`. Lists return `{ "total", "rows" }`, writes return `{ "status", "messages", "payload" }`, as in Snipe-IT. Main resources: `hardware` (with `checkout`, `checkin`, `bytag`, `byserial`), `accessories`, `users`, `companies`, `locations`, `models`, `manufacturers`, `categories`, `statuslabels`, `suppliers`, `departments`, `fields`, `fieldsets`, `reports/activity`.

Extensions not in Snipe-IT (Snipe-IT tools simply ignore them):

- `GET /reports/summary`: totals per company (assets, printers, servers/NAS, lines, accessories, people, incomplete).
- `GET /hardware/{id}/pin`: PIN/PUK of a line, for users who can edit; logged.
- `GET /hardware?incomplete=1`: only incomplete assets, each with `missing_fields`.

## Security

- `.env` is never committed. Change every example password and generate your own `APP_KEY`.
- Passwords use bcrypt; API tokens are stored hashed and can be read-only and expiring.
- Permissions and token abilities are enforced server-side on every request.
- Put the app behind HTTPS (or keep it on an internal network / VPN).
- To report a vulnerability, see [SECURITY.md](SECURITY.md).

## Project status

- [x] Docker install with daily backups
- [x] Database and Snipe-IT compatible API
- [x] Login, password reset, two access levels, API tokens
- [x] Company management and company switcher
- [x] Import / export with preview and undo
- [ ] Management screens (assets with the smart form, printers, servers/NAS, lines, accessories, people)
- [ ] Microsoft (Entra ID) sign-in

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

[MIT](LICENSE). Snipe-IT is a trademark of its respective owners. This project is not affiliated with Snipe-IT; it only follows the same API for compatibility.
