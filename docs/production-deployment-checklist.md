# Production Deployment Checklist

This document describes the production deployment requirements and the current production setup for the Service Desk application.

The application was deployed and verified during SD-33. SD-35 revalidated the documentation against the final portfolio implementation so this checklist can be used both as a description of the current deployment and as a reproducible deployment reference.

## Current Production Deployment

The portfolio demo currently uses:

- public application: `https://desk.kotov.lt`
- Railway web service for the Laravel application
- Railway MySQL database
- dedicated Railway queue worker service
- database-backed Laravel queues
- persistent private storage mounted for `storage/app/private`
- Resend HTTP API for transactional email
- HTTPS with secure session cookies
- trusted forwarded proxy headers configured for the Railway deployment
- Laravel `/up` health endpoint

External Jira, GitHub, and AI integrations remain optional and independently configurable. The core Service Desk ticket workflow does not depend on provider availability.

## Runtime Requirements

Production requires:

- PHP 8.4.1 or newer
- Composer
- MySQL-compatible database
- Node.js 22 for frontend asset builds, unless assets are built before deployment
- a web server or application platform capable of serving the Laravel `public` directory
- HTTPS
- a persistent queue worker
- persistent application storage for private attachments

The project CI currently uses PHP 8.5 and Node.js 22.

## Production Environment

Start from `.env.example`, but configure production-specific values and secrets on the hosting platform.

A representative production configuration is:

```env
APP_NAME="Service Desk"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://desk.kotov.lt
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

MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=noreply@your-domain.example
MAIL_FROM_NAME="${APP_NAME}"

DEMO_DATA_ENABLED=false
```

Generate a unique application key for a new deployment:

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

Typical provider configuration is supplied through environment variables only. Provider secrets must never be committed to the repository.

The core Service Desk workflow must continue to work when external integrations are disabled or unavailable.

## Install PHP Dependencies

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Laravel Tinker is intentionally retained as a runtime dependency. It does not expose an HTTP interface and requires privileged shell access, so production shell and container access must be restricted appropriately.

## Build Frontend Assets

```bash
npm ci
npm run build
```

The application requires the generated Vite manifest and frontend assets at runtime. If assets are built in CI and deployed as an artifact, Node.js does not need to be installed on the final application server.

## Database

Run production migrations with:

```bash
php artisan migrate --force
```

All application migrations have been verified against a fresh database. Never use `migrate:fresh` on an existing production database.

## Demo Data

Demo data is disabled by default:

```env
DEMO_DATA_ENABLED=false
```

For the intentionally disposable public portfolio demo only, temporarily enable demo seeding:

```env
DEMO_DATA_ENABLED=true
```

Then run:

```bash
php artisan config:clear
php artisan db:seed --force
```

After intentional demo initialization, set `DEMO_DATA_ENABLED=false` again and rebuild application caches:

```bash
php artisan optimize
```

Known demo credentials must never be seeded into a real environment containing real or sensitive data.

## Application Optimization

Run:

```bash
php artisan optimize
```

The application has been verified to support cached configuration, routes, events, and views.

## Queue Worker

A persistent production worker must run under Supervisor, systemd, a container process manager, or the hosting platform's dedicated worker service.

Recommended command:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=60
```

The current public demo uses a dedicated Railway worker service with the database queue connection.

The database queue `retry_after` value must remain greater than the worker timeout. Restart long-running workers after deployments that change application code:

```bash
php artisan queue:restart
```

Workflow notifications and integration jobs that depend on committed application state are configured to run after database commit where required.

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

Attachment storage must persist across deployments and container restarts. The current Railway demo mounts persistent storage for the private application storage directory.

Downloads pass through authenticated and authorization-controlled application routes, so `php artisan storage:link` is not required and private attachments are not exposed through the public filesystem.

## Email Delivery

Production email uses the Resend HTTP API rather than SMTP:

```env
MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=noreply@your-domain.example
MAIL_FROM_NAME="${APP_NAME}"
```

The sender domain must be verified with Resend. API keys and mail credentials must remain outside source control.

Mailtrap Sandbox may still be used for local SMTP testing, but it is not the production transport.

## Logging and Error Handling

Production must use:

```env
APP_DEBUG=false
LOG_LEVEL=info
```

The current deployment writes application logs to the platform-accessible production log stream.

External integration clients use finite connection and request timeouts. Integration exceptions are designed not to expose credentials, and production logs must not intentionally contain passwords, API keys, Bearer tokens, webhook secrets, or other sensitive credentials.

## HTTPS and Sessions

Production uses:

```env
APP_URL=https://desk.kotov.lt
SESSION_SECURE_COOKIE=true
```

The public demo is HTTPS-only. Laravel session cookies are HTTP-only, and secure-cookie handling is enabled for production.

## Trusted Proxies

The application is deployed behind Railway's proxy infrastructure.

Trusted proxy handling is configured in the Laravel bootstrap configuration so forwarded HTTPS information is recognized correctly. This is required for correct secure URL generation and HTTPS detection behind the hosting proxy.

Do not blindly trust arbitrary proxy headers when moving the application to another hosting environment. Re-evaluate proxy configuration for the selected platform.

## Health Check

Laravel exposes:

```text
/up
```

The production endpoint has been verified and can be used by the hosting platform for health checks.

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
- configuration, routes, events, and views are cached where expected
- database connection is correct
- queue connection is correct
- session driver is correct
- persistent attachment storage is mounted and writable
- production mail transport is configured through Resend
- trusted proxy handling matches the hosting platform

Then manually verify:

- registration, email verification, login, logout, and password reset
- ticket creation and editing
- assignment, priority, and status workflow
- requester reassignment notification behavior
- comments and human-readable audit history
- attachment upload and authorized download
- attachment persistence across redeploys
- queued notifications and queue worker processing
- `/up`
- REST API token creation and authenticated ticket access
- Jira, GitHub, webhook, and AI flows only when deliberately enabled

## Deployment Order

1. Deploy application source.
2. Configure production environment variables and secrets.
3. Install Composer production dependencies.
4. Build or deploy frontend assets.
5. Ensure `storage` and `bootstrap/cache` are writable.
6. Attach persistent private storage before accepting attachment uploads.
7. Run database migrations.
8. Seed demo data only for an explicitly designated disposable demo environment.
9. Disable demo seeding again after initialization.
10. Run Laravel optimization.
11. Start or restart queue workers.
12. Verify `/up`, authentication, ticket workflow, storage, notifications, API access, and deliberately enabled integrations.

## Final Portfolio Verification

The final portfolio verification confirmed:

- automated test suite passes;
- Laravel Pint passes;
- production Vite build succeeds;
- GitHub Actions CI succeeds;
- the public demo is operational at `https://desk.kotov.lt`;
- production authentication and ticket workflow were manually exercised while preparing the portfolio screenshots;
- production AI assistance was verified;
- documentation was audited against the final implementation.

This checklist now reflects the final portfolio deployment rather than a pre-SD-33 hosting plan.
