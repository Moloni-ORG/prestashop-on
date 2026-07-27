---
name: test-molonion-sync
description: Test molonion product/stock sync against the local Docker PrestaShop store, in both directions — PS→Moloni (PrestaShop hooks/services) and Moloni→PS (the /api webservice webhook). Use when asked to test, verify, or debug product/stock/association sync, the inbound webhook, or any module service that needs the bootstrapped MoloniContext. Covers bringing up the store, DB access, a CLI kernel harness to drive module services directly, simulating the inbound webhook over HTTP, verification queries, cleanup, and gotchas.
---

# Testing molonion sync locally

The module bind-mounts into a Docker PrestaShop store. Most sync code depends on the
`molonion.context` Symfony service (it initialises the static tools `Settings`, `SyncLogs`,
`MoloniApi`, `ProductAssociations`), so any out-of-request test must bootstrap it first.

See also the memory `store-sync-test-context` for the target Moloni company, gating settings,
and the fiscal-zone gotcha.

## 1. Store & containers

- Back office: `http://localhost:8080/administration` (admin `user@moloni.com` / `123456789`).
- Containers: `dev-prestashop` (PS 8.1.3), `dev-mysql` (MySQL 5.7, `:3306`).
- Check they're up: `docker ps`. Repo is mounted at `/var/www/html/modules/molonion`.
- Module files are read live (opcache in dev revalidates), so edits take effect without a rebuild.
  JS/CSS changes still need `cd .dev && npm run build-prod`.

## 2. Database access

DB `prestashop`, prefix `ps_`, creds `root` / `admin`.

```bash
DB() { docker exec -i dev-mysql mysql -uroot -padmin prestashop "$@" 2>/dev/null; }
DB -e "SELECT id_product, reference, product_type FROM ps_product WHERE reference<>'' LIMIT 5;"
```

Key tables: `ps_moloni_on_product_associations`, `ps_moloni_on_logs` (results),
`ps_moloni_on_sync_logs` (30s loop-guard timeouts — clear between test calls),
`ps_moloni_on_settings` (columns `company_id,label,value`; NOT config_key/value),
`ps_moloni_on_app` (auth: company_id + tokens).

## 3. CLI harness (the main tool)

The `/api` dispatcher and CLI do NOT boot the Symfony kernel, so build one to get the service.
Run PHP inside the container. On Git Bash prefix `MSYS_NO_PATHCONV=1` so paths aren't mangled.

```php
<?php // save under the repo root, e.g. _tmp_test.php (repo == modules/molonion)
define('_PS_ADMIN_DIR_', '/var/www/html/administration');
require '/var/www/html/config/config.inc.php';
require '/var/www/html/modules/molonion/vendor/autoload.php';
require_once '/var/www/html/app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();
$ctx = $kernel->getContainer()->get('molonion.context'); // wires up the static tools
// $ctx->loadCompany();  // needed for company()->... unless an auth check runs first
```

Run: `MSYS_NO_PATHCONV=1 docker exec -i dev-prestashop php /var/www/html/modules/molonion/_tmp_test.php`

Then drive real services, e.g.:
- Find/resolve: `FindMoloniProductByReference::fromPrestashopProduct($product)`,
  `FindPrestashopProductByReference::fromMoloniProduct($moloniArray)`.
- Outbound sync: `new UpdateSimpleProduct($product, $moloni)` / `CreateSimpleProduct` then `->run()`.
- Inbound handler (exact webhook target): `(new \MoloniOn\Webservice\Product\ProductUpdate($mlId))->handle();`

Delete the temp script when done.

## 4. PS→Moloni (outbound)

Gating (company scope): settings `addProductsToMoloni` / `updateProductsToMoloni` = 1.
The real trigger is saving a product in the BO (fires `actionProductUpdate` → `ProductSave`);
for control, drive `CreateSimpleProduct`/`UpdateSimpleProduct` via the harness (§3).
Writes to Moloni are outward-facing — use a disposable test product on the test company.

## 5. Moloni→PS (inbound webhook)

Faithful HTTP simulation (no real Moloni webhook needed). Requires the webservice transport fix
(kernel boot in `WebserviceSpecificManagementMoloniOnResource::manage()`).

1. Provision a WS key + enable webservice (run once via harness):
   `Configuration::updateValue('PS_WEBSERVICE',1);` then a `WebserviceKey` with
   `WebserviceKey::setPermissionForAccount($id, ['molonionresource'=>['POST'=>1]])`,
   and a row in `ps_webservice_account_shop`.
2. Enable inbound gating: `updateProductsToPrestashop` / `addProductsToPrestashop` = 1 for the company.
3. Clear timeouts: `DB -e "DELETE FROM ps_moloni_on_sync_logs;"`
4. POST the payload Moloni sends:
   ```bash
   MSYS_NO_PATHCONV=1 curl -sS -w '\nHTTP %{http_code}\n' -X POST \
     "http://localhost:8080/api/molonionresource/?ws_key=KEY" \
     -H "Content-Type: application/json" \
     -d '{"model":"Product","operation":"update","productId":<mlId>}'
   ```
   operations: `create` | `update` | `stockChanged`. Response `Acknowledge` = manage() ran (NOT proof
   of work — verify side effects). Inbound only READS Moloni + writes PS (no outward Moloni changes).

Alternative: skip HTTP and call `(new ProductUpdate($mlId))->handle()` in the harness (§3).

## 6. Verify

```bash
DB -e "SELECT id,ps_product_id,ps_combination_id,ml_product_id,ml_variant_id FROM ps_moloni_on_product_associations;"
DB -e "SELECT id,created_at,LEFT(message,90) FROM ps_moloni_on_logs ORDER BY id DESC LIMIT 5;"
```
Simple-product association = `ps_combination_id=0` AND `ml_variant_id=0`. A successful sync writes a
"Product created/updated ..." log row. Rename-robustness: change a product's reference so it no longer
matches, keep the association, re-sync → it should still resolve by the stored id (association-first).

## 7. Cleanup

Restore what you changed: settings back to original, remove the WS key + disable `PS_WEBSERVICE`,
restore any renamed references, `DELETE FROM ps_moloni_on_sync_logs`, remove temp `_tmp_*.php` scripts.
Revert any dev-mode auto-regenerated `translations/**/*.xlf` (booting the kernel can append entries):
`git checkout -- translations/`.

## 8. Gotchas

- `MoloniContext::instance()` has return type `:MoloniContext` — calling it before bootstrap THROWS
  (don't use it as a null-check). `company()` is null until `loadCompany()` or an auth check
  (`MoloniApi::hasAuthenticationAndCompany()`) runs.
- The kernel boot opens a second Doctrine DB connection and doesn't set `global $kernel`, so
  `SymfonyContainer::getInstance()`/`Module::get()` stay null downstream — fine for sync (uses injected deps).
- `auto-phpcs` under PHP 8 injects 8.0 trailing commas that break the 7.4 target — run it under 7.4
  (see memory `phpcs-php-version-trailing-comma-hazard`).
- Fiscal-zone/tax gotcha can make PS→Moloni create fail with "Exemption Reason is required" — see
  `store-sync-test-context`.
- The dev module version is `#VERSION` so PrestaShop never auto-runs `upgrade/upgrade-*.php`; apply
  schema migrations manually against the dev DB to test them.
