# Security and Production Configuration Review

## Overview

This document records the security and production-readiness review performed as part of SD-31.

The review covers application configuration, authentication, authorization, REST API security, session and cookie security, CSRF protection, file storage, uploads, logging, queues, mail, demo data, dependency configuration, secret management, and production deployment concerns.

Findings are classified as:

- **Critical** - must be resolved before deployment.
- **Important** - must be fixed or explicitly addressed before the public demo is deployed.
- **Nice to have** - improvements that reduce risk or simplify production configuration but are not deployment blockers.
- **Reviewed** - areas that were reviewed and where no actionable issue was identified.

## Summary

### Critical

No Critical findings were identified.

### Important

1. REST API authentication strategy is incomplete.
2. The PHP requirement declared in `composer.json` does not match the currently locked dependency set.
3. Public demo accounts use known credentials, including an Admin account.
4. Production HTTPS, session cookie, logging, mail, and proxy configuration must be explicitly configured for the deployment environment.

### Nice to have

1. The private local filesystem disk exposes Laravel signed file-serving routes that are not currently used by the application.
2. `User.role` is mass assignable even though no exploitable HTTP mass-assignment path was identified.
3. `laravel/tinker` is installed as a production dependency even though it is not required for normal HTTP application operation.

---

## 1. REST API Authentication

**Severity:** Important  
**Status:** Confirmed - fix in SD-31

The REST API routes are registered under the standard Laravel `api` middleware group and protected using the default `auth` middleware.

The default authentication guard is the session-based `web` guard.

The standard `api` middleware group does not start a session. The application does not enable Laravel stateful API middleware and Laravel Sanctum is not currently installed.

Existing API feature tests use `actingAs()`. These tests correctly verify authorization after a user has been authenticated, but they do not reproduce the authentication flow of a real HTTP API client.

### Decision

Introduce an explicit API authentication strategy.

The planned approach is to use Laravel Sanctum personal access tokens and protect API routes with `auth:sanctum`.

The web application will continue to use Fortify and session-based authentication.

The API will use Bearer token authentication and remain stateless.

### Target

SD-31.

---

## 2. PHP Version and Locked Dependencies

**Severity:** Important  
**Status:** Confirmed - deployment decision required

`composer.json` currently declares:

```json
"php": "^8.3"
```

Laravel Framework 13.29.0 itself supports PHP `^8.3`.

However, the current `composer.lock` contains Symfony 8.1 components. These components require PHP `>=8.4.1`.

The current Laravel Sail environment runs PHP 8.5.9 and satisfies all installed platform requirements.

As a result, a production environment running PHP 8.3 would not be able to install the currently locked dependency set even though `composer.json` declares PHP 8.3 compatibility.

### Decision

Do not change the dependency graph during the security review.

The production runtime target must be explicitly selected during deployment preparation.

Two valid strategies exist:

1. Target PHP 8.3 and resolve dependencies against a PHP 8.3 Composer platform.
2. Use PHP 8.4.1 or newer and update the project's declared PHP requirement to match the actual supported production runtime.

### Target

SD-32.

---

## 3. Public Demo Accounts and Seeder Safety

**Severity:** Important  
**Status:** Confirmed - public demo strategy required

`DatabaseSeeder` creates the following demo users:

- `requester@example.com`
- `agent@example.com`
- `admin@example.com`

All demo accounts use the publicly documented password:

```text
password
```

This includes an Admin account with administrative application permissions.

Known credentials are acceptable for an intentionally disposable portfolio demo environment, but they must not be treated as secure production credentials.

The current `DatabaseSeeder` also has no environment guard preventing demo data from being seeded into an unintended production database.

### Decision

Keep demo accounts because demonstrating Requester, Agent, and Admin workflows is useful for the portfolio project.

The public demo must be treated as disposable demo infrastructure containing no real or sensitive data.

Deployment preparation must define how demo data is initialized and restored and how destructive actions are handled.

Demo-specific seeding should be separated or otherwise explicitly controlled so that known demo credentials cannot accidentally be introduced into a non-demo production environment.

### Target

SD-32 / SD-33.

---

## 4. HTTPS, Cookies, Logging, Mail, and Trusted Proxies

**Severity:** Important  
**Status:** Deployment configuration required

Laravel uses safe framework defaults for production error display:

- `APP_ENV` defaults to `production`.
- `APP_DEBUG` defaults to `false`.
- `APP_KEY` is supplied through the environment.

However, the actual production environment must explicitly configure security-sensitive values.

### Production requirements

The public deployment must use:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://...
SESSION_SECURE_COOKIE=true
```

A production-appropriate `LOG_LEVEL` must be selected instead of relying on the development-oriented `debug` default.

Production mail credentials and sender configuration must be provided through environment variables.

Trusted proxy configuration must be reviewed after the hosting architecture is selected. This is especially important if Laravel is deployed behind a reverse proxy, load balancer, hosting proxy, or similar infrastructure.

HTTPS enforcement belongs primarily to the deployment and infrastructure configuration rather than being hard-coded into the application before the hosting architecture is known.

### Target

SD-32.

---

## 5. Private Filesystem Serving

**Severity:** Nice to have  
**Status:** Reviewed

The default local filesystem disk points to:

```text
storage/app/private
```

and currently has Laravel file serving enabled with:

```php
'serve' => true,
```

Laravel therefore registers framework GET and PUT storage routes.

The framework implementation was reviewed.

Private file downloads require a valid relative signed URL. Uploads require both the upload flag and a valid relative signed URL. Path traversal attempts are handled and invalid production requests are hidden behind a 404 response.

No security vulnerability was identified in these framework routes.

The application itself does not currently use `temporaryUrl()` or `temporaryUploadUrl()`.

Ticket attachments are downloaded through the application's own authenticated and authorized attachment controller.

### Decision

The framework file-serving routes appear unnecessary for the current application.

Removing `serve => true` may reduce unnecessary route surface, but this is not a deployment blocker.

---

## 6. User Role Mass Assignment

**Severity:** Nice to have  
**Status:** Reviewed - no exploitable path identified

The `User` model currently includes `role` in its fillable attributes.

The application was searched for HTTP-accessible user creation, update, `fill()`, and similar mass-assignment operations.

No application path was found that mass assigns user-controlled data into the `User` model.

No privilege-escalation path through mass assignment was identified.

### Decision

There is no current exploitable issue.

Because `role` is a security-sensitive attribute, removing it from general mass assignment may still be considered as a defense-in-depth improvement.

---

## 7. Laravel Tinker

**Severity:** Nice to have  
**Status:** Reviewed

`laravel/tinker` is currently listed under production Composer dependencies.

Tinker does not expose an HTTP endpoint and does not create a direct remote attack surface.

It does provide an interactive application REPL to users who already have command-line access to the deployed application.

### Decision

No immediate security change is required.

Whether Tinker should remain installed in the production environment can be decided during deployment preparation.

---

## 8. Authentication and Login Protection

**Status:** Reviewed - no issue identified

Laravel Fortify is used for web authentication.

The application configures login rate limiting to five attempts per minute using a key derived from the normalized login identifier and client IP address.

Registration and other unused Fortify features are not publicly enabled.

Passwords use Laravel's hashed model cast.

Password and remember-token fields are hidden from model serialization.

No actionable issue was identified during this review.

---

## 9. Authorization

**Status:** Reviewed - no issue identified

Ticket access is controlled through application policies and Form Request authorization.

The review confirmed role-based restrictions for:

- viewing tickets,
- updating tickets,
- assigning tickets,
- changing priority,
- changing status,
- adding comments,
- deleting tickets.

Requesters are restricted to appropriate operations on their own tickets.

Agent and Admin permissions are explicitly defined.

Ticket deletion is restricted to Admin users.

API authorization tests cover both allowed and forbidden operations.

No authorization bypass was identified.

---

## 10. CSRF and Session Security

**Status:** Reviewed / deployment configuration required

Web routes use Laravel's standard `web` middleware group and therefore receive Laravel CSRF protection.

Session cookies are HTTP-only by default.

SameSite defaults to `lax`.

Secure-cookie enforcement depends on the production environment and must be enabled for the HTTPS public deployment.

No application-specific CSRF bypass was identified.

---

## 11. File Upload and Attachment Security

**Status:** Reviewed - no Critical issue identified

Ticket attachments are stored on the private local filesystem disk rather than the public disk.

Attachment operations require authenticated access.

Application authorization is applied when accessing ticket attachments.

Upload validation restricts accepted file types and limits attachment size.

Physical attachment files are not exposed through a public storage symlink.

The application also contains failure handling that removes a newly stored physical file if attachment metadata persistence fails.

No Critical file-access issue was identified.

---

## 12. CORS

**Status:** Reviewed - no action required

No custom `config/cors.php` configuration is currently present.

The application currently has no requirement for unrestricted cross-origin browser access.

No permissive CORS configuration was identified.

CORS should remain restrictive unless a concrete cross-origin browser client is introduced.

---

## 13. Queue and Mail Configuration

**Status:** Reviewed / deployment configuration required

The application uses the database queue driver.

Failed jobs are persisted using the database UUID failed-job driver.

Workflow notifications that depend on committed database changes are configured to be dispatched after the database transaction commits.

Mail credentials are supplied through environment variables and no hard-coded mail credentials were identified.

Production queue workers and production mail transport configuration must be established during deployment preparation.

---

## 14. Logging and Error Exposure

**Status:** Reviewed / deployment configuration required

Laravel production debug mode defaults to disabled.

No custom exception handler exposing application internals was identified.

No application logging calls were found that intentionally log passwords, tokens, or other credentials.

Laravel's default log channels use `LOG_LEVEL` from the environment, with `debug` as the fallback.

### Decision

Production deployment must explicitly select an appropriate log level and keep `APP_DEBUG=false`.

---

## 15. Secrets and Repository History

**Status:** Reviewed - no issue identified

The repository ignores local environment files and common sensitive files.

Tracked filenames were reviewed for environment files, credentials, private keys, certificates, and similar secret material.

Git history was also checked for previously committed environment and credential files.

The only environment-style file found in repository history was `.env.example`.

No committed `.env`, production environment file, private key, or credential file was identified.

Credential-like values found in tracked files were placeholders such as:

```text
MAIL_PASSWORD=null
MAIL_PASSWORD=your_mailtrap_password
```

No repository secret exposure was identified.

---

## 16. Public Files and Storage

**Status:** Reviewed - no issue identified

The public directory contains only expected application assets and entry-point files.

No `public/storage` symlink currently exists.

Private uploaded files and application logs are not tracked by Git.

No unintended publicly accessible application data was identified.

---

## 17. Production Optimization

**Status:** Reviewed / SD-32

Laravel provides the expected production optimization commands, including:

```text
optimize
config:cache
event:cache
route:cache
view:cache
```

Production deployment should install Composer dependencies without development packages and execute the appropriate Laravel optimization commands.

The exact production deployment procedure will be documented as part of SD-32.

---

## Remediation Plan

### SD-31

Before closing the security review:

1. Implement an explicit REST API authentication strategy.
2. Add or update automated tests for real API authentication.
3. Consider small defense-in-depth changes identified during the review where they do not unnecessarily expand scope.
4. Run the relevant targeted tests.
5. Run the complete automated test suite.
6. Run Laravel Pint.
7. Update this document with the final remediation status.

### SD-32

Production deployment preparation must address:

- production PHP version and Composer dependency compatibility,
- `APP_ENV`,
- `APP_DEBUG`,
- HTTPS `APP_URL`,
- secure session cookies,
- trusted proxy configuration,
- production logging,
- production mail configuration,
- queue worker configuration,
- production Composer installation,
- Laravel production optimization,
- controlled demo seeding.

### SD-33

Public demo deployment must address:

- safe use of publicly documented demo accounts,
- disposable demo data,
- Admin demo access,
- demo data restoration/reset strategy,
- verification that no real or sensitive data is present.

---

## Current Review Result

At the end of the audit phase:

- **Critical findings:** 0
- **Important findings:** 4
- **Nice-to-have findings:** 3

No unresolved Critical security finding exists.

The REST API authentication finding will be remediated as part of SD-31.

Deployment-specific Important findings are explicitly assigned to SD-32 and SD-33 before the public demo is released.
