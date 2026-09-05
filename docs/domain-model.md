# Service Desk Domain Model

## English

### 1. Purpose

This document describes the current domain model, business rules, permissions, relationships, and core technical behavior of the Service Desk application.

The Service Desk provides a support ticket workflow for requesters, agents, and administrators.

The application includes ticket management, assignment, comments, change history, attachments, email notifications, demo data, and a REST API.

---

### 2. User Roles

The application contains three user roles:

#### Requester

A requester can:

- create support tickets;
- view their own tickets;
- comment on tickets they have access to;
- edit the title and description of their own tickets;
- upload and download attachments on accessible tickets;
- reopen their own resolved tickets.

#### Agent

An agent can:

- create support tickets;
- view all tickets;
- comment on tickets;
- edit ticket information;
- upload and download attachments;
- assign and unassign tickets;
- assign tickets to agents;
- change ticket priority;
- change ticket status.

#### Admin

An administrator has the same ticket management permissions as an agent.

The role is stored directly on the `users` table and represented in the application by a PHP backed enum.

Each user has exactly one role.

Supported roles:

- `requester`
- `agent`
- `admin`

---

### 3. Ticket Lifecycle

Supported ticket statuses:

- `new`
- `in_progress`
- `resolved`
- `closed`

Main workflow:

```text
NEW -> IN_PROGRESS -> RESOLVED -> CLOSED
```

A resolved ticket can be reopened:

```text
RESOLVED -> IN_PROGRESS
```

A closed ticket is considered final.

When a ticket becomes `resolved`, `resolved_at` is populated.

When a resolved ticket is reopened, `resolved_at` is cleared.

When a ticket becomes `closed`, `closed_at` is populated.

Ticket status transitions are handled by the application workflow layer.

---

### 4. Ticket Priorities

Supported priorities:

- `low`
- `medium`
- `high`
- `urgent`

The default priority is:

```text
medium
```

Statuses, priorities, and user roles are stored as strings in the database and represented by PHP backed enums in the application.

---

### 5. Domain Entities

#### User

Main fields:

- `id`
- `name`
- `email`
- `password`
- `role`
- `created_at`
- `updated_at`

A user can:

- create multiple tickets;
- be assigned multiple tickets when acting as an agent;
- create multiple comments;
- create multiple ticket history records;
- upload multiple ticket attachments.

---

#### Ticket

Main fields:

- `id`
- `created_by_id`
- `assigned_to_id`
- `title`
- `description`
- `status`
- `priority`
- `resolved_at`
- `closed_at`
- `created_at`
- `updated_at`

`created_by_id` identifies the user who created the ticket.

`assigned_to_id` identifies the agent responsible for the ticket and can be `null`.

A ticket can contain multiple:

- comments;
- history records;
- attachments.

---

#### Ticket Comment

Main fields:

- `id`
- `ticket_id`
- `user_id`
- `body`
- `created_at`
- `updated_at`

A ticket can contain multiple comments.

Each comment belongs to one ticket and one user.

Internal/private agent comments are not currently supported.

---

#### Ticket History

Main fields:

- `id`
- `ticket_id`
- `user_id`
- `action`
- `old_values`
- `new_values`
- `created_at`

`old_values` and `new_values` are stored as JSON.

The history records important ticket workflow changes, including:

- status changes;
- priority changes;
- assignee changes.

---

#### Ticket Attachment

Main fields:

- `id`
- `ticket_id`
- `user_id`
- `original_name`
- `path`
- `mime_type`
- `size`
- `created_at`
- `updated_at`

A ticket attachment represents a file uploaded to a ticket.

The attachment stores metadata about the uploaded file while the physical file is managed through Laravel filesystem storage.

An attachment belongs to:

- one ticket;
- one user who uploaded it.

A ticket can contain multiple attachments.

Supported file types:

- `jpg`
- `jpeg`
- `png`
- `pdf`
- `txt`
- `log`

Maximum file size:

```text
10 MB
```

Attachment access follows ticket authorization rules.

A user must have access to the associated ticket in order to download its attachments.

---

### 6. Relationships

The main relationships are:

- User `1:N` Ticket as creator
- User `1:N` Ticket as assignee
- Ticket `1:N` TicketComment
- User `1:N` TicketComment
- Ticket `1:N` TicketHistory
- User `1:N` TicketHistory
- Ticket `1:N` TicketAttachment
- User `1:N` TicketAttachment

Conceptually:

```text
User
 ├── creates ────────> Ticket
 ├── assigned to ────> Ticket
 ├── writes ─────────> TicketComment
 ├── performs ───────> TicketHistory
 └── uploads ────────> TicketAttachment

Ticket
 ├── has many ───────> TicketComment
 ├── has many ───────> TicketHistory
 └── has many ───────> TicketAttachment
```

---

### 7. Permissions

| Action | Requester | Agent | Admin |
|---|---|---|---|
| Create ticket | Yes | Yes | Yes |
| View own tickets | Yes | Yes | Yes |
| View all tickets | No | Yes | Yes |
| Comment on accessible ticket | Yes | Yes | Yes |
| Edit accessible ticket information | Own | Yes | Yes |
| Upload attachment | Accessible tickets | Yes | Yes |
| Download attachment | Accessible tickets | Yes | Yes |
| Assign ticket | No | Yes | Yes |
| Change priority | No | Yes | Yes |
| Change status | Limited | Yes | Yes |

A requester can perform the following status transition on their own resolved ticket:

```text
RESOLVED -> IN_PROGRESS
```

Agents and administrators can perform:

```text
NEW -> IN_PROGRESS
IN_PROGRESS -> RESOLVED
RESOLVED -> IN_PROGRESS
RESOLVED -> CLOSED
```

Authorization is enforced through Laravel policies and Form Request authorization.

The web interface and REST API use the same authorization rules.

---

### 8. Validation

Input validation is handled through Laravel Form Requests.

The application validates operations including:

- ticket creation;
- ticket updates;
- status changes;
- priority changes;
- ticket assignment;
- comments;
- attachments;
- ticket filtering.

Validation rules are shared between the web application and REST API where the same operation is performed.

REST API validation errors are returned as JSON with HTTP status `422`.

---

### 9. Ticket Workflow Services

Core ticket workflow behavior is separated from HTTP controllers.

The main application services are:

#### TicketWorkflowService

Handles workflow operations including:

- status changes;
- priority changes;
- assignment changes;
- ticket history creation;
- workflow-related notifications.

#### TicketStatusTransitionService

Defines and validates allowed ticket status transitions.

#### TicketNotificationService

Handles notification recipients for ticket events such as:

- comments;
- attachments.

This separation allows the web interface and REST API to reuse the same business logic.

---

### 10. Ticket History

Important workflow changes are recorded in `ticket_histories`.

Recorded actions include:

- `status_changed`
- `priority_changed`
- `assignee_changed`

History records contain:

- the affected ticket;
- the user who performed the action;
- the action type;
- previous values;
- new values;
- creation timestamp.

This provides an audit trail for the main ticket workflow.

---

### 11. Attachments

Attachments are associated with tickets and users.

File metadata is stored in the database.

Physical files are stored using Laravel filesystem storage.

Attachment uploads are validated by file type and size.

Attachment downloads require authorization to access the associated ticket.

When a ticket is physically deleted, its associated attachment database records are deleted through the `ON DELETE CASCADE` foreign key rule.

Deletion of the physical attachment file from filesystem storage is not currently part of the normal application workflow.

---

### 12. Email Notifications

The application sends email notifications for important ticket events.

Implemented notification types include:

- ticket created;
- ticket assigned;
- attachment added;
- comment added;
- priority changed;
- status changed.

Notifications use Laravel Notifications.

Email delivery is queued using Laravel's database queue.

The application avoids sending redundant notifications to the user who performed an action where applicable.

For local development, outgoing email can be tested using Mailtrap Sandbox.

Queue jobs are processed using:

```bash
./vendor/bin/sail artisan queue:work
```

---

### 13. REST API

The application provides an authenticated REST API for the core ticket workflow.

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

The REST API reuses the same:

- Form Requests;
- authorization policies;
- workflow services;
- domain models;
- business rules

as the web interface.

Attachments are currently handled through the web application and are not exposed through the REST API.

---

### 14. Demo Data

The application contains database factories and seeders for local development and demonstration.

Demo users are provided for each supported role:

- Requester
- Agent
- Admin

The seed data also creates example ticket-related data so the main workflow can be demonstrated without manually building the initial dataset.

Demo data is disabled by default.

To create a fresh demo database, explicitly enable demo seeding in `.env`:

```env
DEMO_DATA_ENABLED=true
```

Then run:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

Demo data uses known demonstration credentials and must not be enabled in a real production environment.

---

### 15. Database Indexes

The ticket-related schema includes indexes intended to support common application queries.

Documented indexes include:

- `tickets(created_by_id)`
- `tickets(assigned_to_id)`
- `tickets(status, assigned_to_id)`
- `tickets(created_at)`
- `ticket_comments(ticket_id, created_at)`
- `ticket_histories(ticket_id, created_at)`
- `ticket_attachments(ticket_id, created_at)`

The foreign key columns also receive the indexes required by the database for their constraints.

Indexes may be adjusted based on application queries and execution plans as the project evolves.

---

### 16. Deletion Rules

Deleting a user must not automatically delete their historical tickets.

Core deletion behavior:

- `tickets.created_by_id -> ON DELETE RESTRICT`
- `tickets.assigned_to_id -> ON DELETE SET NULL`
- `ticket_comments.ticket_id -> ON DELETE CASCADE`
- `ticket_comments.user_id -> ON DELETE RESTRICT`
- `ticket_histories.ticket_id -> ON DELETE CASCADE`
- `ticket_histories.user_id -> ON DELETE SET NULL`
- `ticket_attachments.ticket_id -> ON DELETE CASCADE`
- `ticket_attachments.user_id -> ON DELETE RESTRICT`

Tickets are not physically deleted through the normal application workflow.

If a ticket is physically removed administratively, its dependent database records are removed according to the configured foreign key rules.

Deletion of the physical attachment file from filesystem storage is not currently part of the normal application workflow.

---

### 17. Current Exclusions

The following features are not currently implemented:

- ticket categories;
- SLA management;
- teams and departments;
- watchers;
- tags;
- internal agent notes;
- custom fields;
- advanced full-text search;
- advanced support queues.

These features can be introduced in future versions if required.

---

# Service Desk domeno modelis

## Lietuvių kalba

### 1. Paskirtis

Šiame dokumente aprašomas dabartinis „Service Desk“ sistemos domeno modelis, verslo taisyklės, prieigos teisės, ryšiai ir pagrindinė techninė elgsena.

„Service Desk“ sistema suteikia pagalbos užklausų valdymo procesą naudotojams, specialistams ir administratoriams.

Sistemoje įgyvendintas užklausų valdymas, priskyrimas specialistams, komentarai, pakeitimų istorija, failų prisegimas, el. pašto pranešimai, demonstraciniai duomenys ir REST API.

---

### 2. Naudotojų rolės

Sistemoje yra trys naudotojų rolės:

#### Requester

Naudotojas gali:

- kurti pagalbos užklausas;
- matyti savo užklausas;
- komentuoti užklausas, prie kurių turi prieigą;
- redaguoti savo užklausų pavadinimą ir aprašymą;
- įkelti ir atsisiųsti prieinamų užklausų failus;
- iš naujo atidaryti savo išspręstas užklausas.

#### Agent

Specialistas gali:

- kurti pagalbos užklausas;
- matyti visas užklausas;
- komentuoti užklausas;
- redaguoti užklausų informaciją;
- įkelti ir atsisiųsti failus;
- priskirti užklausas specialistams;
- panaikinti specialisto priskyrimą;
- keisti užklausos prioritetą;
- keisti užklausos būseną.

#### Admin

Administratorius turi tokias pačias užklausų valdymo teises kaip specialistas.

Rolė saugoma tiesiogiai `users` lentelėje ir programoje atvaizduojama naudojant PHP backed enum.

Kiekvienas naudotojas turi vieną rolę.

Galimos rolės:

- `requester`
- `agent`
- `admin`

---

### 3. Užklausos gyvavimo ciklas

Galimos užklausos būsenos:

- `new`
- `in_progress`
- `resolved`
- `closed`

Pagrindinis procesas:

```text
NEW -> IN_PROGRESS -> RESOLVED -> CLOSED
```

Išspręsta užklausa gali būti atidaryta iš naujo:

```text
RESOLVED -> IN_PROGRESS
```

Uždaryta užklausa laikoma galutine.

Kai užklausa tampa `resolved`, nustatoma `resolved_at` reikšmė.

Kai išspręsta užklausa atidaroma iš naujo, `resolved_at` reikšmė išvaloma.

Kai užklausa tampa `closed`, nustatoma `closed_at` reikšmė.

Būsenų perėjimai valdomi programos workflow sluoksnyje.

---

### 4. Užklausų prioritetai

Galimi prioritetai:

- `low`
- `medium`
- `high`
- `urgent`

Numatytasis prioritetas:

```text
medium
```

Būsenos, prioritetai ir naudotojų rolės duomenų bazėje saugomos kaip tekstinės reikšmės, o programoje atvaizduojamos naudojant PHP backed enum.

---

### 5. Domeno esybės

#### User

Pagrindiniai laukai:

- `id`
- `name`
- `email`
- `password`
- `role`
- `created_at`
- `updated_at`

Naudotojas gali:

- sukurti daug užklausų;
- būti atsakingas už daug užklausų, kai naudotojas yra specialistas;
- sukurti daug komentarų;
- sukurti daug užklausų istorijos įrašų;
- įkelti daug užklausų failų.

---

#### Ticket

Pagrindiniai laukai:

- `id`
- `created_by_id`
- `assigned_to_id`
- `title`
- `description`
- `status`
- `priority`
- `resolved_at`
- `closed_at`
- `created_at`
- `updated_at`

`created_by_id` nurodo užklausą sukūrusį naudotoją.

`assigned_to_id` nurodo už užklausą atsakingą specialistą ir gali būti `null`.

Viena užklausa gali turėti daug:

- komentarų;
- istorijos įrašų;
- prisegtų failų.

---

#### Ticket Comment

Pagrindiniai laukai:

- `id`
- `ticket_id`
- `user_id`
- `body`
- `created_at`
- `updated_at`

Viena užklausa gali turėti daug komentarų.

Kiekvienas komentaras priklauso vienai užklausai ir vienam naudotojui.

Vidiniai ir privatūs specialistų komentarai šiuo metu nepalaikomi.

---

#### Ticket History

Pagrindiniai laukai:

- `id`
- `ticket_id`
- `user_id`
- `action`
- `old_values`
- `new_values`
- `created_at`

`old_values` ir `new_values` saugomi JSON formatu.

Istorijoje registruojami svarbūs užklausos pakeitimai:

- būsenos pakeitimai;
- prioriteto pakeitimai;
- atsakingo specialisto pakeitimai.

---

#### Ticket Attachment

Pagrindiniai laukai:

- `id`
- `ticket_id`
- `user_id`
- `original_name`
- `path`
- `mime_type`
- `size`
- `created_at`
- `updated_at`

Prisegtas failas yra su konkrečia užklausa susietas naudotojo įkeltas failas.

Duomenų bazėje saugoma įkelto failo metainformacija, o fizinis failas valdomas naudojant Laravel failų saugojimo sistemą.

Prisegtas failas priklauso:

- vienai užklausai;
- vienam failą įkėlusiam naudotojui.

Viena užklausa gali turėti daug prisegtų failų.

Palaikomi failų tipai:

- `jpg`
- `jpeg`
- `png`
- `pdf`
- `txt`
- `log`

Didžiausias failo dydis:

```text
10 MB
```

Prieigai prie prisegtų failų taikomos užklausos autorizacijos taisyklės.

Naudotojas gali atsisiųsti failą tik tada, kai turi prieigą prie susijusios užklausos.

---

### 6. Ryšiai

Pagrindiniai ryšiai:

- User `1:N` Ticket kaip kūrėjas
- User `1:N` Ticket kaip atsakingas specialistas
- Ticket `1:N` TicketComment
- User `1:N` TicketComment
- Ticket `1:N` TicketHistory
- User `1:N` TicketHistory
- Ticket `1:N` TicketAttachment
- User `1:N` TicketAttachment

Konceptualiai:

```text
User
 ├── sukuria ─────────> Ticket
 ├── priskiriamas ─────> Ticket
 ├── rašo ─────────────> TicketComment
 ├── atlieka ──────────> TicketHistory
 └── įkelia ───────────> TicketAttachment

Ticket
 ├── turi daug ────────> TicketComment
 ├── turi daug ────────> TicketHistory
 └── turi daug ────────> TicketAttachment
```

---

### 7. Prieigos teisės

| Veiksmas | Requester | Agent | Admin |
|---|---|---|---|
| Sukurti užklausą | Taip | Taip | Taip |
| Matyti savo užklausas | Taip | Taip | Taip |
| Matyti visas užklausas | Ne | Taip | Taip |
| Komentuoti pasiekiamą užklausą | Taip | Taip | Taip |
| Redaguoti užklausos informaciją | Savo | Taip | Taip |
| Įkelti failą | Pasiekiamos užklausos | Taip | Taip |
| Atsisiųsti failą | Pasiekiamos užklausos | Taip | Taip |
| Priskirti užklausą | Ne | Taip | Taip |
| Keisti prioritetą | Ne | Taip | Taip |
| Keisti būseną | Ribotai | Taip | Taip |

Requester savo išspręstai užklausai gali atlikti šį būsenos pakeitimą:

```text
RESOLVED -> IN_PROGRESS
```

Agent ir Admin gali atlikti:

```text
NEW -> IN_PROGRESS
IN_PROGRESS -> RESOLVED
RESOLVED -> IN_PROGRESS
RESOLVED -> CLOSED
```

Autorizacija vykdoma naudojant Laravel policies ir Form Request autorizaciją.

Web sąsaja ir REST API naudoja tas pačias autorizacijos taisykles.

---

### 8. Validacija

Įvesties duomenų validacija vykdoma naudojant Laravel Form Requests.

Sistema validuoja:

- užklausų kūrimą;
- užklausų redagavimą;
- būsenų pakeitimus;
- prioritetų pakeitimus;
- užklausų priskyrimą;
- komentarus;
- failų įkėlimą;
- užklausų filtravimą.

Kai web sąsajoje ir REST API atliekama ta pati operacija, naudojamos tos pačios validacijos taisyklės.

REST API validacijos klaidos grąžinamos JSON formatu su HTTP `422` statusu.

---

### 9. Užklausų workflow servisai

Pagrindinė užklausų workflow logika atskirta nuo HTTP kontrolerių.

Pagrindiniai servisai:

#### TicketWorkflowService

Valdo:

- būsenų pakeitimus;
- prioritetų pakeitimus;
- priskyrimo pakeitimus;
- užklausos istorijos kūrimą;
- su workflow susijusius pranešimus.

#### TicketStatusTransitionService

Apibrėžia ir tikrina leidžiamus užklausos būsenų perėjimus.

#### TicketNotificationService

Valdo pranešimų gavėjus tokiems įvykiams kaip:

- komentarų pridėjimas;
- failų pridėjimas.

Toks atskyrimas leidžia web sąsajai ir REST API naudoti tą pačią verslo logiką.

---

### 10. Užklausos istorija

Svarbūs workflow pakeitimai registruojami `ticket_histories` lentelėje.

Registruojami veiksmai:

- `status_changed`
- `priority_changed`
- `assignee_changed`

Istorijos įraše saugoma:

- susijusi užklausa;
- veiksmą atlikęs naudotojas;
- veiksmo tipas;
- ankstesnės reikšmės;
- naujos reikšmės;
- sukūrimo laikas.

Tai suteikia pagrindinio užklausos proceso auditavimo istoriją.

---

### 11. Prisegti failai

Prisegti failai susieti su užklausomis ir naudotojais.

Failo metainformacija saugoma duomenų bazėje.

Fiziniai failai saugomi naudojant Laravel filesystem.

Įkeliamiems failams taikoma tipo ir dydžio validacija.

Failą galima atsisiųsti tik turint prieigą prie susijusios užklausos.

Kai užklausa fiziškai pašalinama, susiję prisegtų failų duomenų bazės įrašai pašalinami naudojant `ON DELETE CASCADE` išorinio rakto taisyklę.

Fizinių prisegtų failų pašalinimas iš failų saugyklos šiuo metu nėra įprasto sistemos workflow dalis.

---

### 12. El. pašto pranešimai

Sistema siunčia el. pašto pranešimus apie svarbius užklausos įvykius.

Įgyvendinti pranešimų tipai:

- sukurta užklausa;
- užklausa priskirta specialistui;
- pridėtas failas;
- pridėtas komentaras;
- pakeistas prioritetas;
- pakeista būsena.

Pranešimams naudojamas Laravel Notifications mechanizmas.

El. laiškų siuntimas vykdomas asinchroniškai naudojant Laravel database queue.

Kai tai prasminga, sistema nesiunčia nereikalingo pranešimo tam pačiam naudotojui, kuris atliko veiksmą.

Lokaliame kūrimo procese el. laiškams tikrinti galima naudoti Mailtrap Sandbox.

Queue užduotys vykdomos komanda:

```bash
./vendor/bin/sail artisan queue:work
```

---

### 13. REST API

Sistema turi autentifikuotą REST API pagrindiniam užklausų valdymo procesui.

Galimi endpoint'ai:

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

REST API naudoja tas pačias:

- Form Requests;
- autorizacijos policies;
- workflow servisus;
- domeno modelius;
- verslo taisykles

kaip ir web sąsaja.

Prisegti failai šiuo metu valdomi per web sąsają ir nėra pateikiami per REST API.

---

### 14. Demonstraciniai duomenys

Sistema turi duomenų bazės factories ir seeders lokaliam kūrimui ir demonstravimui.

Sukuriami demonstraciniai naudotojai kiekvienai palaikomai rolei:

- Requester
- Agent
- Admin

Seeder taip pat sukuria pavyzdinius užklausų duomenis, kad pagrindinį workflow būtų galima demonstruoti be rankinio pradinių duomenų kūrimo.

Pagal numatytuosius nustatymus demonstracinių duomenų kūrimas yra išjungtas.

Norint sukurti naują demonstracinę duomenų bazę, `.env` faile reikia aiškiai įjungti demonstracinių duomenų kūrimą:

```env
DEMO_DATA_ENABLED=true
```

Tada paleisti:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

Demonstraciniai duomenys naudoja viešai žinomus demonstracinių paskyrų prisijungimo duomenis, todėl ši funkcija neturi būti įjungta realioje produkcinėje aplinkoje.

---

### 15. Duomenų bazės indeksai

Su užklausomis susijusioje duomenų bazės struktūroje naudojami indeksai dažniausioms sistemos užklausoms.

Dokumentuoti indeksai:

- `tickets(created_by_id)`
- `tickets(assigned_to_id)`
- `tickets(status, assigned_to_id)`
- `tickets(created_at)`
- `ticket_comments(ticket_id, created_at)`
- `ticket_histories(ticket_id, created_at)`
- `ticket_attachments(ticket_id, created_at)`

Išorinių raktų stulpeliams taip pat naudojami duomenų bazės apribojimams reikalingi indeksai.

Projektui vystantis indeksai gali būti koreguojami pagal realias sistemos užklausas ir jų vykdymo planus.

---

### 16. Duomenų šalinimo taisyklės

Naudotojo pašalinimas neturi automatiškai pašalinti jo istorinių užklausų.

Pagrindinės šalinimo taisyklės:

- `tickets.created_by_id -> ON DELETE RESTRICT`
- `tickets.assigned_to_id -> ON DELETE SET NULL`
- `ticket_comments.ticket_id -> ON DELETE CASCADE`
- `ticket_comments.user_id -> ON DELETE RESTRICT`
- `ticket_histories.ticket_id -> ON DELETE CASCADE`
- `ticket_histories.user_id -> ON DELETE SET NULL`
- `ticket_attachments.ticket_id -> ON DELETE CASCADE`
- `ticket_attachments.user_id -> ON DELETE RESTRICT`

Įprastame sistemos workflow fizinis užklausų šalinimas nenumatytas.

Jeigu užklausa administraciniu būdu fiziškai pašalinama, priklausomi duomenų bazės įrašai pašalinami pagal nustatytas išorinių raktų taisykles.

Fizinių prisegtų failų pašalinimas iš failų saugyklos šiuo metu nėra įprasto sistemos workflow dalis.

---

### 17. Šiuo metu neįgyvendintas funkcionalumas

Šiuo metu neįgyvendinta:

- užklausų kategorijos;
- SLA valdymas;
- komandos ir padaliniai;
- stebėtojai;
- žymos;
- vidinės specialistų pastabos;
- pasirinktiniai laukai;
- išplėstinė full-text paieška;
- išplėstinės pagalbos užklausų eilės.

Šis funkcionalumas gali būti įgyvendintas būsimose sistemos versijose, jei atsiras poreikis.