# reQuest / request-api

PHP backend for the existing Records Request Management System, using the already-copied `request_db`. Existing request data is retained; the optional record-log table is initialized with the CLI migration described below.

This is an independent application and Git repository at `C:\VertrigoServ\www\request-api`, beside `request-web` and `rrms`. It does not include or load configuration from either sibling application.

This is the first separation phase. Authentication now has JSON endpoints for `request-web`; the existing administrative PHP screens and their request handlers remain here so their workflows can continue unchanged. This is not yet a complete conversion of every administrative screen into a frontend application.

## Run locally

1. Copy `.env.example` to `.env` and enter the existing MySQL connection details. Keep `RRMS_DB_NAME=request_db`.
2. From this directory, run `node serve.mjs` (Node.js 22, also used by the frontend). This starts PHP on port 8016 with sessions in the ignored local `.runtime/sessions` folder, rather than relying on a global PHP session directory. A normal PHP host can use its own configured, writable session directory.
3. Run `request-web` on port 5178. For direct backend access, set `REQUEST_WEB_URL=http://127.0.0.1:5178`; when serving through the web proxy, `/` returns to its login screen.

A local `.env` has already been created for this workspace. It is ignored by Git. Database configuration comes from this file or the hosting environment; the backend does not use the original application's other database connection files.

## Authentication

- `GET /auth/session.php`: session information and a CSRF token.
- `POST /auth/login.php`: existing username/password authentication against `tblaccounts`; send URL-encoded `username`, `password`, and `csrf_token` with the session cookie.
- Successful sign-in retains the existing session keys and password verification/legacy hash-upgrade behavior.
- `administrator/`, the existing AJAX handlers, and `logout.php` retain their PHP workflows.

Operational PHP files were copied from the current working tree, including its uncommitted work. The shared header displays reQuest with a red Q. Unused database configuration files, backup PHP copies, and logs were excluded from the new application.

## Hosting and GitHub

Deploy to PHP hosting with MySQLi and PHP sessions, and configure the existing `request_db` connection. Configure `request-web`'s `REQUEST_API_ORIGIN` to this backend's HTTPS base URL, including a subdirectory if applicable. Do not include `/api` unless that is part of your actual PHP hosting path.

Keep `.env`, runtime profile photos, logs, and database exports out of Git. Reference data and account tables stay in MySQL; they are not bundled in this source. All database connection settings are read from `.env` or the host environment, with no embedded database username/password fallback. Normal page requests do not create or modify database schemas; the explicit record-log CLI migration creates only its dedicated table. Production should use PHP 8.2 or newer, MySQLi with mysqlnd, and a writable PHP session directory.

The web proxy exposes the working administrative routes and static assets. On the PHP host, disable directory listing and deny direct access to `.env`, `config.php`, and `db.php`. Apache rules are supplied in `.htaccess`; configure equivalent rules on other servers.

## GitHub and cPanel Git Version Control

1. Create a GitHub repository named `request-api` and add its URL as this application's `origin`. Commit the source; `.env`, `.runtime`, personal profile photos, and database dumps are ignored. No remote or cloud deployment is created automatically.
2. Later, use cPanel **Git Version Control** to clone that repository into a private directory such as `/home/CPANEL_USER/repositories/request-api`, outside the public document root. Configure SSH access first if GitHub is private.
3. Create a dedicated production application directory, for example `/home/CPANEL_USER/public_html/request-api`. Configure its API domain/document root as appropriate.
4. Create the production application directory's `.env` manually using `.env.example`. Supply the cPanel MySQL host, port, database user/password, and existing database name. cPanel may prefix database and user names with your account name. Use `REQUEST_WEB_URL=/` when returning through the web proxy, or the production frontend URL for direct API visits. Keep production errors in server logs rather than the browser.
5. In the private Git repository's own ignored `.env`, set only `REQUEST_DEPLOY_PATH=/home/CPANEL_USER/public_html/request-api`. PHP CLI and `rsync` must be available. If needed, select PHP CLI with the server environment variable `REQUEST_PHP_BIN`.
6. In cPanel, select **Update from Remote**, then **Deploy HEAD Commit**. The root `.cpanel.yml` runs `deploy/cpanel.sh`. This copies application code while preserving the production `.env`, profile uploads, and database. It performs no schema changes and uses no deletion option.
7. Set `REQUEST_API_ORIGIN=https://YOUR_API_DOMAIN` in the Vercel project's server-side Environment Variables. Include the deployment subdirectory only if it is part of the public API URL.

The deployment script rejects the account home, the bare `public_html` directory, paths outside the account, and paths overlapping the Git repository. Use a dedicated application directory. Because deployment preserves existing files, removals from a future release require a separate reviewed cleanup.

The deployment sets copied public directories to `0755` and files to `0644`. The private cPanel clone may be `0700`; copying that mode onto the public API directory prevents Apache from reading `.htaccess`. A parent website's fallback rewrite can then return its home page for API requests. Keep the private clone private; correct only the public application's directory permissions. The production `.env` is separate from the clone's `.env` and is deliberately not copied during deployment.

For an existing installation with this problem, set the public API directory to `0755` in cPanel File Manager, complete its own `.env`, and verify `auth/session.php` returns JSON. No subdomain is required when the application is served at `https://YOUR_DOMAIN/request-api`. The frontend's `npm run health -- --api https://YOUR_DOMAIN/request-api --web https://YOUR_FRONTEND` checks the direct API and proxy without logging in or modifying records.

References: [cPanel Git Version Control](https://docs.cpanel.net/cpanel/files/git-version-control/), [cPanel pull deployment](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-set-up-deployment/).

## Office logo

The supplied Student Records and Admission Office emblem is bundled unchanged as `assets/img/request-logo.png`. Administrative page favicons, the shared header, and the printed report use this emblem. The shared reQuest wordmark uses Paola with an orange final e; the font attribution and existing usage notice are included in `assets/fonts/PAOLA-NOTICE.md`.

## Workspace appearance

`assets/css/admin-theme.css` shares the login's green, cream, and orange branding across the administrative pages while retaining the original Open Sans, Nunito, and Poppins typography. Hover effects apply to actionable cards; informational cards remain static. The New Request card opens the pending list; the active list's card uses a light sage highlight. The green Create Request button beside the list heading opens a dialog with a pale header, square form groups, a scrolling body, and visible actions. Small screens use the full-screen dialog layout.

`administrator/footer.php` renders the shared footer in a fixed bottom row, with copyright on the left and credits on the right. Small screens show compact copyright and management credits in the same row. Whole-word SAM references in footer configuration display as the Paola eoa wordmark with an orange e; configuration remains unchanged in the database. Footer configuration is escaped before inserting the trusted wordmark.

Run `node --test tests/request-ui.test.mjs` to check validation, duplicate-submit protection, and modal behavior after successful, rejected, and interrupted saves. These tests use isolated fixtures and never submit a real request.

## Pending and overdue requests

`administrator/manage_request.php` shows unreleased requests that are due today or later, or have no due date. `administrator/overdue_requests.php` shows only unreleased requests with a real due date before today, using the configured application timezone. Both pages share the same table, dialogs, and processing handlers. Card counts refresh with the list after edits, releases, and document changes.

The Request column lists the document names in italic text instead of showing a count. Names are fetched in one batch for the visible page by `administrator/request_documents.php`, including the released summary. Status badges wrap to leave more room for names, and request actions use two buttons per row. Row details align at the top, with compact name/contact spacing. Run `php tests/request-documents.php` to check rendering/escaping and compare document maps with read-only database counts.

Pending, overdue, and released lists use server-side DataTables paging through the existing authenticated, CSRF-protected `query_request.php` endpoint (`request_table=1`). `administrator/request_table.php` limits responses to 10/25/50/100 rows, applies search and allowlisted sorting across the full list, and fetches documents/remarks only for the selected IDs. Card counts share a single scan of the request table. `assets/js/request-records.js` reuses the table on refresh, delays search requests briefly while typing, and displays results without artificial loading delays. This requires no new database tables, columns, or indexes.

Run `php tests/request-table.php` for read-only paging/search/sort/rendering checks and `node --test tests/*.test.mjs` for the table controller and existing save behavior.

## Student record movement logs

**Student Record Logs** in the sidebar opens `administrator/record_logs.php`. Search students from existing requests, select their latest encoded name/program details, and log a record out. The register follows the paper log: date out, student, course, encoded attendance year, logged out by, date returned, checked by, and remarks. A separate return deadline appears under Date Out. The source currently stores last year of attendance, not graduation year, so this is labelled explicitly.

Run `php deploy/migrate-record-logs.php` once in each environment to create the dedicated `tbl_record_logs` table. It leaves existing request tables and data intact. For cPanel, run this from the private repository with that environment's database configuration before using the new page; normal file deployment does not run migrations. Snapshots preserve log history if a request, account, or incharge name changes later.

Dates and actors are set by the backend using the configured timezone and authenticated account. Seven working days are counted starting the day after log-out, excluding Saturdays/Sundays (holidays are counted). A record remains outstanding on the deadline and becomes overdue the next day. A unique database key prevents simultaneous outstanding entries for the same encoded name and program; returned records can be logged out again. Return records store the current recorder separately from the selected checker, validated against `tblincharge`. Early and overdue returns are allowed. Student identity uses name/program because the existing request source has no separate student master identifier.

`query_record_logs.php` requires authentication and CSRF protection for saves. The register supports server-side searching, status filtering, pagination, and minute-by-minute status refresh. `request-web` must be restarted locally after proxy route changes.

Run `php tests/record-logs.php` to check deadlines, lookup, duplicate protection, and returns. Its write checks use a temporary table that shadows the real log table only within the test connection; existing student/request/log rows are preserved.

`php tests/record-logs-page.php` checks the server-rendered page using an isolated CLI session fixture. `node --test tests/record-logs-ui.test.mjs` checks student search races, escaped display, interrupted/duplicate saves, and overdue returns.

Run `php tests/request-status.php` to check date boundaries, released/missing-date cases, and the live lists/counts using read-only queries. The test does not create tables or change records.
