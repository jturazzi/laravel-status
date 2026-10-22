# Laravel Status

Central dashboard monitoring Laravel applications through the `GET /api/system-info`
endpoint exposed by the [`jturazzi/laravel-system-info`](https://github.com/jturazzi/laravel-system-info) package.

For each application it shows:

- overall status (`OK`, `Degraded`, `Down`, `Unreachable`) and reported issues;
- deployed version, commit and build date;
- PHP / Laravel versions, flagging applications **behind** the rest of the fleet (↓);
- database (engine, version, size, latency), pending migrations;
- queue (pending / failed jobs), free disk space, log size, response time.

Applications are queried in parallel and the result is cached for 30 s
(`?refresh=1` to force a refresh). `GET /status.json` returns the same data for an
external monitoring tool.

## Declaring applications

No application is hard-coded. Two sources are merged:

1. **From the UI** — the `/apps` page adds, edits, disables and removes applications.
   They are stored in the SQLite database; per-app tokens are encrypted with `APP_KEY`.
2. **From the environment** — `STATUS_APPS`, read-only in the UI:

   ```dotenv
   STATUS_APPS="Billing|https://billing.example.com,CRM|https://crm.example.com|crm-token"
   ```

   Each entry is `Name|base URL|optional token`. Invalid entries are ignored.

An application without its own token uses the shared `STATUS_TOKEN`.

## Languages

The interface is available in English and French. The language comes from the
EN / FR switcher (remembered in a cookie), otherwise from the browser, otherwise
from `APP_LOCALE`. Translations live in `lang/fr.json`; `/status.json` stays in English.

## Configuration

| Variable | Purpose |
|----------|---------|
| `STATUS_USERNAME` / `STATUS_PASSWORD` | HTTP Basic access to the dashboard. Empty = everyone is rejected. |
| `STATUS_TOKEN` | Shared token sent to the applications (= their `SYSTEM_INFO_TOKEN`). |
| `STATUS_APPS` | Applications declared in the environment (see above). |
| `STATUS_TIMEOUT` / `STATUS_CACHE_TTL` | Per-application timeout (5 s) and cache duration (30 s). |
| `APP_TIMEZONE` | Timezone used to display dates (e.g. `Europe/Paris`). |
| `APP_KEY` | Also encrypts the stored tokens: keep it stable across deployments. |

## Docker

See [`docker-compose.yml`](docker-compose.yml). The SQLite database defaults to
`storage/app/private/database.sqlite`, inside the `storage` volume, so applications
added from the UI survive redeployments; migrations run automatically on start.

## Development

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan test
php artisan serve
```

## License

MIT.
