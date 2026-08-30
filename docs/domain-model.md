# Service Desk Domain Model

## English

### 1. Purpose

This document defines the initial domain model and database design for the Service Desk MVP.

The goal is to establish the core entities, relationships, business rules, permissions, and ticket lifecycle before implementing the database schema and application logic.

---

### 2. User Roles

The MVP contains three user roles:

#### Requester

A requester can:

- create support tickets;
- view their own tickets;
- comment on tickets they have access to;
- edit the title and description of their own tickets;
- reopen a resolved ticket.

#### Agent

An agent can:

- create support tickets;
- view all tickets;
- comment on tickets;
- edit ticket information;
- assign tickets to agents;
- change ticket priority;
- change ticket status.

#### Admin

An administrator has all agent permissions.

User and role management may be extended in future versions.

For the MVP, the role is stored directly on the `users` table and represented in the application by a PHP backed enum.

Each user has exactly one role in the MVP. Roles represent increasing access levels:

`requester -> agent -> admin`

An agent can perform requester actions, and an administrator can perform both requester and agent actions.

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

`NEW -> IN_PROGRESS -> RESOLVED -> CLOSED`

A resolved ticket can be reopened:

`RESOLVED -> IN_PROGRESS`

A closed ticket is considered final in the MVP.

When a ticket becomes `resolved`, `resolved_at` is populated.

When a resolved ticket is reopened, `resolved_at` is cleared.

When a ticket becomes `closed`, `closed_at` is populated.

---

### 4. Ticket Priorities

Supported priorities:

- `low`
- `medium`
- `high`
- `urgent`

The default priority is `medium`.

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

A user can create multiple tickets.

An agent can be assigned to multiple tickets.

A user can create multiple comments and ticket history records.

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

`assigned_to_id` identifies the agent responsible for the ticket and can be null.

#### Ticket Comment

Main fields:

- `id`
- `ticket_id`
- `user_id`
- `body`
- `created_at`
- `updated_at`

A ticket can contain multiple comments.

Each comment belongs to one user.

Internal/private agent comments are outside the MVP scope.

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

The history records important ticket changes, including:

- status changes;
- priority changes;
- assignee changes.

---

### 6. Relationships

The main relationships are:

- User `1:N` Ticket as creator
- User `1:N` Ticket as assignee
- Ticket `1:N` TicketComment
- User `1:N` TicketComment
- Ticket `1:N` TicketHistory
- User `1:N` TicketHistory

---

### 7. Permissions

| Action | Requester | Agent | Admin |
|---|---|---|---|
| Create ticket | Yes | Yes | Yes |
| View own tickets | Yes | Yes | Yes |
| View all tickets | No | Yes | Yes |
| Comment on accessible ticket | Yes | Yes | Yes |
| Edit own ticket title/description | Yes | Yes | Yes |
| Assign ticket | No | Yes | Yes |
| Change priority | No | Yes | Yes |
| Change status | Limited | Yes | Yes |

A requester can perform the following status transition:

`RESOLVED -> IN_PROGRESS`

Agents and administrators can perform:

- `NEW -> IN_PROGRESS`
- `IN_PROGRESS -> RESOLVED`
- `RESOLVED -> IN_PROGRESS`
- `RESOLVED -> CLOSED`

---

### 8. Database Indexes

Initial indexes:

- `tickets(created_by_id)`
- `tickets(assigned_to_id)`
- `tickets(status, assigned_to_id)`
- `tickets(created_at)`
- `ticket_comments(ticket_id, created_at)`
- `ticket_histories(ticket_id, created_at)`

Indexes may be adjusted later based on real application queries and query execution plans.

---

### 9. Deletion Rules

Deleting a user must not automatically delete their historical tickets.

Foreign key deletion rules:

- `tickets.created_by_id -> ON DELETE RESTRICT`
- `tickets.assigned_to_id -> ON DELETE SET NULL`
- `ticket_comments.ticket_id -> ON DELETE CASCADE`
- `ticket_comments.user_id -> ON DELETE RESTRICT`
- `ticket_histories.ticket_id -> ON DELETE CASCADE`
- `ticket_histories.user_id -> ON DELETE SET NULL`

Tickets are not physically deleted as part of the MVP functionality.

If a ticket is physically removed administratively, its comments and history records are deleted with it.

---

### 10. MVP Exclusions

The following features are intentionally excluded from the initial MVP:

- attachments;
- ticket categories;
- SLA management;
- email notifications;
- teams and departments;
- watchers;
- tags;
- internal agent notes;
- custom fields;
- advanced search and queues.

These features can be introduced later when required.

---

# Service Desk domeno modelis

## Lietuvių kalba

### 1. Paskirtis

Šiame dokumente apibrėžiamas pradinis „Service Desk“ MVP domeno modelis ir duomenų bazės projektas.

Tikslas – prieš pradedant duomenų bazės struktūros ir programos logikos įgyvendinimą apibrėžti pagrindines esybes, jų ryšius, verslo taisykles, prieigos teises ir užklausos gyvavimo ciklą.

---

### 2. Naudotojų rolės

MVP numatytos trys naudotojų rolės:

#### Requester

Naudotojas gali:

- kurti pagalbos užklausas;
- matyti savo užklausas;
- komentuoti užklausas, prie kurių turi prieigą;
- redaguoti savo užklausų pavadinimą ir aprašymą;
- iš naujo atidaryti išspręstą užklausą.

#### Agent

Specialistas gali:

- kurti pagalbos užklausas;
- matyti visas užklausas;
- komentuoti užklausas;
- redaguoti užklausų informaciją;
- priskirti užklausas specialistams;
- keisti užklausos prioritetą;
- keisti užklausos būseną.

#### Admin

Administratorius turi visas specialisto teises.

Naudotojų ir rolių valdymas gali būti išplėstas būsimose sistemos versijose.

MVP versijoje rolė saugoma tiesiogiai `users` lentelėje ir programoje atvaizduojama naudojant PHP backed enum.

MVP versijoje kiekvienas naudotojas turi vieną rolę. Rolės reiškia didėjančius prieigos lygius:

`requester -> agent -> admin`

Agent gali atlikti requester veiksmus, o admin gali atlikti tiek requester, tiek agent veiksmus.

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

`NEW -> IN_PROGRESS -> RESOLVED -> CLOSED`

Išspręsta užklausa gali būti atidaryta iš naujo:

`RESOLVED -> IN_PROGRESS`

MVP versijoje uždaryta (`closed`) užklausa laikoma galutine.

Kai užklausa tampa `resolved`, nustatoma `resolved_at` reikšmė.

Kai išspręsta užklausa atidaroma iš naujo, `resolved_at` reikšmė išvaloma.

Kai užklausa tampa `closed`, nustatoma `closed_at` reikšmė.

---

### 4. Užklausų prioritetai

Galimi prioritetai:

- `low`
- `medium`
- `high`
- `urgent`

Numatytasis prioritetas yra `medium`.

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

Vienas naudotojas gali sukurti daug užklausų.

Vienam specialistui gali būti priskirta daug užklausų.

Naudotojas gali sukurti daug komentarų ir užklausos istorijos įrašų.

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

#### Ticket Comment

Pagrindiniai laukai:

- `id`
- `ticket_id`
- `user_id`
- `body`
- `created_at`
- `updated_at`

Viena užklausa gali turėti daug komentarų.

Kiekvienas komentaras priklauso vienam naudotojui.

Vidiniai ir privatūs specialistų komentarai nėra MVP dalis.

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

### 6. Ryšiai

Pagrindiniai ryšiai:

- User `1:N` Ticket kaip kūrėjas
- User `1:N` Ticket kaip atsakingas specialistas
- Ticket `1:N` TicketComment
- User `1:N` TicketComment
- Ticket `1:N` TicketHistory
- User `1:N` TicketHistory

---

### 7. Prieigos teisės

| Veiksmas | Requester | Agent | Admin |
|---|---|---|---|
| Sukurti užklausą | Taip | Taip | Taip |
| Matyti savo užklausas | Taip | Taip | Taip |
| Matyti visas užklausas | Ne | Taip | Taip |
| Komentuoti pasiekiamą užklausą | Taip | Taip | Taip |
| Redaguoti savo užklausos pavadinimą / aprašymą | Taip | Taip | Taip |
| Priskirti užklausą | Ne | Taip | Taip |
| Keisti prioritetą | Ne | Taip | Taip |
| Keisti būseną | Ribotai | Taip | Taip |

Requester gali atlikti šį būsenos pakeitimą:

`RESOLVED -> IN_PROGRESS`

Agent ir Admin gali atlikti:

- `NEW -> IN_PROGRESS`
- `IN_PROGRESS -> RESOLVED`
- `RESOLVED -> IN_PROGRESS`
- `RESOLVED -> CLOSED`

---

### 8. Duomenų bazės indeksai

Pradiniai indeksai:

- `tickets(created_by_id)`
- `tickets(assigned_to_id)`
- `tickets(status, assigned_to_id)`
- `tickets(created_at)`
- `ticket_comments(ticket_id, created_at)`
- `ticket_histories(ticket_id, created_at)`

Vėliau indeksai gali būti koreguojami pagal realias sistemos užklausas ir jų vykdymo planus.

---

### 9. Duomenų šalinimo taisyklės

Naudotojo pašalinimas neturi automatiškai pašalinti jo istorinių užklausų.

Išorinių raktų šalinimo taisyklės:

- `tickets.created_by_id -> ON DELETE RESTRICT`
- `tickets.assigned_to_id -> ON DELETE SET NULL`
- `ticket_comments.ticket_id -> ON DELETE CASCADE`
- `ticket_comments.user_id -> ON DELETE RESTRICT`
- `ticket_histories.ticket_id -> ON DELETE CASCADE`
- `ticket_histories.user_id -> ON DELETE SET NULL`

MVP funkcionalume fizinis užklausų šalinimas nenumatytas.

Jeigu užklausa administraciniu būdu fiziškai pašalinama, jos komentarai ir istorijos įrašai pašalinami kartu.

---

### 10. Į MVP neįtrauktas funkcionalumas

Į pradinį MVP sąmoningai neįtraukiama:

- failų prisegimas;
- užklausų kategorijos;
- SLA valdymas;
- el. pašto pranešimai;
- komandos ir padaliniai;
- stebėtojai;
- žymos;
- vidinės specialistų pastabos;
- pasirinktiniai laukai;
- išplėstinė paieška ir užklausų eilės.

Šis funkcionalumas gali būti įgyvendintas vėliau, atsiradus poreikiui.