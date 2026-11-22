<div align="center">
  <br>
  <h1>🧺 WS Clean & Laundry Serviços, Lda</h1>
  <p><strong>Laundry management system</strong> — counter, production, logistics, credit customers, inventory, HR, WhatsApp and PGC-NIRF accounting in one Laravel application.</p>
  <p>
    <a href="https://github.com/injaua/ws-laundry/actions/workflows/ci.yml"><img src="https://github.com/injaua/ws-laundry/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
    <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel" alt="Laravel 13">
    <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php" alt="PHP 8.4">
    <img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
    <img src="https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap" alt="Bootstrap 5">
    <img src="https://img.shields.io/badge/license-MIT-blue" alt="MIT License">
  </p>
  <br>
</div>

## 📋 Contents

- [Modules](#-modules)
- [Multiple stores](#-multiple-stores)
- [Roles](#-roles)
- [Documentation](#-documentation)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Default logins](#-default-logins)
- [Configuration](#-configuration)
- [Scheduled tasks](#-scheduled-tasks)
- [Development](#-development)
- [Architecture](#-architecture)
- [License](#-license)

---

## ✨ Modules

| Module | What it covers |
|--------|----------------|
| 📋 **Orders** | By-weight, by-piece and package lines priced from the catalogue (never from the browser), express surcharge, IVA, realistic promise dates, forward-only 8-step status flow, lock on completion, invoices, thermal receipts and barcode labels |
| 👥 **Customers** | Individual and business customers, negotiated prices and general discounts, loyalty points, WhatsApp opt-out, data export and anonymisation |
| 💳 **Payments & tills** | Cash, transfer, cheque, M-Pesa, e-Mola and mKesh with unique references; receipts; till shifts with opening float, withdrawals/deposits and blind-count close (shortage/surplus journaled) |
| 📱 **Mobile money** | M-Pesa and e-Mola push payments (C2B) and refunds (B2C) from the counter, the courier or a signed payment link; off / simulation / sandbox / live modes |
| 🛟 **After-sales** | Complaints with deadlines and resolutions, credit notes (refund or deduction), free re-wash orders, unclaimed items with reminders and disposal |
| 🏭 **Production** | Stage board, quality control, rework, delays and new promise dates, intake photos and care flags, piece-by-piece tracking with scan station, machine loads, daily capacity, production report |
| 🚚 **Logistics** | Home pickup and delivery, delivery zones with fees and free thresholds, courier route on the phone, cash handover at the counter, public pickup requests |
| 🧾 **Credit & invoicing** | Credit limits and terms, overdue blocking with justified override, receivables aging, monthly consolidated invoices, grouped payments, statements of account |
| 📦 **Resources** | Inventory items and lots (expiry), stock movements, consumption recipes per service / package / machine cycle, suppliers, purchase orders, equipment and maintenance |
| 👷 **HR** | Employees, attendance, absence justification, leave, vacations and vacation plan, payroll, staff self-service (payslips, attendance) |
| 📚 **Accounting (PGC-NIRF)** | Chart of accounts, automatic and manual journal entries, trial balance, balance sheet, income statement, IVA settlement, period close / reopen |
| 💰 **Finance & reports** | Expenses by category, treasury (cheques, bank deposits), wallet statement reconciliation, profit & loss, payment methods, management KPIs with targets, Excel / CSV exports |
| 📄 **Documents & exports** | A4 documents with PDF download: order invoices, receipts, grouped receipts, credit notes, consolidated invoices, statements of account, payslips, purchase orders and till reports. Trial balance, balance sheet, income statement, IVA settlement, journal and payroll summary in Excel or PDF |
| 💬 **Communication** | WhatsApp inbox inside the platform (official Cloud API or a normal number linked by QR code), automatic notifications with templates, internal staff chat |
| 🏬 **Multiple stores** | One company, several locations: each store has its own data and settings, store switcher for the admin, company overview with per-store figures and totals |
| ⚙️ **Administration** | Users, services, packages, promotions, business settings, company identity, languages and in-app translations, currencies, public website content |
| 🛡️ **Security** | Two-factor authentication per role, idle logout, session revocation, configuration review, scheduled backups, tamper-evident activity log |
| 🌐 **Public website** | Order status check (order number **plus the last 4 digits of the phone**, rate limited), online payment, pickup requests, gallery and testimonials |

## 🏬 Multiple stores

The admin runs one company with several locations (**Admin → Stores**):

- **Isolated data.** Every business record (customers, orders, payments, tills, stock, purchases, equipment, staff, payroll, accounting, WhatsApp messages…) carries a `store_id`. A global scope (`BelongsToStore` trait + `StoreScope`) filters every query by the current store and stamps new rows with it; a row can never be moved to another store. Records of another store answer **404**, and `exists` / `unique` validation rules only look inside the current store (`StorePresenceVerifier`).
- **Who works where.** Owners, accountants, cashiers and couriers belong to one store. Admins work in every store and pick the store in use from the top bar (`CurrentStore`). Each store has its owner, who creates the store's users.
- **New stores** are created by the admin only and receive a copy of the chart of accounts, expense categories, message templates and delivery zones (optionally the service / package catalogue) of a model store (`StoreProvisioner`). They start with no customers, orders or stock.
- **Settings.** Company identity (name, NUIT, logo), business settings, notifications and templates are per store, falling back to the company value. Localization, languages, currencies, the WhatsApp channel, mobile-money credentials, security and the public website are company-wide (admin only).
- **Numbering.** Orders, invoices, credit notes, complaints, machine loads and piece codes are numbered per store (unique per store), from per-store counters (`NumberSequence`).
- **Reports.** Every report shows the store in use. **Stores → Company overview** compares the stores for a period (orders, sales, received, refunds, expenses, cash result, receivables, late orders, customers, users) with the company total.
- **Outside requests.** Public pages run in the main store, or in the store of the signed payment link; the order status lookup searches every store by number + phone. Incoming WhatsApp messages go to the store that last talked to that number. Scheduled commands run store by store (`CurrentStore::each`) and queued jobs in the store of their record.

## 👤 Roles

| Role | Access |
|------|--------|
| **Admin** | Everything, in every store: stores and company overview, users, catalogue, system settings, security and website |
| **Owner** | Their store: management, reports, KPIs and targets, accounting, HR, resources, store identity and business settings; manages the store's cashiers, couriers and accountants |
| **Accountant** | Accounting (chart of accounts, journal entries, trial balance, balance sheet, income statement, VAT, period close), expenses, receivables, business invoicing, treasury, wallet reconciliation, payroll and financial reports; orders, customers, tills, credit notes, mobile payments, suppliers and purchases read-only. Reopening a closed period stays with admin / owner |
| **Cashier** | Front desk: customers, new orders, status updates, payments, their own till, production, logistics, complaints, WhatsApp inbox |
| **Courier** | Their pickup and delivery route only |

Access is enforced twice: role middleware on each route group, and policies (`app/Policies`) per record. For expenses, manual journal entries, payslips, consolidated invoices and credit notes, admin and owner manage every record of their store, while other roles change, delete, pay or void only the records they registered themselves (`OwnsStoreRecords`). Records without a known author are left to management.

## 📖 Documentation

**In the app.** The **Documentação** menu (sidebar, after the activity log, and the user menu) opens the user manual inside the platform, limited to what the signed-in role can do. Topics are Markdown files in `resources/help/pt`, mapped to roles in `app/Support/HelpCenter.php`.

**User manual.** The full user manual (Portuguese, v1.4) — every module, step-by-step tasks, permission matrix, multiple stores, technical reference, chart of accounts and automatic journal entries — is in [`docs/Manual_do_Utilizador_WS_Laundry.docx`](docs/Manual_do_Utilizador_WS_Laundry.docx).

For developers, the technical documentation (v1.6) in [`docs/Documentacao_Tecnica_WS_Laundry.docx`](docs/Documentacao_Tecnica_WS_Laundry.docx) covers the architecture, request pipeline, authorisation, multi-store data isolation, data model (every table), domain rules and where they live, accounting posting and period locks, WhatsApp / mobile-money integrations, queues and scheduler, front-end conventions, tests and step-by-step recipes (new module, new role, new notification).

---

## 🚀 Requirements

- **PHP** 8.4 (8.4.1 or later: the locked Symfony 8.1 components require it) with `bcmath`, `ctype`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `xml`, `zip`
- **Composer** 2.x and **Node.js** 20+ (front-end build and the WhatsApp QR service)
- **Database**: MySQL 8 / MariaDB, InnoDB only (tables are always created as InnoDB, whatever the server default). SQLite is not supported
- The **MySQL client** tools on the PATH: `mysql` loads `database/schema/mysql-schema.sql` when `migrate` runs on an empty database (install, tests), `mysqldump` makes the backups
- A web server pointing at `public/` (HTTPS in production), the scheduler every minute and a queue worker

> PHP 8.5 is not supported yet: the locked `phpoffice/phpspreadsheet` (used for Excel exports) requires PHP < 8.5.

## 🛠️ Installation

```bash
git clone https://github.com/injaua/ws-laundry.git
cd ws-laundry
composer install
npm ci && npm run build
cp .env.example .env    # then set DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE \`washly-lavandaria\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

For production, set `APP_ENV=production` and `APP_DEBUG=false` before seeding, then run `composer install --no-dev --optimize-autoloader` afterwards (the seeders use factories, which need the dev dependencies). Keep a queue worker running (`php artisan queue:work` under Supervisor, systemd or NSSM), or let the scheduler start one every minute (see Scheduled tasks). With a permanent worker you can set `MOBILE_MONEY_QUEUE=true`.

## 🔐 Default logins

| Role | Email |
|------|-------|
| Admin | `admin@ws-cleanlaundry.example` |
| Cashier | `cashier@ws-cleanlaundry.example` |
| Owner | `owner@ws-cleanlaundry.example` |

- With `APP_ENV=local` the password is `password`.
- In any other environment the seeder generates a **random password for each account and prints it once** in the console. Store it safely and change it after the first login.
- Accountants and couriers are created in **Users → New user**.

## ⚙️ Configuration

Most settings are made in the app (Admin → System settings, Business settings, WhatsApp, Mobile money, Security); the `.env` values are only the defaults.

| Variable | Purpose |
|----------|---------|
| `APP_URL` | The address users open (e.g. `https://laundry.example.com`). Pages show images from the host the browser is using, but links in e-mails, WhatsApp messages and queued jobs are built from this value; Admin → Security → configuration review flags a mismatch |
| `QUEUE_CONNECTION` | `database` (default): WhatsApp messages and account e-mails are sent by the queue worker |
| `MOBILE_MONEY_QUEUE` | `false` (default): M-Pesa / e-Mola requests go to the operator right after the page answers. `true`: through the queue, only with a permanent worker (Supervisor, systemd, NSSM); with the once-a-minute worker of the scheduler the customer would get the prompt up to a minute late |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Default language (`pt`) and fallback (`en`) |
| `DB_CONNECTION`, `DB_*` | `mysql` (or `mariadb`), database `washly-lavandaria` |
| `DB_ENGINE` | Storage engine for new tables, `InnoDB` by default (transactions, foreign keys and row locks depend on it) |
| `MYSQLDUMP_PATH` | `mysqldump` binary for backups, full path when it is not on the PATH (e.g. `C:/wamp64/bin/mysql/mysql8.4.7/bin/mysqldump.exe`) |
| `WHATSAPP_DRIVER` | Conversations: `log` (test), `cloud` (official API) or `web` (normal number linked by QR code) |
| `WHATSAPP_NOTIFICATIONS_DRIVER` | Automatic notifications: `cloud` or `log` (empty = same as conversations; off with `web`) |
| `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN` | Meta Cloud API credentials and webhook verification |
| `WHATSAPP_WEB_URL`, `WHATSAPP_WEB_TOKEN` | QR service address and shared secret (generated in `storage/app/whatsapp-web.token` when empty) |
| `WHATSAPP_WEB_AUTOSTART` | `true`: choosing "QR code" in the settings starts `whatsapp-web/` (npm install on first run); `false`: run it yourself |
| `WHATSAPP_WEB_NODE` | Path to `node` when the web server does not find it |

M-Pesa / e-Mola endpoints and field names are in `config/mobile_money.php`; credentials and mode (off / simulation / sandbox / live) are set in Admin → Mobile money.

## ⏰ Scheduled tasks

Run the scheduler every minute (`* * * * * php artisan schedule:run`):

On cPanel (no terminal): **Cron Jobs → Add New Cron Job**, "Once Per Minute", command
`/usr/local/bin/php /home/<user>/<app>/artisan schedule:run >> /dev/null 2>&1` (check the PHP path in cPanel → *MultiPHP*).
That is all the queue needs: the scheduler runs the worker below every minute.

| Command | When | Purpose |
|---------|------|---------|
| `queue:work --stop-when-empty` | Every minute | Sends the queued jobs (WhatsApp messages, account e-mails…) without Supervisor; harmless next to a permanent worker |
| `whatsapp:notify-scheduled` | Daily 09:00 | Credit due soon, overdue invoices, items not collected (each store) |
| `whatsapp:web-service` | Every minute | Start the QR service when it is the chosen channel and not running |
| `invoices:generate-monthly` | 1st of the month 07:00 | Draft consolidated invoices for monthly-billed customers (each store) |
| `mobile-money:refresh` | Every minute | Check pending M-Pesa / e-Mola requests |
| `backup:run` | Daily 01:30 | `mysqldump` of the database to `storage/app/private/backups/*.sql.gz` (consistent snapshot, old copies pruned) |
| `audit:verify` | Mondays 02:00 | Check that no audit-trail entry was changed or removed |

Maintenance: `accounting:sync-payments` and `accounting:sync-expenses` repost journal entries.

Restoring a backup (download it from Admin → Security, or take it from `storage/app/private/backups`):

```bash
gunzip < backup-YYYYMMDD-HHMMSS.sql.gz | mysql -u root -p washly-lavandaria
```

---

## 🧪 Development

```bash
composer dev            # server, queue, logs and Vite together
php artisan test --parallel   # full test suite, one process per CPU core (~2 min)
composer lint                 # code style (Pint, rules in pint.json) + static analysis (Larastan)
vendor/bin/pint               # fix code style
php artisan schema:snapshot   # after adding migrations: rebuild database/schema/mysql-schema.sql
npm run build                 # production front-end build
```

The tests run on MySQL like production, in their own database `washly-lavandaria-testing` (create it once, empty, like the main one; `--parallel` creates `washly-lavandaria-testing_test_1`, `_2`… by itself). It is emptied and rebuilt on every run, and the suite refuses to start on a database whose name does not end in `-testing`.

**Schema snapshot.** An empty database (tests, new installs) is built from `database/schema/mysql-schema.sql`: the final structure plus the rows the migrations insert (chart of accounts, currencies, languages, templates, main store), then only the migrations newer than the snapshot run. This takes seconds instead of ~90 s. Regenerate it with `php artisan schema:snapshot` after adding migrations (it rebuilds the scratch database `washly-lavandaria-testing`, never the one in use). Loading it needs the `mysql` client on the PATH.

**Static analysis.** Larastan runs at level 5 (`phpstan.neon`; `composer analyse`). Findings that predate it are listed in `phpstan-baseline.neon`: fix them over time and regenerate the baseline with `vendor/bin/phpstan analyse --generate-baseline`, never add new code to it.

The suite has 650+ tests (Laravel 13). CI (`.github/workflows/ci.yml`) runs on every push and pull request against a MySQL 8.4 service: code style (Pint, Prettier), static analysis and the tests in parallel.

## 🏗️ Architecture

| Layer | Location | Purpose |
|-------|----------|---------|
| Form requests | `app/Http/Requests` | Validation and request-level authorisation |
| Controllers | `app/Http/Controllers` | HTTP flow only |
| Actions | `app/Actions` | One business operation each (e.g. `CreateOrder`, `CloseCashSession`, `IssueCreditNote`) |
| Services | `app/Services` | Reusable domain logic (pricing, production planning, logistics, invoicing, WhatsApp, mobile money, security) |
| Abilities | `app/Support/Permissions.php` | Every ability (`manage-catalog`, `configure-store`…) and the roles that have it, registered as Gates: routes use `can:…`, views `@can` |
| Policies | `app/Policies` | Per-record authorisation |
| Routes | `routes/web.php`, `routes/web/*.php` | Public routes in `web.php`; signed-in routes split by area (sales, management, finance, operations, HR, messages, account, admin) |
| Settings | `app/Models/SiteSetting.php`, `app/Support/SettingsCache.php`, `app/Support/StoreSettings.php` | Company / store settings, read once per request; WhatsApp and payments configured per store |
| Request state | `app/Support/RequestCache.php`, `app/Http/Middleware/ApplySystemSettings.php` | Values computed once per request / queued job (current store, system settings, languages), in a scoped container instance instead of static properties; system settings applied once the user and their store are known |
| Store readiness | `app/Support/StoreReadiness.php` | Whether a store can take payments and talk to customers (dashboard, Admin → Stores) |
| Store scoping | `app/Support/CurrentStore.php`, `app/Models/Traits/BelongsToStore.php` | The store in use and the per-store filter on every business model |
| Money | `app/Support/Money.php` | Exact decimal arithmetic for every amount (sums, differences, VAT, discounts, comparisons) |
| Numbering | `app/Support/NumberSequence.php` | Per-store counters for every numbered series, incremented inside the saving transaction |
| Views | `resources/views` | Blade, per module, with partials |
| Front-end | `resources/js`, `resources/css` | Per-page scripts and styles built by Vite |
| Exports | `app/Exports`, `app/Support/Documents` | Excel workbooks, PDFs (dompdf) of the A4 documents, report tables delivered as Excel or PDF |
| WhatsApp QR service | `whatsapp-web/` | Node companion service (Baileys) for the QR-code connection |

Amounts are stored in `decimal(12,2)` columns (cents included). In PHP they are never added, subtracted or compared with the plain operators: `Money` works on exact decimals (brick/math) and rounds every result to the cent, half away from zero, so float noise can never produce a wrong cent, an "unbalanced" journal entry or a phantom balance.

Documents and reports export from the page itself: an A4 document (`layouts.document`) returns its PDF with `?format=pdf` (`DocumentResponse`, the same Blade view rendered by dompdf with `resources/css/pdf.css`), and a report page builds a `ReportTable` once and returns it as Excel or PDF with `?format=xlsx` / `?format=pdf` (`ReportDownload`), keeping the page's filters and permissions.

The back office opens pages without full reloads (`resources/js/lib/navigation.js`): a click on an internal link, or a GET form (filters, searches), fetches the page and swaps the `<body>` of `layouts.admin` (`<body data-nav-root>`); the address bar and back / forward work as usual. POST forms, documents, print-outs and exports (`…/document`, `…/print`, `…/export`, `?format=pdf|xlsx`), the language switch, links with `target` / `download`, pages of another layout and anything inside `data-no-nav` load normally. Page scripts run again on every visit, so whatever they leave outside their own elements ends with the page through `resources/js/lib/page.js`: `on(document, …)` instead of `document.addEventListener`, `every()` / `later()` instead of `setInterval` / `setTimeout`, `onLeave()` for cameras and similar, `reloadPage()` / `visit()` instead of `location.reload()` / `location.href`. Charts made with `lib/charts.js` are destroyed automatically.

Numbered series (`ORD…`, `FT-…`, `NC…`, `REC…`, `CG-…`) come from the `number_sequences` table: `NumberSequence::next()` increments the store's counter for the series with one atomic `UPDATE` inside the transaction that saves the record. Concurrent saves wait on that row lock instead of colliding, and a rolled-back save gives its number back, so fiscal series have no gaps. A new series starts after the highest number already in use.

---

## 📄 License

**MIT License.** Based on [afdhalpower/laravel-laundry](https://github.com/afdhalpower/laravel-laundry) — © 2026 afdhalpower.
