# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`molonion` is a **PrestaShop module** that integrates PrestaShop stores with **Moloni ON**
(invoicing/billing SaaS). It creates fiscal documents from orders, and synchronizes products
and stock in both directions between PrestaShop and Moloni ON over Moloni's **GraphQL API**.

Runtime: PHP (7.2+, platform pinned to 7.4 in composer). Compatible PrestaShop 1.7.6+.
PHP autoload is PSR-4: `MoloniOn\` → `src/`.

## Commands

PHP dependencies and build (run from project root):
```bash
composer install                 # dev deps
composer run install-prod        # prod deps (--no-dev --optimize-autoloader)
composer run build               # full release build (see below)
composer run auto-phpcs          # apply PrestaShop coding standards (php-cs-fixer fix)
composer run check-phpstan       # static analysis (see note)
php builder.php                  # produce build/molonion.zip only
```

`composer run build` runs, in order: `auto-index` (stamp `index.php` guards into every dir via
`autoindex`), `auto-header` (stamp license headers via `header-stamp`), `auto-phpcs`, then
`php builder.php`. The builder copies an allowlist of dirs/files (see `INCLUDE_DIRS`/`INCLUDE_FILES`
in `builder.php`) into `build/molonion/`, replaces the `#VERSION#` placeholder in `molonion.php`
with `PLUGIN_VERSION`, and zips to `build/molonion.zip`.

> Note: `check-phpstan` points at `tests/phpstan/phpstan.neon`, which is **not present** in the repo.
> The script will fail until that config exists.

Frontend assets (run from the `.dev/` folder — it has its own `package.json`):
```bash
cd .dev
npm install
npm run build-prod   # webpack (Symfony Encore). MUST run after any JS/CSS change.
npm run watch        # encore dev --watch
```
Assets compile to `views/js/app.js` and `views/css/app.css`, which are **gitignored and generated** —
never edit them by hand. Source lives in `.dev/js/**` and `.dev/css/**`.

Local store via Docker (see `dev.md`): copy `docker-compose.yml` to the parent directory of the
project, then `docker compose up -d`. Back office at `http://localhost:8080/administration`
(admin `user@moloni.com` / `123456789`). The compose file bind-mounts this repo to
`/var/www/html/modules/molonion`.

Releases: pushing a `v*` git tag triggers `.github/workflows/production-deploy.yml`, which builds
assets + composer prod deps, runs `builder.php` with `PLUGIN_VERSION=<tag>`, and publishes
`molonion.zip` as a GitHub release asset.

## Architecture

### Module entry and hooks
`molonion.php` declares the `MoloniOn` module class (name `molonion`); it `include`s and extends
`CoreModule.php`. `CoreModule` holds all PrestaShop lifecycle methods (`install`/`uninstall`/
`enable`/`disable` delegate to `src/Activators/Install.php` + `Remove.php`) and the PrestaShop
**hooks**: `actionProductAdd/Update` → `ProductSave`, `actionUpdateQuantity` → `ProductStockUpdate`,
`actionOrderStatusUpdate` → `OrderStatusUpdate`, `actionGetAdminOrderButtons` → `AdminOrderButtons`
(all in `src/Hooks/`). `hookAddWebserviceResources` registers the `molonionresource` webservice.
The `#VERSION#` string in `molonion.php` is a build-time placeholder.

### MoloniContext (the spine)
`src/MoloniContext.php` is the central object, registered as the **public** Symfony service
`molonion.context` (see `config/services/admin/core.yml`). Hooks fetch it via
`$this->get('molonion.context')`; controllers receive it by constructor injection. On construction it
bootstraps everything in order: `Configurations` (from `config/platform.php`) → `MoloniOnApp` entity
(credentials/tokens) → `MoloniApi` → `Settings` → tool singletons (`Logs`, `SyncLogs`,
`ProductAssociations`). A static `MoloniContext::instance()` exposes it globally.

### API layer (GraphQL)
- `src/Api/MoloniApi.php` — authenticated transport. Handles OAuth-style login/refresh against
  `/auth/grant`, injects bearer token + `companyId`, and posts to the GraphQL endpoint
  (`api_url` in `config/platform.php`) through `GuzzleWrapper`. Auth state lives in the
  `MoloniOnApp` entity; `hasValidAuthentication()` / `hasValidCompany()` gate everything.
- `src/Api/MoloniApiClient.php` — static factory returning lazily-instantiated singleton
  endpoint objects (`MoloniApiClient::invoice()`, `::products()`, `::customers()`, …).
- `src/Api/Endpoints/**` — one class per Moloni domain. They extend `Endpoint`, which loads the
  actual GraphQL operations from `.graphql` files in `src/Api/Queries/` and `src/Api/Mutations/`
  by name (`loadQuery`/`loadMutation`) and provides `simplePost` / `paginatedPost`.
- **To change a GraphQL operation, edit the matching `.graphql` file**, not PHP strings.
  `.graphqlrc.yaml` points tooling at the remote schema.

### Admin UI (Symfony controllers)
Controllers are Symfony `FrameworkBundleAdminController`s under `src/Controller/Admin/**`, all
extending `MoloniController` (abstract, `implements MoloniControllerInterface`). Routes live in
`config/routing/admin/**.yaml` (imported via `config/routes.yml`, URL prefix `/molonion`); route
name constants are centralized in `src/Enums/MoloniRoutes.php`. Templates render from
`views/templates/admin/**` (Twig via `getViewDir()`).

**Auth flow:** `MoloniController` registers `AuthenticationListener` on the `kernel.controller`
event. Before each Moloni controller action it checks token/company state and redirects to
login / company-select / orders depending on the route's tier in `MoloniRoutes`
(`ROUTES_NON_AUTHENTICATED` / `ROUTES_PARTIALLY_AUTHENTICATED` / `ROUTES_FULLY_AUTHENTICATED`).
When adding a route, also classify it in one of those arrays or auth redirects will misbehave.

### Business logic layering
Controllers stay thin and delegate:
- `src/Actions/**` — use-case orchestration (e.g. `Orders/OrderCreateDocument`, `Imports/*`,
  `Exports/*`, product-list fetching). Controllers instantiate an Action and call `handle()`.
- `src/Builders/**` — assemble Moloni/PrestaShop payloads. `DocumentFromOrder` (+ `Builders/Document/*`)
  turns a PrestaShop order into a Moloni document; `MoloniProduct*` / `PrestashopProduct*` map
  products/variants across systems.
- `src/Repository/**` — Doctrine repositories, including thin ones over PrestaShop tables
  (`OrdersRepository`, `ProductsRepository`) and the module's own entities.
- `src/Entity/**` — Doctrine ORM entities backing the `moloni_on_*` tables (`MoloniOnApp`,
  `MoloniOnSettings`, `MoloniOnLogs`, `MoloniOnSyncLogs`, `MoloniOnOrderDocuments`,
  `MoloniOnProductAssociations`). Table DDL is in `src/Activators/sql/*.sql` and applied on install.
- `src/Tools/**` — process-wide **static** singletons initialized by `MoloniContext`: `Settings`
  (read config with `Settings::get('key')`), `Logs`, `SyncLogs` (loop-guard so hook-driven syncs
  don't echo back), `ProductAssociations`.
- `src/Enums/**` — domain constants (document types/status, sync fields, routes, etc.).

### Inbound sync (webservice)
`src/Webservice/WebserviceSpecificManagementMoloniOnResource.php` (included directly by
`CoreModule`) implements a PrestaShop specific-management webservice. Moloni ON calls it with a JSON
body `{model:'Product', operation:'create'|'update'|'stockChanged', productId}`; it dispatches to
`src/Webservice/Product/*`. This is the Moloni → PrestaShop direction; the PrestaShop → Moloni
direction goes through the hooks above.

## Conventions

- Every PHP file starts with the Moloni license header and `if (!defined('_PS_VERSION_')) { exit; }`.
  Both are applied automatically by `composer run build` (header-stamp / autoindex) — don't hand-maintain
  them, and expect an `index.php` guard file in every directory.
- Coding standard is PrestaShop's (`.php-cs-fixer.php` uses `PrestaShop\CodingStandards\CsFixer\Config`).
  Run `composer run auto-phpcs` before committing.
- Strings shown to users go through the new translation system (`$this->trans(..., 'Modules.Molonion.*')`);
  `isUsingNewTranslationSystem()` returns true. Translations live in `translations/`.
- DI uses autowiring (`config/services/admin/*.yml`, `_defaults: autowire/autoconfigure: true`).
