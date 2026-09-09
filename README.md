# iMSafe v2.0 Community Emergency Desk — PHP/MySQL Localhost Edition

This package is a **standalone PHP 8.2+ and MySQL/MariaDB application** for local use. It includes the full iMSafe v2.0 public homepage, local account creation/sign-in, adaptive rapid-assessment reporting, public reference-code tracking, protected incident monitoring dashboard, dispatch controls, normalized analytics, live Philippine administrative-area lookups, DOST-PAGASA outlook, GDACS global signal context, and the iMAssist disaster information assistant. The implementation uses explicit PHP classes instead of placing domain logic inside pages.

## Object-Oriented Structure

| Path | Responsibility |
| --- | --- |
| `src/Support/Config.php` | Reads environment-based application and database configuration. |
| `src/Support/Database.php` | Creates the single PDO connection used by repositories. |
| `src/Services/HttpClient.php` | Makes bounded server-side API calls. |
| `src/Services/LocationService.php` | Loads and caches Philippine locations, with PSGC GitLab and a code-compatible PSGC Community API fallback. |
| `src/Services/PagasaService.php` | Retrieves the official DOST-PAGASA weather outlook. |
| `src/Services/GlobalAdvisoryService.php` | Retrieves, classifies, and caches GDACS global disaster signals. |
| `src/Services/IAssistService.php` | Applies iMAssist emergency, scope, source, privacy, and navigation rules. |
| `src/Services/IAssistContextService.php` | Builds public-safe context from official feeds and local incident records. |
| `src/Services/OpenAiResponsesClient.php` | Optionally generates concise structured answers through the OpenAI Responses API. |
| `src/Repositories/IncidentRepository.php` | Persists, retrieves, and updates normalized incident records. |
| `src/Repositories/AccountRepository.php` | Creates and authenticates local community accounts using password hashes. |
| `src/Controllers/IncidentController.php` | Validates and turns a submitted rapid assessment into domain data. |
| `src/AppKernel.php` | Wires the local services, repository, and controller together. |

## Local Setup

1. Copy `imsafe-php-localhost` into your XAMPP `htdocs` folder or any PHP server directory.
2. Start **Apache** and **MySQL** in XAMPP. Import `schema.sql` through phpMyAdmin, or run `mysql -u root -p < schema.sql` in this folder. This creates the `imsafe_oop_local` database and the eight normalized application tables. If upgrading an existing installation, run `migrations/001_add_incident_user.sql` once, then `migrations/002_add_province.sql`. The second migration is additive and preserves existing records (MariaDB). On MySQL, check for the two columns first and use `ADD COLUMN` without `IF NOT EXISTS` if your version does not support it.
3. Configure a dedicated MySQL user. Example:

   ```sql
   CREATE USER 'imsafe_local'@'localhost' IDENTIFIED BY 'replace-this-password';
   GRANT ALL PRIVILEGES ON imsafe_oop_local.* TO 'imsafe_local'@'localhost';
   FLUSH PRIVILEGES;
   ```

4. Set these web-server environment values. For a fresh XAMPP installation, iMSafe v2.0 falls back to `root` with a blank database password and uses `admin@imsafe.local` / `imsafe-local-admin` for local administrator access. For safer local use, configure the dedicated `imsafe_local` database account and different administrator credentials through Apache `SetEnv` or a virtual-host configuration. Do not use these fallbacks on a shared or production server.

   | Variable | Development example |
   | --- | --- |
   | `IMSAFE_DB_HOST` | `localhost` |
   | `IMSAFE_DB_NAME` | `imsafe_oop_local` |
   | `IMSAFE_DB_USER` | `imsafe_local` |
   | `IMSAFE_DB_PASS` | Your dedicated MySQL password |
   | `IMSAFE_ADMIN_EMAIL` | Administrator login email |
   | `IMSAFE_ADMIN_PASSWORD` | A strong administrator password |
   | `IMSAFE_AI_API_KEY` | Optional OpenAI API key for AI-generated iMAssist answers |
   | `IMSAFE_AI_MODEL` | Optional model override; defaults to `gpt-5-mini` |

5. Open `http://localhost/imsafe-php-localhost/index.php`. The full application routes are listed below. The operations workspace uses the local administrator password you configured.

| Route | Local feature |
| --- | --- |
| `index.php` | Full public disaster-monitoring homepage with report, tracking, and administrator sign-in calls to action. |
| `account.php?mode=login` | One email-and-password form that automatically recognizes community and administrator credentials. |
| `account.php?mode=signup` | Local community account creation flow. |
| `account.php?mode=reports` | Signed-in user's account-linked report history. |
| `report.php` | Green/Orange/Red adaptive rapid-assessment form, four-level location cascade, flood check, retry feedback, and submission confirmation. |
| `track.php?ref=IMS-...` | Public reference-code tracking with validation, loading feedback, not-found, error, and status-result states. |
| `dashboard.php` | Protected incident monitoring dashboard. Signed-out visitors are redirected to the administrator option on the unified login page. |
| `admin.php` | Compatibility redirect to `dashboard.php`. |
| `imassist-api.php` | Same-origin JSON endpoint used by the global iMAssist chat widget. |

For PHP's built-in server, run this command after exporting the environment values:

```bash
php -S localhost:8013 -t /path/to/imsafe-php-localhost
```

## Normalized Database Design

The report is **not** stored in a single table. The primary `incidents` record owns separate tables for exact location, rapid-assessment narrative, selected needs, type-specific details, dispatch assignment history, and public status updates. The local-account model is also stored separately. Foreign keys preserve referential integrity; deletion of a report also removes its dependent operational data.

| Table | Purpose |
| --- | --- |
| `incidents` | Report identity, reference code, reporter contact, hazard, legend, narrative, and status. |
| `local_users` | Local community account identity, unique email, password hash, and sign-in timestamp. |
| `incident_locations` | Region, optional province, municipality/city, barangay, house/street, and landmark. |
| `rapid_assessments` | Assessment color, condition, narrative, alternate contact, email, and evidence note. |
| `assessment_needs` | One selected rapid-response need per row. |
| `incident_details` | One type-specific detail per row, including flood conditions. |
| `dispatch_assignments` | Dispatch-team assignment history with active/released timestamps. |
| `incident_updates` | Public-facing or internal status updates. |

## Live Data APIs

All third-party calls happen **server side** through `api.php`; browser code never receives provider credentials or calls providers directly. Location routes return provider metadata and a last-updated value. The location service caches successful values for 24 hours and falls back automatically from PSGC GitLab to the code-compatible PSGC Community API. The weather route uses a dedicated 12-second bounded request because the official DOST-PAGASA weather page can take longer than the location endpoints.

| Local endpoint | External source | Use |
| --- | --- | --- |
| `api.php?action=regions` | PSGC GitLab, then PSGC Community API | Region list. |
| `api.php?action=provinces&region=...` | PSGC GitLab, then PSGC Community API | Provinces within the selected region. |
| `api.php?action=municipalities&region=...&province=...` | PSGC GitLab, then PSGC Community API | Cities/municipalities filtered by region and province. `province=none` handles NCR and cities without a listed province. |
| `api.php?action=barangays&municipality=...` | PSGC GitLab, then PSGC Community API | Barangays. |
| `api.php?action=pagasa` | DOST-PAGASA | Current official weather outlook. |
| `api.php?action=advisories` | GDACS | Current global disaster signal list with severity and hazard labels. |
| `api.php?action=analytics` | Local normalized MySQL data | Protected aggregate totals and counts by hazard, legend, and status. |
| `api.php?action=reports` | Local normalized MySQL data | Protected full incident queue projection for local operations. |

## iMAssist disaster assistant

iMAssist appears on every main application page. It handles disaster safety, preparedness, evacuation, current conditions, public incident information, reporting, and tracking. Immediate-danger phrases are handled locally before any provider request so the user receives a 911-first response without waiting for external services. Unrelated questions are declined.

When `IMSAFE_AI_API_KEY` is configured in the web-server environment, iMAssist uses the OpenAI Responses API with structured output and `store: false`. The key is never sent to the browser or stored in source code. Without a key, during a provider outage, or after an invalid provider response, the same interface automatically uses the built-in disaster guidance.

Current answers are labeled as official source data, cached official-source data, community incident information, general guidance, or unavailable. Generic incident questions expose municipality-level public context only. Barangay and public update details are provided only after the user supplies the exact report reference. Chat history is held only in the open browser page and is not saved by iMSafe.

The endpoint requires CSRF protection, accepts only bounded POST requests, rate-limits messages by IP, strips control characters, and limits conversation history. iMAssist is a support tool and never replaces 911, emergency responders, PAGASA, PHIVOLCS, NDRRMC, or local government instructions.

Administrators can use one **Export reports** menu on the dashboard to download either a true Excel workbook (`.xlsx`) or a paginated PDF. The workbook includes a color-coded Overview sheet, a filterable Reports sheet with frozen headings, and a focused Flood reports sheet. The PDF includes an operational summary, a report directory, priority-colored incident sections, complete report details, and page numbering. Both downloads are generated by `export.php`, require an active administrator session, use no-store response headers, and include report, contact, location, assessment, needs, flood, assignment, and latest-update fields. A UTF-8 CSV response remains available through `export.php?format=csv` for backward compatibility; spreadsheet formulas are neutralized in all tabular exports.

Flood reports use shared operational depth ranges everywhere they appear: ankle-deep is up to 6 in (up to 0.5 ft), knee-deep is 7–18 in (0.6–1.5 ft), waist-deep is 19–36 in (1.6–3.0 ft), and chest-deep or higher is more than 36 in (over 3.0 ft). These are practical visual estimates rather than instrument measurements.

## Security Notes

Change `IMSAFE_ADMIN_PASSWORD` before use. Use a dedicated database account rather than a privileged root account. Do not expose the PHP built-in server to public networks. Forms use CSRF protection, session hardening, input allowlists, and lightweight local rate limiting. Uploaded JPG, PNG, and WEBP evidence is validated by content, saved under a random private filename in `storage/evidence`, and served only to signed-in administrators through `evidence.php`. For a production rollout, move evidence to managed object storage, add centralized monitoring, and use role-based administrator accounts.

## Redesigned interface

The shared shell lives in `partials/header.php` and `partials/footer.php`. `assets/app.css` owns the common tokens and form controls; the home, navigation, and dashboard stylesheets own their respective surfaces. Barlow Condensed SemiBold is self-hosted for headings (license in `assets/fonts/OFL.txt`), with Segoe UI/system fonts for readable body text and 16px form controls. No external font request is required.

The mobile menu supports Escape, visible keyboard focus, a skip link, and a no-JavaScript navigation fallback. Location controls cancel outdated requests, clear descendant selections immediately, show retriable provider failures, and restore saved selections after rejected submissions. JavaScript is required for the report's adaptive questions and location lists.

### Location data limitations

The providers are community-maintained PSGC datasets, not a guarantee of the latest PSA geographic release. The currently observed primary dataset returns 17 regions. Confirm coverage against the current official PSGC before deployment. A warm cache can serve previous data during an outage; a cold installation still needs a provider. No complete offline PSGC snapshot is bundled. The service preserves nine-digit public codes while matching ten-digit parent metadata supplied by the fallback provider.

`IMSAFE_STORAGE_PATH` optionally overrides the service cache directory, allowing isolated tests or a cache outside the public document root. The lightweight rate limiter still uses the application's local storage directory.

### Regression checks

- Syntax: run `php -l` for every PHP source; `node --check assets/app.js`, `node --check assets/navigation.js`, and `node --check assets/imassist.js`.
- iMAssist tests: run `php tests/imassist-service.php` for emergency bypass, disaster-only scope, privacy, trusted sources, safe actions, and fallback behavior. Run `node tests/imassist-smoke.cjs` for endpoint validation, keyboard behavior, global route coverage, safety responses, and responsive layouts.
- Export tests: run `php tests/report-export.php` for flood-range, CSV-safety, XLSX structure/style, and PDF pagination checks. Run `php tests/export-database-read.php` to generate all export formats from the current database without modifying records.
- Location tests: start `php -S 127.0.0.1:8015 tests/provider-router.php`, then `php tests/location-service.php`. Fixtures test parent validation, tampered names, secondary-provider formats, malformed/empty/partial data, outages, and stale caching without touching the application database.
- Browser tests: install Playwright externally or as a development dependency and set `IMSAFE_PLAYWRIGHT_PATH` to its module directory. `node tests/ui-smoke.cjs` checks public routes, mobile menu, 320/390/720/1440px layouts, Cavite/NCR, provider retry, and rejected-form restoration. Chrome must be installed. Set `IMSAFE_TEST_URL` when testing another local URL.
- `node tests/operations-smoke.cjs` starts an isolated server with a random test administrator password and uses the existing local database read-only. It assumes local root access with a blank password for this XAMPP fixture; adjust the test environment for other setups. It signs in through the normal form and renders a labeled synthetic queue separately, without inserting reports or accounts.
- Screenshots and results are written under `.impeccable/review/`. Test fixtures, partials, and review artifacts deny direct Apache access through their local `.htaccess` files.
