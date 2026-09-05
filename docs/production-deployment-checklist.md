# Production Deployment Checklist

This document describes the production deployment requirements for the Service Desk application.

The exact hosting platform is selected and configured separately during SD-33. This checklist defines the application-level requirements that must be satisfied by that platform.

## Runtime Requirements

Production requires:

- PHP 8.4.1 or newer
- Composer
- MySQL-compatible database
- Node.js 22 for frontend asset builds, unless assets are built in CI before deployment
- a web server capable of serving the Laravel `public` directory
- HTTPS
- a persistent queue worker
- persistent application storage

The project CI currently uses PHP 8.5 and Node.js 22.

## Production Environment

Start from `.env.example`, but configure production-specific values.

Required production settings include:

```env
APP_NAME="Service Desk"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-domain.example
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"

DEMO_DATA_ENABLED=false
```

Generate a unique application key:

```bash
php artisan key:generate
```

Never reuse development credentials or commit the production `.env` file.

## Optional Integrations

Jira, GitHub, and AI integrations are optional and independently configurable.

They should remain disabled until valid production credentials are configured:

```env
JIRA_ENABLED=false
GITHUB_INTEGRATION_ENABLED=false
AI_ENABLED=false
```

The core Service Desk workflow must continue to work when external integrations are disabled or unavailable. Secrets must be provided through environment variables and must never be committed to the repository.

## Install PHP Dependencies

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Laravel Tinker is intentionally retained as a runtime dependency. It does not expose an HTTP interface and requires privileged shell access. Production shell and container access must therefore be restricted appropriately.

## Build Frontend Assets

```bash
npm ci
npm run build
```

The application requires the generated Vite manifest and frontend assets at runtime. If assets are built in CI and deployed as an artifact, Node.js does not need to be installed on the final application server.

## Database

```bash
php artisan migrate --force
```

All application migrations have been verified against a fresh database. Do not use `migrate:fresh` on an existing production database.

## Demo Data

Demo data is disabled by default:

```env
DEMO_DATA_ENABLED=false
```

For a dedicated public demo deployment, explicitly set:

```env
DEMO_DATA_ENABLED=true
```

Then:

```bash
php artisan config:clear
php artisan db:seed --force
```

Known demo credentials must never be seeded into a real production environment. After demo seeding, return `DEMO_DATA_ENABLED` to `false` and rebuild caches:

```bash
php artisan optimize
```

## Application Optimization

```bash
php artisan optimize
```

The application has been verified to support cached configuration, routes, events, and views.

## Queue Worker

A persistent production worker must run under Supervisor, systemd, a container process manager, or the hosting platform's worker service.

Recommended command:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=60
```

The database queue `retry_after` value is greater than the worker timeout. After deployment, restart long-running workers:

```bash
php artisan queue:restart
```

## Storage and Attachments

Ticket attachments are private files stored on Laravel's `local` filesystem disk:

```text
storage/app/private/ticket-attachments
```

The application must have write access to:

```text
storage/
bootstrap/cache/
```

Attachment storage must persist across deployments and container restarts. Downloads pass through authorization-controlled application routes, so `php artisan storage:link` is not required.

If the SD-33 hosting platform uses an ephemeral filesystem, persistent storage must be configured before deployment. Migrating attachments to object storage is outside SD-32.

## Logging and Error Handling

Production must use:

```env
APP_DEBUG=false
LOG_LEVEL=info
```

External integration clients use finite connection and request timeouts. Application-level integration exceptions do not intentionally include API credentials.

Production logs must be accessible to the operator but must not be publicly accessible through the web server.

## HTTPS and Sessions

```env
APP_URL=https://your-production-domain.example
SESSION_SECURE_COOKIE=true
```

Production must use HTTPS. Laravel session cookies are configured as HTTP-only.

## Trusted Proxies

The application does not currently trust arbitrary reverse proxies. Do not configure global proxy trust until the hosting architecture is known.

During SD-33, configure trusted proxies for the selected hosting provider and verify HTTPS detection, generated secure URLs, client IP handling, and `X-Forwarded-*` headers.

## Health Check

Laravel exposes:

```text
/up
```

The production platform may use this endpoint for health checks.

## Deployment Verification

Run:

```bash
php artisan about
php artisan migrate:status
```

Confirm:

- environment is `production`
- debug mode is disabled
- application URL uses HTTPS
- configuration, routes, events, and views are cached
- database connection is correct
- queue connection is correct
- session driver is correct

Then manually verify login, ticket creation and workflow, comments, attachment upload/download, queued notifications, `/up`, and any deliberately enabled optional integrations.

## Deployment Order

1. Deploy application source.
2. Configure production environment variables.
3. Install Composer production dependencies.
4. Build or deploy frontend assets.
5. Ensure `storage` and `bootstrap/cache` are writable.
6. Run database migrations.
7. Seed demo data only for an explicitly designated demo environment.
8. Run Laravel optimization.
9. Start or restart queue workers.
10. Verify `/up` and application functionality.

SD-33 is responsible for applying this checklist to the selected public hosting environment.
