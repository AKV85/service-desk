# Service Desk

Service Desk is a Laravel-based support ticket management application built as a portfolio project.

The application provides a complete support ticket workflow with role-based access control, ticket assignment, comments, change history, attachments, queued email notifications, demo data, and a REST API.

## Features

- User authentication
- Role-based access control
- Requester, Agent, and Admin roles
- Ticket creation and editing
- Ticket status workflow
- Ticket priorities
- Ticket assignment and unassignment
- Ticket comments
- Ticket change history
- File attachments
- Ticket filtering and search
- Dashboard with ticket statistics
- Queued email notifications
- Mailtrap integration for local email testing
- Demo seed data
- REST API
- Automated feature and integration tests

## Technology Stack

- PHP 8.3+
- Laravel 13
- MySQL
- Laravel Sail
- Docker
- Laravel Fortify
- Eloquent ORM
- Laravel Notifications
- Database queue
- Mailtrap
- PHPUnit
- Faker
- Git / GitHub

## Architecture

The application follows a layered structure:

```text
Web / API Controllers
        ↓
Form Requests
        ↓
Policies
        ↓
Application Services
        ↓
Eloquent Models
        ↓
MySQL
```

Main responsibilities:

- Controllers handle HTTP requests and responses.
- Form Requests handle input validation and request authorization.
- Policies enforce role-based access control.
- Services contain ticket workflow and notification logic.
- Eloquent models represent domain entities and relationships.
- Laravel Notifications handle queued email delivery.
- The database queue processes asynchronous notification jobs.

Important services:

```text
TicketWorkflowService
TicketStatusTransitionService
TicketNotificationService
```

## User Roles

### Requester

A requester can:

- create tickets;
- view their own tickets;
- edit their own ticket title and description;
- comment on tickets they can access;
- reopen their own resolved tickets.

### Agent

An agent can:

- view all tickets;
- edit ticket information;
- assign tickets to agents;
- change ticket priority;
- change ticket status;
- comment on tickets.

### Admin

An administrator has the same ticket management permissions as an agent.

## Ticket Workflow

Supported statuses:

```text
new
in_progress
resolved
closed
```

Main workflow:

```text
NEW → IN_PROGRESS → RESOLVED → CLOSED
```

A resolved ticket can be reopened:

```text
RESOLVED → IN_PROGRESS
```

When a ticket becomes resolved, `resolved_at` is populated.

When a resolved ticket is reopened, `resolved_at` is cleared.

When a ticket becomes closed, `closed_at` is populated.

## Ticket Priorities

Supported priorities:

```text
low
medium
high
urgent
```

The default priority is:

```text
medium
```

## Ticket History

Important ticket changes are stored in the ticket history.

Currently recorded actions include:

- status changes;
- priority changes;
- assignee changes.

Old and new values are stored as JSON.

## Attachments

Users who can access a ticket can upload attachments.

Supported file types include:

```text
jpg
jpeg
png
pdf
txt
log
```

Maximum upload size:

```text
10 MB
```

Attachment metadata is stored in MySQL while files are stored using Laravel filesystem storage.

Attachment downloads are protected by ticket authorization rules.

## Email Notifications

The application sends queued email notifications for important ticket events.

Supported notifications include:

- ticket creation;
- ticket assignment;
- ticket status change;
- ticket priority change;
- new ticket comment;
- new ticket attachment.

The user who performs an action is not notified when the notification would be redundant.

Notifications are processed through Laravel's database queue.

## REST API

The Service Desk exposes an authenticated REST API for the core ticket workflow.

Available endpoints:

```text
GET    /api/tickets
POST   /api/tickets
GET    /api/tickets/{ticket}
PUT    /api/tickets/{ticket}
PATCH  /api/tickets/{ticket}/status
PATCH  /api/tickets/{ticket}/priority
PATCH  /api/tickets/{ticket}/assignee
POST   /api/tickets/{ticket}/comments
```

The API uses the same validation, authorization policies, workflow services, and business rules as the web interface.

Validation errors are returned as JSON with HTTP status `422`.

Unauthorized operations return the appropriate HTTP status.

## Installation

Clone the repository:

```bash
git clone <repository-url>
cd service-desk
```

Install PHP dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Start Laravel Sail:

```bash
./vendor/bin/sail up -d
```

Run migrations:

```bash
./vendor/bin/sail artisan migrate
```

To recreate the database with demo data:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

## Demo Accounts

All demo accounts use the password:

```text
password
```

Requester:

```text
requester@example.com
```

Agent:

```text
agent@example.com
```

Admin:

```text
admin@example.com
```

## Mailtrap Email Testing

The project uses Mailtrap Sandbox for local email testing.

Configure the following variables in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_FROM_ADDRESS=service-desk@example.com
MAIL_FROM_NAME="${APP_NAME}"

APP_URL=http://localhost
QUEUE_CONNECTION=database
```

Never commit real Mailtrap credentials.

Clear cached configuration after changing mail settings:

```bash
./vendor/bin/sail artisan config:clear
```

Run the queue worker:

```bash
./vendor/bin/sail artisan queue:work
```

Mailtrap Sandbox may rate-limit emails when several queued notifications are processed quickly.

For manual testing, jobs can be processed individually:

```bash
./vendor/bin/sail artisan queue:work --once
```

## Running Tests

Run the full automated test suite:

```bash
./vendor/bin/sail artisan test
```

Current test suite:

```text
157 tests
410 assertions
```

The suite covers:

- authentication;
- roles and authorization;
- ticket creation and updates;
- ticket workflow;
- assignment;
- comments;
- history;
- filtering;
- dashboard;
- attachments;
- notifications;
- demo seeders;
- REST API.

## Project Documentation

Detailed domain model documentation is available here:

```text
docs/domain-model.md
```

The document describes:

- domain entities;
- relationships;
- permissions;
- ticket lifecycle;
- database indexes;
- deletion rules.

## License

This project is built as a portfolio and learning project using the Laravel framework.