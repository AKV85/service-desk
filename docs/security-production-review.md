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

1. ~~REST API authentication strategy is incomplete.~~ **Resolved in SD-31.**
2. The PHP requirement declared in `composer.json` does not match the currently locked dependency set.
3. Public demo accounts use known credentials, including an Admin account.
4. Production HTTPS, session cookie, logging, mail, and proxy configuration must be explicitly configured for the deployment environment.

### Nice to have

1. ~~The private local filesystem disk exposes Laravel signed file-serving routes that are not currently used by the application.~~ **Resolved in SD-31.**
2. ~~`User.role` is mass assignable even though no exploitable HTTP mass-assignment path was identified.~~ **Resolved in SD-31.**
3. `laravel/tinker` is installed as a production dependency even though it is not required for normal HTTP application operation.

---

## 1. REST API Authentication

**Severity:** Important  
**Status:** Resolved in SD-31

### Original finding

The REST API routes were registered under the standard Laravel `api` middleware group and protected using the default `auth` middleware.

The default authentication guard was the session-based `web` guard.

The standard `api` middleware group does not start a session. The application did not enable Laravel stateful API middleware and Laravel Sanctum was not installed.

Existing API feature tests used `actingAs()`. These tests correctly verified authorization after a user had been authenticated, but they did not reproduce the authentication flow of a real HTTP API client.

### Resolution

Laravel Sanctum was added to provide explicit API authentication using personal access tokens.

The web application continues to use Laravel Fortify and session-based authentication.

The REST API uses stateless Bearer token authentication.

Protected API routes now use:

```php
auth:sanctum
```

The application provides an API token creation endpoint:

```text
POST /api/tokens
```

Clients authenticate using their email address and password. After successful authentication, the endpoint creates a Sanctum personal access token and returns the plain-text token to the client.

The token is then supplied to protected API requests using:

```text
Authorization: Bearer <token>
```

The application also provides an authenticated endpoint for revoking the currently used personal access token:

```text
DELETE /api/tokens/current
```

After the token is revoked, the same Bearer token can no longer authenticate protected API requests.

### Token endpoint rate limiting

Because the token creation endpoint accepts user credentials and is publicly accessible, explicit rate limiting was added.

The token endpoint is limited to:

```text
5 attempts per minute
```

The rate-limit key is derived from the normalized email address and client IP address.

This provides protection against repeated password attempts against the API authentication endpoint.

### Verification

Dedicated API authentication tests verify that:

- valid credentials can create a personal access token,
- invalid credentials do not create a token,
- a Bearer token can authenticate a protected API request,
- a protected API request without authentication returns `401`,
- the current personal access token can be revoked,
- a revoked token can no longer authenticate,
- the token endpoint is rate limited.

Existing API authorization tests also continue to pass after the authentication change.

**Result:** Resolved.

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
**Status:** Resolved in SD-31

### Original finding

The default local filesystem disk points to:

```text
storage/app/private
```

and had Laravel file serving enabled with:

```php
'serve' => true,
```

Laravel therefore registered framework GET and PUT storage routes.

The framework implementation was reviewed.

Private file downloads required a valid relative signed URL. Uploads required both the upload flag and a valid relative signed URL. Path traversal attempts were handled and invalid production requests were hidden behind a `404` response.

No security vulnerability was identified in these framework routes.

The application itself does not use `temporaryUrl()` or `temporaryUploadUrl()`.

Ticket attachments are downloaded through the application's own authenticated and authorized attachment controller.

### Resolution

Because the application does not use Laravel's framework file-serving functionality for the private local disk, the following configuration was removed:

```php
'serve' => true,
```

The unnecessary framework storage routes are therefore no longer registered.

Attachment functionality was verified after the configuration change and continues to operate through the application's authorized attachment workflow.

**Result:** Resolved as a defense-in-depth improvement.

---

## 6. User Role Mass Assignment

**Severity:** Nice to have  
**Status:** Resolved in SD-31

### Original finding

The `User` model included `role` in its fillable attributes.

The application was searched for HTTP-accessible user creation, update, `fill()`, and similar mass-assignment operations.

No application path was found that mass assigned user-controlled data into the `User` model.

No privilege-escalation path through mass assignment was identified.

### Resolution

Although no exploitable path was found, `role` is a security-sensitive attribute.

It was therefore removed from the model's general mass-assignable attributes as a defense-in-depth improvement.

The model now allows general mass assignment only for the non-role user attributes required by the application.

Existing API and application tests continue to pass after this change.

**Result:** Resolved.

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

### Target

SD-32.

---

## 8. Authentication and Login Protection

**Status:** Reviewed - no issue identified

Laravel Fortify is used for web authentication.

The application configures login rate limiting to five attempts per minute using a key derived from the normalized login identifier and client IP address.

Registration and other unused Fortify features are not publicly enabled.

Passwords use Laravel's hashed model cast.

Password and remember-token fields are hidden from model serialization.

The REST API now has its own explicit Sanctum-based Bearer token authentication flow and rate-limited token creation endpoint.

No actionable authentication issue remains within the SD-31 scope.

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

REST API routes use the same application authorization policies after Sanctum authentication.

API authorization tests cover both allowed and forbidden operations.

No authorization bypass was identified.

---

## 10. CSRF and Session Security

**Status:** Reviewed / deployment configuration required

Web routes use Laravel's standard `web` middleware group and therefore receive Laravel CSRF protection.

Session cookies are HTTP-only by default.

SameSite defaults to `lax`.

Secure-cookie enforcement depends on the production environment and must be enabled for the HTTPS public deployment.

The REST API uses stateless Bearer token authentication and does not depend on the web session for API authentication.

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

Unnecessary Laravel framework file-serving routes for the private local disk were disabled during SD-31.

Attachment tests continue to pass after this configuration change.

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

### Target

SD-32.

---

## 14. Logging and Error Exposure

**Status:** Reviewed / deployment configuration required

Laravel production debug mode defaults to disabled.

No custom exception handler exposing application internals was identified.

No application logging calls were found that intentionally log passwords, tokens, or other credentials.

Laravel's default log channels use `LOG_LEVEL` from the environment, with `debug` as the fallback.

### Decision

Production deployment must explicitly select an appropriate log level and keep:

```text
APP_DEBUG=false
```

### Target

SD-32.

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

The unused Laravel private filesystem serving routes were disabled during SD-31.

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

## Remediation Completed in SD-31

The following changes were completed as part of the security review:

1. Laravel Sanctum was installed and configured.
2. The `User` model was configured with Sanctum API token support.
3. The Sanctum personal access token database migration was added.
4. Protected REST API routes were changed to `auth:sanctum`.
5. A token creation endpoint was added for API clients.
6. A current-token revocation endpoint was added.
7. API token creation was protected by a five-attempts-per-minute rate limiter keyed by normalized email address and client IP.
8. Dedicated tests were added for the real Bearer-token authentication flow.
9. Existing REST API authorization tests were verified after the authentication change.
10. `role` was removed from the `User` model's general mass-assignable attributes.
11. Unused Laravel private filesystem serving was disabled.
12. Attachment behavior was verified after disabling framework file serving.
13. Laravel Pint was executed successfully.
14. The complete automated test suite was executed successfully.

---

## Follow-up Work

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
- controlled demo seeding,
- production decision regarding Laravel Tinker.

### SD-33

Public demo deployment must address:

- safe use of publicly documented demo accounts,
- disposable demo data,
- Admin demo access,
- demo data restoration/reset strategy,
- verification that no real or sensitive data is present.

---

## Final Review Result

At the completion of SD-31:

- **Critical findings:** 0
- **Important findings originally identified:** 4
- **Important findings resolved in SD-31:** 1
- **Important findings assigned to deployment follow-up:** 3
- **Nice-to-have findings originally identified:** 3
- **Nice-to-have findings resolved in SD-31:** 2
- **Nice-to-have findings assigned to deployment follow-up:** 1

No unresolved Critical security findings remain.

The REST API authentication finding was resolved by implementing stateless Laravel Sanctum personal access token authentication with protected API routes, token revocation, rate limiting, and dedicated authentication tests.

The remaining Important findings are deployment-specific and are explicitly assigned to SD-32 and SD-33 before the public demo is released.

Final automated verification:

```text
Tests: 177 passed (467 assertions)
```

Laravel Pint verification:

```text
93 files checked
3 style issues fixed
PASS
```

SD-31 therefore completes the application-level security and production configuration review. Remaining production-environment decisions are tracked as deployment preparation work rather than unresolved application security defects.