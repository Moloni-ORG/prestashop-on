# AGENTS.md — prestashop-on

Guidance for AI coding agents (Claude Code, Cursor, Copilot, …) and new developers working in this Moloni ON plugin. This is the canonical context file; `CLAUDE.md` imports it.

## What this plugin is

The **Moloni ON** PrestaShop module — a **PHP module** (Composer `type: prestashop-module`, namespace `MoloniOn\` PSR-4 → `src/`, PHP ≥ 7.2, platform pinned 7.4). It installs into a PrestaShop back-office (`ps_versions_compliancy` **1.7.6 – 9.0.0**) and syncs the store to Moloni ON **over the public GraphQL API** (`https://api.molonion.in/v1`). It creates Moloni documents from PrestaShop orders, syncs products/stock (both ways), taxes, and customers.

## Responsibilities & boundaries

**Owns:**
- Reacting to PrestaShop **hooks** (`src/Hooks/`: `OrderStatusUpdate`, `ProductSave`, `ProductStockUpdate`, `AdminOrderButtons`) — registered by `CoreModule::install()`.
- Issuing Moloni documents from orders and the merchant admin surface (Symfony `src/Controller/Admin/`, forms in `src/Form/`).
- **Inbound sync from Moloni**: a PrestaShop **Webservice API** resource (`src/Webservice/WebserviceSpecificManagementMoloniOnResource.php` + `src/Webservice/Product/`) that Moloni calls to push product/stock changes back into PrestaShop.
- The Moloni public-API client (`src/Api/`, GraphQL `Queries/` + `Mutations/` + `Endpoints/`, HTTP via Guzzle in `src/Guzzle/`).

**Must not:** call any non-public Moloni service (public API only); hard-code or commit credentials (merchant authenticates in the module; auth flows through `src/EventListener/AuthenticationListener.php`); double-issue documents (fiscal idempotency below); assume a single PrestaShop version's core API (see the 1.7→9 gotcha). UI stays **host-native PrestaShop back-office** — no invented look, never restyled.

## Architecture map

- **`molonion.php`** — the PrestaShop module entrypoint: `class MoloniOn extends CoreModule` (name `molonion`, tab `administration`). Sets version (`#VERSION#`, replaced at build) and `ps_versions_compliancy`, then `autoload()`.
- **`CoreModule.php`** — base module: Composer autoload, PrestaShop lifecycle (`install`/`uninstall`/`enable`/`disable` delegate to `src/Activators/Install.php` + `Remove.php`), and hook registration — `actionProductAdd`/`actionProductUpdate` → `ProductSave`, `actionUpdateQuantity` → `ProductStockUpdate`, `actionOrderStatusUpdate` → `OrderStatusUpdate`, `actionGetAdminOrderButtons` → `AdminOrderButtons`; `hookAddWebserviceResources` registers the `molonionresource` webservice.
- **`src/MoloniContext.php` — the spine.** Registered as the **public** Symfony service `molonion.context` (`config/services/admin/core.yml`); hooks fetch it via `$this->get('molonion.context')`, controllers get it by constructor injection. On construction it bootstraps, in order: `Configurations` (from `config/platform.php`) → `MoloniOnApp` entity (credentials/tokens) → `MoloniApi` → `Settings` → the static tool singletons. `MoloniContext::instance()` exposes it globally.
- **`src/Hooks/`** — PrestaShop hook handlers (`AbstractHookAction` base; order status, product save, stock update, admin order buttons). PrestaShop → Moloni direction.
- **`src/Webservice/`** — **inbound Moloni → PrestaShop** sync: `WebserviceSpecificManagementMoloniOnResource.php` (included directly by `CoreModule`) implements a PrestaShop specific-management webservice; Moloni calls it with a JSON body `{model:'Product', operation:'create'|'update'|'stockChanged', productId}`, dispatched to `src/Webservice/Product/*`.
- **`src/Api/`** — Moloni public **GraphQL** client. `MoloniApi.php` is the authenticated transport (OAuth-style login/refresh against `/auth/grant`, injects bearer token + `companyId`, posts through `src/Guzzle/GuzzleWrapper`; `hasValidAuthentication()`/`hasValidCompany()` gate calls). `MoloniApiClient.php` is a static factory of lazy endpoint singletons (`::invoice()`, `::products()`, `::customers()`, …). `Endpoints/` classes load operations from **`.graphql` files** in `Queries/` and `Mutations/` — **edit the `.graphql` file, not PHP strings**.
- **`src/Controller/Admin/`** — Symfony `FrameworkBundleAdminController`s extending `MoloniController`. Routes in `config/routing/admin/**.yaml` (prefix `/molonion`); route-name constants in `src/Enums/MoloniRoutes.php`. Twig templates in `views/templates/admin/**`. **Auth:** `MoloniController` registers `src/EventListener/AuthenticationListener.php` on `kernel.controller`; before each action it checks token/company state and redirects by the route's tier (`ROUTES_NON_AUTHENTICATED` / `ROUTES_PARTIALLY_AUTHENTICATED` / `ROUTES_FULLY_AUTHENTICATED`) — **classify every new route in one of those arrays** or auth redirects misbehave.
- **`src/Actions/`** — use-case orchestration (`Orders/OrderCreateDocument`, `Imports/*`, `Exports/*`); controllers instantiate an Action and call `handle()`. **`src/Builders/`** — assemble payloads (`DocumentFromOrder` + `Builders/Document/*`; `MoloniProduct*`/`PrestashopProduct*` map products/variants). **`src/Services/`** — `MoloniProduct/`, `PrestashopProduct/`, `Tax/`.
- **`src/Entity/`** — Doctrine entities backing the `moloni_on_*` tables (`MoloniOnApp`, `MoloniOnSettings`, `MoloniOnLogs`, `MoloniOnSyncLogs`, `MoloniOnOrderDocuments`, `MoloniOnProductAssociations`); DDL in `src/Activators/sql/*.sql`, applied on install. **`src/Repository/`** — Doctrine repos (incl. thin ones over PrestaShop tables). **`src/Tools/`** — process-wide **static** singletons initialized by `MoloniContext`: `Settings` (`Settings::get('key')`), `Logs`, `SyncLogs` (loop-guard so hook-driven syncs don't echo back), `ProductAssociations`.
- **`src/Form/`** (Symfony forms), **`src/Enums/`** (domain constants), **`src/Helpers/`**, **`src/Exceptions/`**, **`src/Traits/`**, **`src/Mails/`** (+ `mails/`, `translations/`, `views/`, `config/`, `upgrade/` at the root).
- **`.dev/`** — the **frontend asset toolchain**: **webpack / Symfony Encore** (its own `package.json` + `webpack.config.js` + `postcss.config.js`). Compiles to `views/js/app.js` + `views/css/app.css` (both **gitignored/generated** — never hand-edit; sources live in `.dev/js/**`, `.dev/css/**`).
- **`builder.php`** — packages `build/molonion.zip`; run in CI on `v*` tags. No submodules (`.gitmodules` absent).

## Running locally

PHP deps and frontend assets build separately; the checkout is then bind-mounted into a Dockerised PrestaShop (`dev.md` has the walkthrough).

1. **PHP deps** (project root): `composer install`
2. **Frontend deps** (inside `.dev/`): `cd .dev && npm install`
3. **Build assets** (inside `.dev/`): `npm run build-prod` (webpack). **Required after every JS/CSS change.** (`.nvmrc` = 20 is for this toolchain, not the PHP runtime.)
4. **Run the store** (project root): `docker compose up -d` — PrestaShop (`localhost:8080/administration`, `user@moloni.com` / `123456789`) + MySQL 5.7. The compose file **bind-mounts the checkout directly** at `/var/www/html/modules/molonion`, so edits are live — no path tweaking needed.
5. In the back-office, install the **Moloni ON** module and authenticate to Moloni.

## Commands

| Command | Where | What it does |
| --- | --- | --- |
| `composer install` | root | Install PHP dependencies (dev tools included) |
| `composer run build` | root | Full package: `auto-index` + `auto-header` + `auto-phpcs` + `php builder.php` → `build/molonion.zip` (**rewrites source** — see Gotchas) |
| `composer run auto-phpcs` | root | `php-cs-fixer fix` against `.php-cs-fixer.php` (PrestaShop coding standard) |
| `npm install` | `.dev/` | Install the asset toolchain |
| `npm run build-prod` | `.dev/` | Compile CSS/JS (webpack) — rerun after any JS/CSS change |
| `npm run watch` | `.dev/` | Encore dev watch |
| `docker compose up -d` | root | Boot local PrestaShop + MySQL (see `dev.md`) |

## Verification

**There is no PR-time quality gate** — the only CI workflow (`.github/workflows/production-deploy.yml`) builds assets + `composer install --no-dev` + `php builder.php` and publishes a GitHub release on `v*` tags; nothing runs on PRs. Note also that `composer run check-phpstan` is declared but points at `tests/phpstan/phpstan.neon`, **which is absent from the checkout** — so phpstan is not runnable as-is (the `php-cs-fixer`/`phpcs` bins under `vendor/bin/` are).

So verify a change by:
1. **Build must be clean** — `composer install` resolves and `npm run build-prod` (in `.dev/`) compiles without error.
2. **Exercise the behaviour** — install the module into the local Docker PrestaShop and drive the affected flow by hand (generate a document from an order, product/stock sync via the Webservice resource, settings save). This is the required check for any behaviour change.

(You *may* run `composer run auto-phpcs` to keep to the PrestaShop coding standard, but it rewrites files — review the diff.)

## Testing

No test suite is configured (`tests/` phpstan config is missing; no PHPUnit). "Testing" = the manual verification above (build clean + exercise in the Docker PrestaShop). If you add coverage, restore a `tests/phpstan/phpstan.neon` so `composer run check-phpstan` works, and note it here.

## Conventions

- **Namespace `MoloniOn\`**, PSR-4 → `src/`; **PrestaShop coding standard** enforced by `php-cs-fixer` (`.php-cs-fixer.php` → `PrestaShop\CodingStandards\CsFixer\Config`). Run `composer run auto-phpcs` before committing.
- **Every PHP file** starts with the Moloni license header and `if (!defined('_PS_VERSION_')) { exit; }`, and every directory carries an `index.php` guard — all **auto-applied by `composer run build`** (`auto-header` / `auto-index`); don't hand-maintain or delete them.
- **PrestaShop/Symfony idioms**: module lifecycle via `CoreModule`, Symfony `Controller/`, `Form/`, `EventListener/`, Doctrine `Entity/` + `Repository/`. **DI is autowired** (`config/services/admin/*.yml`, `_defaults: autowire/autoconfigure: true`).
- **GraphQL operations live in `.graphql` files** (`src/Api/Queries/`, `src/Api/Mutations/`) — edit those, never inline PHP strings.
- **i18n** via the new translation system (`$this->trans( …, 'Modules.Molonion.*' )`); translations in `translations/`.
- Read `api_url`/config through `MoloniContext`; never hard-code the endpoint or credentials.
- **When in doubt, ask / return NEEDS DIRECTION — never guess** an API contract, fiscal mapping, or expected behaviour.
- **Assets are compiled artifacts** — edit sources under `.dev/`, rebuild with webpack, never hand-edit compiled output.
- **Idempotency + fiscal correctness are load-bearing** — document creation must not double-issue; tax / document-type mappings are fiscal-legal.

## Gotchas

Curated, verified conventions and traps. This is the default-trusted source — every agent reads it.

- **`composer run build` rewrites source, it's not just a zip.** It runs `auto-index` (drops `index.php` into every folder), `auto-header` (stamps the license header via `header-stamp`), and `auto-phpcs` (`php-cs-fixer fix`) *before* `builder.php`. Running it mutates the working tree — expect a diff, and don't run it casually mid-review.
- **Assets are webpack / Symfony Encore** (in `.dev/`), *not* gulp like `woocommerce-on`. `npm run build-prod` in `.dev/` is mandatory after any JS/CSS change or the back-office UI ships stale.
- **This PHP module *does* have an inbound webhook-like surface** — the PrestaShop **Webservice API** resource (`src/Webservice/`), which Moloni calls to push product/stock changes. It's registered in `CoreModule::install()`; a broken install won't sync inbound.
- **Wide PrestaShop compat (1.7.6 → 9.0.0).** Core APIs differ substantially between 1.7 and 8/9 — guard version-specific calls; a fix that works on one major can break another. Test against the version you're targeting.
- **`check-phpstan` is broken out of the box** — its config (`tests/phpstan/phpstan.neon`) isn't in the checkout, even though the phpstan bin is installed. Don't assume `composer run check-phpstan` runs; it won't until that config is restored.
- **Fiscal idempotency is load-bearing** — never double-issue a document; treat tax/document-type mappings as legal, not cosmetic.
- **No submodules** here (`.gitmodules` absent).

> Recent, **unverified** findings live in `.claude/journal/` (one dated file per finding). They are promoted into this section by manual curation once re-verified against the code — treat them as leads, not yet rules.
