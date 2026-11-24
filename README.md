# WMSU University-Wide Student Council Funds Management System

React front end (Vite) + the same PHP + MySQL/MariaDB back end. The database name remains `student_council_funds`.

## Run it from the terminal (React)

Requirements: **Node.js 20.19+**, **PHP 8.1+** (with `pdo_mysql` and `gd`), and **MySQL/MariaDB** running (XAMPP's is fine).

```bash
npm install          # once: installs React, Vite and the React plugin
npm run dev          # development: PHP API on :8000 + React dev server on http://localhost:5173 (hot reload)
```

Production-style, everything on one port served by PHP:

```bash
npm run build        # compiles the React app into dist/
npm start            # http://127.0.0.1:8000  (builds first if dist/ is missing)
```

| Command | What it does |
| --- | --- |
| `npm run dev` | PHP back end + Vite dev server together (Ctrl+C stops both) |
| `npm run dev:web` / `npm run dev:api` | Only the React dev server / only the PHP back end |
| `npm run build` | Production build into `dist/` |
| `npm start` | Serves the built app and the API from PHP on one port |

Useful settings (environment variables): `API_PORT` (default 8000), `WEB_PORT` (default 5173), and `PHP_BIN` when PHP is not on your PATH, e.g. `PHP_BIN="C:\xampp\php\php.exe" npm run dev`.

First run: edit the database credentials in `config.php`, then open `http://127.0.0.1:8000/setup.php` once (delete it afterwards). The first database connection also runs the additive migration.

How it fits together:

- `src/` — the React app. Every old `*.php` page is one screen (hash routes such as `#/payments`, `#/crud/budget`).
- `api/` — a JSON API (`api/index.php?r=<route>`, one file per area in `api/routes/`). It reuses `includes/lib.php` and `includes/auth.php`, so the rules, SQL, roles, OTP e-mail, fee split and audit trail are unchanged.
- `router.php` + `scripts/` — the small Node launchers that start PHP's built-in server and Vite.
- The original `*.php` pages are still in the project and still work under XAMPP/Apache if you prefer them; Apache users can also open the built app at `/dist/`.

## Included in this update

- Student registration with separate name fields, college-first selection, dependent program list, and WMSU email OTP verification. Admin can maintain active colleges and programs in `catalog.php`; Admin can record annual enrollment snapshots in `enrollments.php`.
- Four account levels: Student, University Admin, College Mayor, and College Officer. A Mayor is assigned by the Admin to a registered student account from that same college.
- College Mayor assignment and history (`mayors.php`); Admin-only school-year enrollment management and historical snapshots (`enrollments.php`). The Admin can assign, reassign, or end a Mayor assignment. Ordinary officer assignments are managed by the College Mayor (`members.php`).
- College-scoped request workflow (`requests.php`): Officers submit Event, Project, Donation, or Funding requests; the Mayor of that college approves or declines them. The Admin can review all colleges.
- Annual fee payment records (`payments.php`): Insurance PHP 70 and CSC PHP 200 per school year. Students upload JPG, PNG, or PDF proof (maximum 5 MB); Officers and the Mayor verify the proof and enter a physical receipt number.
- Verified fee split: 70% college share and 30% university share. A separate distribution record is created, and the university share is posted once to the Admin-only central fund ledger (`university_ledger.php`) so the gross payment is not counted twice.
- University-wide announcements by Admin and college announcements by Mayor/Officer (`announcements.php`). Students have view-only access.
- College transaction history (`college_ledger.php`) with college/date/school-year filters for Admin, and college-only date/school-year view for Mayor/Officer.
- College-scoped financial CRUD for Mayor: budgets, fund sources, collections, projects, and expenses. Mayor queries and foreign-key dropdowns are restricted server-side to the assigned college. New projects must link to an approved request from the same college and school year; project allocations and expenses are checked against remaining balances.
- Audit events for important actions, viewable by Admin and the assigned Mayor (`audit.php`).
- Additive database migration. Existing tables and the database name are retained; transaction college/school-year snapshots are added for new and migrated records.

## Important limits before live financial use

This update adds the role foundation and the new workflows above, but it is **not yet a complete production finance system**:

- The older university-wide ledger (`ledger.php`) remains Admin-only. The new central-fund ledger (`university_ledger.php`) records 30% student-fee shares and Admin-entered central expenses. Mayor financial CRUD is college-scoped through `crud.php` and `college_ledger.php`.
- Approved requests can now be linked to projects. A later phase should add a dedicated project conversion workflow and stronger review/approval steps for budget changes and expenses.
- Existing old transaction college snapshots are backfilled from the student's current college where possible. If a student's college changed in the past, the database may not contain enough information to reconstruct the original college; review historical data before relying on it for official reporting.
- The initial college/program catalog must be confirmed against the current WMSU Registrar prospectus before real student registration.
- Email OTP only confirms access to the supplied mailbox. It is not a live Registrar enrollment check.
- Configure real SMTP before using email verification outside development mode.
- PHP syntax was checked. The migration and payment workflow still need to be tested against your actual XAMPP/MariaDB database before real use.

## Safe update steps

1. Export `student_council_funds` from phpMyAdmin (Export → Quick → SQL).
2. Copy your existing project folder as a backup.
3. Extract this ZIP and replace/merge the project folder. Keep your local `config.php` credentials if they differ.
4. Confirm `DB_NAME` is still `student_council_funds` in `config.php`.
5. Start Apache and MySQL in XAMPP.
6. Sign in and open the site. The next database connection runs the additive migration automatically.
7. Test first with test accounts for Student, Officer, Mayor, and Admin. Do not use real funds until the SQL migration and role restrictions have been checked.
8. Configure SMTP before relying on real email delivery. For Gmail, enable 2-Step Verification and create an App Password; do not use your regular Google password. Set `SMTP_USER`, `SMTP_PASS`, and optionally `MAIL_FROM` as environment variables available to Apache, then restart Apache. Keep the App Password out of `config.php` and source control. Gmail-managed accounts may have App Passwords disabled by their administrator.
9. Delete `setup.php` after initial setup, as instructed by the setup page.

## Main pages

- `signup.php`, `verify.php`, `login.php` — registration, email verification, login
- `dashboard.php`, `profile.php` — role-aware dashboard and own profile
- `mayors.php` — Admin-only Mayor assignment
- `members.php` — Admin directory; Mayor manages officers in own college
- `requests.php` — college request submission and Mayor decision
- `payments.php`, `payment-proof.php` — annual fees, proof upload, verification
- `announcements.php` — university and college notices
- `enrollments.php` — Admin school-year enrollment records and filters
- `catalog.php` — Admin-managed college and program options
- `university_ledger.php` — Admin central-fund history and outflows
- `college_ledger.php` — college history and filters
- `audit.php` — Admin/Mayor audit trail
- `crud.php` — Admin university CRUD and Mayor college-scoped budgets/sources/collections/projects/expenses
- `ledger.php` — Admin-only legacy university ledger


## University central fund

Verified annual student fee payments automatically post the 30% university share to the append-only `university_fund_ledger`. Admin can filter that ledger by date and school year and record central-fund outflows. The 70% college share remains in each college's financial history. The split is recorded separately so the gross fee is not counted twice.
