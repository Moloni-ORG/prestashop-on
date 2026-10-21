# One Product hook (no operation) replaces three per-operation hooks

**Symptom:** Settings save and the Tools "reinstall hooks" action each installed up to three separate Moloni ON hooks for the `Product` model — one per operation (`create`, `update`, `stockChanged`) — whenever the corresponding setting was on.

**Cause:** `WebhookCreate::handle()` required an `$operation` argument and always sent it in the `hookCreate` mutation's `data.operation`. Nothing in the hook contract actually requires narrowing by operation: the mutation's `operation` field is optional, and a hook created with it omitted/null fires for **every** operation of that model — the delivered payload then carries the real operation (`{companyId, model, operation, productId}`), which our inbound Webservice resource already switches on.

**Rule / what changed:**
- `WebhookCreate::handle($model, $operation = null)` now omits `operation` from the mutation payload entirely when it is `null`, instead of sending a literal `null`/empty value.
- `SettingsFormHandler::createWebHooks()` and `Tools::reinstallHooks()` now install a single `handle('Product')` (no operation) when **any** of `syncStockToPrestashop` / `addProductsToPrestashop` / `updateProductsToPrestashop` is on, instead of calling `handle('Product', <op>)` once per enabled setting.
- No receiver change needed: `WebserviceSpecificManagementMoloniOnResource::manage()` already switches on the delivered `operation` and silently acknowledges anything it doesn't recognise (e.g. a future `delete`), and each `src/Webservice/Product/*` handler already re-checks its own specific setting before acting — so a single broader hook can't bypass a setting that's off.
- `WebhookDeleteAll::handle()` already matches hooks by a `url` substring search (not by operation), so it continues to delete every old per-operation hook — and the new single hook — for our endpoint when settings are saved or hooks are reinstalled. No change needed there.

**Still manual-only:** not exercised against a running PrestaShop/Moloni pair (no CI quality gate here) — verified by reading the mutation's input type and the receiver/handler code paths. A human should confirm end-to-end: save settings with one of the three toggles on, reinstall hooks, then trigger a product create/update/stock change and a delete in Moloni and watch only one hook row exist for this store's URL.
