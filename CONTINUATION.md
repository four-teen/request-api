# reQuest continuation status

Verified on 8 November 2026 (Asia/Manila). This note contains no credentials or browser session URLs.

## Latest configuration update

Resolved on 8 November: changed only the public `/home/tnrmssks25/public_html/request-api` directory from `0700` to `0755` through cPanel File Manager and verified the saved mode. The user had already saved the production `.env` (267 bytes); its database username and password were confirmed present without displaying or copying them. No credential or database-record changes were made by the agent.

All four production health checks now pass: direct API session, proxied session, direct PNG, and proxied PNG. The session checks verify the expected JSON, database connectivity, and persistent CSRF tokens across requests using the PHP session cookie. HEAD checks confirm the production `.env`, `config.php`, and `db.php` still return 403; the proxy blocks `.env` with 404. Refreshed the live frontend and opened its empty sign-in form; the service-unavailable error is gone. Actual account sign-in has not been submitted.

The local deployment-script permission fix and health command remain uncommitted and unpushed. Publish the updated deployment script before another cPanel deployment; the old script's rsync archive permissions can restore `0700` on the public directory. The diagnostic paragraphs below describe the earlier failure state.

Latest diagnosis: cPanel now records deployment at 08:25:49 on 8 November, and PHP files exist in the production directory. Its `.env` still shows zero bytes. The private repository editor now contains database username and password values (only their presence was inspected; values were not displayed or copied). These are separate configurations.

The public `request-api` directory is `0700`. cPanel's web server error log reports `Failed to open .../request-api/.htaccess: Permission denied`. The main domain's document root is correctly `/home/tnrmssks25/public_html`; its parent `.htaccess` routes missing/inaccessible paths to the university home page. Both direct session/logo requests and the Vercel proxy return HTTP 200 university HTML. DNS and HTTPS checks succeed. A subdomain is optional and does not resolve the empty runtime configuration or directory permissions.

Prepared local fixes: `deploy/cpanel.sh` now normalizes copied public directories to `0755` and files to `0644`, preventing rsync archive mode from propagating the private clone's `0700` root mode. `request-web` has an `npm run health` command for read-only direct/proxy session persistence and PNG checks. These source changes have not been pushed or deployed, and no live permission change was made. Correct the public API directory permissions and complete the separate production `.env`, then rerun the health command.

On 8 November, the user requested completion of the private repository's `.env`, with the database username and password left blank. Saved and reloaded `/home/tnrmssks25/repositories/request-api/.env` to verify these values:

```dotenv
RRMS_DB_HOST=localhost
RRMS_DB_PORT=3306
RRMS_DB_USER=
RRMS_DB_PASS=
RRMS_DB_NAME=tnrmssks25_rmms_db
RRMS_TIMEZONE=Asia/Manila
REQUEST_WEB_URL=https://request-web-dun.vercel.app
REQUEST_DEPLOY_PATH=/home/tnrmssks25/public_html/request-api
```

This changed the private repository configuration only. The production application's separate `.env`, database credentials, and deployment remain pending. The hosting state below records the initial review.

## Working project

- Backend: `C:\VertrigoServ\www\request-api`, PHP 8.2 with MySQLi.
- Frontend and proxy: `C:\VertrigoServ\www\request-web`, Node.js 22, no third-party runtime dependencies.
- Work only in these two applications. The sibling `rrms` application is separate.
- This is a partial frontend/backend separation: the login is static frontend code, while the administrative workspace is still PHP rendered through the frontend's `/api/` proxy.
- Existing features include pending/overdue/released requests, server-side request paging, document names, and Student Record Logs with seven-working-day return deadlines.

## Local setup verified

Both development servers were already running and returned valid session JSON:

- Frontend: `http://127.0.0.1:5178`
- Backend: `http://127.0.0.1:8016`
- Local database: `request_db`; timezone: `Asia/Manila`.
- Backend `.env` contains the database configuration and points `REQUEST_WEB_URL` to the local frontend.
- Frontend `.env` sets `REQUEST_API_ORIGIN=http://127.0.0.1:8016` and `REQUEST_WEB_PORT=5178`.
- Both `.env` files are ignored by Git and were not modified during this review.

If the servers stop, run `node serve.mjs` in the backend and `npm.cmd run dev` in the frontend, using separate terminals. Do not start duplicate servers on the same ports.

## GitHub and hosting verified

| Component | Verified state |
| --- | --- |
| Backend repository | `https://github.com/four-teen/request-api`, `main`, commit `652d9302e41f21f1ee875704dfdd1422404568a9` |
| Frontend repository | `https://github.com/four-teen/request-web`, local `main` at `ccdfdc07ee781e133a328b991697e475962e064d`; Vercel production uses that same commit |
| Local Git state | Both working trees were clean before this note was added |
| Vercel | Project `elbrens-projects/request-web`; production deployment `GhRgaBDbNH5Mk9c3hpNXHbx7UKg8` is Ready |
| Frontend URL | `https://request-web-dun.vercel.app/` |
| Vercel configuration | Production `REQUEST_API_ORIGIN=https://sksu-orms.net/request-api` |
| cPanel private clone | `/home/tnrmssks25/repositories/request-api`, `main`, same backend commit as GitHub |
| Saved deployment configuration | Private clone `.env` contains `REQUEST_DEPLOY_PATH=/home/tnrmssks25/public_html/request-api` |
| Production API directory | `/home/tnrmssks25/public_html/request-api` contains only `.htaccess` and a zero-byte `.env`; application code has not been deployed there |
| cPanel deployment history | Most recent deployment information is unavailable; HEAD is shown as deployable |
| Hosted database | `tnrmssks25_rmms_db` is accessible through the existing cPanel/phpMyAdmin session, with 14 tables |
| Record-log schema | `tbl_record_logs` already exists with 19 expected columns and the primary, `one_open_student`, `due_returns`, `request_history`, and `student_history` indexes |

The cPanel database was inspected read-only. No migration, data import, production deployment, credential change, or production configuration save was performed during this review.

## Exact remaining hosting work

1. Complete and save the production API directory's empty `.env` using `.env.example` and the existing cPanel database credentials. The selected hosted database is `tnrmssks25_rmms_db`. Confirm that the configured database user has access. Do not copy local development credentials to production.
2. Check the already-open production `.env` editor for an unsaved draft before replacing anything. Original editor tabs were left untouched; some tabs remain owned by the prior browser automation session. Separate review tabs worked with the existing signed-in browser.
3. Deploy backend HEAD through cPanel Git Version Control after checking the production configuration. `.cpanel.yml` runs `deploy/cpanel.sh`, which uses additive rsync, preserves the production `.env` and uploads, and does not migrate the database.
4. Verify that `https://sksu-orms.net/request-api/auth/session.php` returns JSON containing `csrfToken`, `user`, and `workspaceUrl`, then verify the same response through `https://request-web-dun.vercel.app/api/auth/session.php`.
5. Verify browser sign-in and the administrative screens with an authorized existing account, preserving real records.

At review time, both live session URLs returned HTTP 200 with the university website's HTML instead of API JSON. A Ready Vercel deployment therefore does not yet mean working production authentication. The local session endpoints return the expected JSON. Production database login and application sign-in remain unverified until the API is configured and deployed.

The record-log table is already present in the hosted database. Do not recreate the database, reimport records, or assume another schema migration is required.

## Validation completed

- Frontend: `npm.cmd test` — 4/4 passed, including proxy cookies/redirects and streamed responses.
- Frontend: `npm.cmd run build` — passed.
- Backend: `node --test tests/*.test.mjs` — 15/15 passed.
- Backend PHP checks: `tests/request-status.php`, `tests/request-documents.php`, `tests/request-table.php`, `tests/record-logs-page.php`, and `tests/record-logs.php` — all passed against the local database.
- Record-log write checks use a connection-local temporary table; existing requests and log records were preserved.

Frontend networking tests and database checks require network access outside the restricted command sandbox. Initial sandbox networking failures disappeared when the same tests ran with networking enabled.
