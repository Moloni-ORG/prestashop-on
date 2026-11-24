# OAuth public-client login: the callback can't be an admin route, and can't use the module's own services either

**Symptom:** a straightforward port of the OAuth (PKCE) connect flow — redirect Moloni ON back to the
existing admin `retrieveCode` route — would intermittently land on PrestaShop's "security compromised"
warning page instead of completing the connection. Routing the callback through a module **front**
controller instead (to dodge that) then failed every single connect with a generic error, because the
front controller tried to call `$this->module->get('molonion.context')` to do the grant itself.

**Cause (two separate issues):**
1. Every PrestaShop admin Symfony route is tokenized. The real `router` service
   (`PrestaShopBundle\Service\Routing\Router`) auto-appends `_token=<value>` (tied to the employee's
   session) to any URL it generates for a non-anonymous route, and `TokenizedUrlsListener` redirects a
   request without a valid one to `admin_security_compromised`. Moloni ON's own redirect back only
   ever carries `?code`/`?state`/`?error` — never a token it was never given.
2. A module's own services (`config/services.yml`, where `molonion.context` lives) are loaded into the
   **admin** container (`LoadServicesFromModulesPass` with the kernel's app type), not the **front**
   one — the two are separate Symfony containers built from separate compiler passes. A module front
   controller has no access to `molonion.context`, no Doctrine entity manager, nothing: calling
   `$this->module->get(...)` there throws `ServiceNotFoundException`. (The existing hooks that call
   `$this->get('molonion.context')`, e.g. from `OrderStatusUpdate`, only ever actually fire from admin
   order-status changes in this plugin, not genuine front-office checkout — so they never exercised
   this path; a prior draft of this note wrongly assumed they did.)

**Rule / what changed:**
- The OAuth `redirect_uri` is still a **module front controller**
  (`controllers/front/callback.php`, `Context::getContext()->link->getModuleLink('molonion',
  'callback', [], true)`) — front routes carry no employee token and no per-request nonce, so the same
  string is used verbatim at register, authorize and grant.
- But the front controller does **no grant**. It only verifies `state` (hash_equals, **without**
  consuming it) against the pending connect, then bounces (`Tools::redirectAdmin`) to the existing
  admin `LOGIN_RETRIEVE_CODE` route with `code`/`state`/`error` appended — that route's own
  action does the real check-and-consume-state + `MoloniApi::login()` call, since it runs with the
  full admin container + the employee's own session. On an invalid/missing state the front controller
  just 404s — it must never redirect to the stored (tokenized) admin URL for an unverified request,
  since that URL contains the employee's token.
- `Login::submit()` pre-builds that tokenized `LOGIN_RETRIEVE_CODE` URL while still in admin context
  (building one from front context is not possible: `Link::getAdminLink()` returns `''` there, and
  `_PS_ADMIN_DIR_` is undefined, so a bare-path `Tools::redirectAdmin()` call would be fatal — safe
  only because the stored URL is already absolute, which short-circuits `sanitizeAdminUrl()` on PS9
  before it ever touches that constant; PS 1.7/8's `redirectAdmin()` doesn't even have that branch).
- The pending connect (`state`, PKCE verifier, `redirect_uri`, the retrieveCode URL, the registered
  client id) lives in PrestaShop's own `Configuration` table, **global** (`updateGlobalValue`/
  `getGlobalValue`, not the default shop-scoped `get`/`updateValue`) so a multi-shop setup can't
  shadow one shop's pending connect with another's.
- `Link::getModuleLink(..., $ssl = null)` on PrestaShop 1.7 resolves to https only when **both**
  `PS_SSL_ENABLED` and the stricter `PS_SSL_ENABLED_EVERYWHERE` are on — under-reporting a shop that
  genuinely serves https behind a reverse proxy. Pass `true` explicitly (matches PS8/9's simpler
  check); it still falls back to http when SSL is off entirely, since `Link::getBaseLink()` ANDs it
  with the shop's own `ssl_enable` flag regardless. The front controller also sets `public $ssl =
  true;` so `sslRedirection()` doesn't 301 a legitimately-https callback hit down to http.
- The front controller overrides `displayMaintenancePage()`/`displayRestrictedCountryPage()` as
  no-ops — both run during `FrontController::init()`, before `postProcess()`, and would otherwise
  swallow Moloni's redirect whenever the shop is in maintenance mode or geoloc-restricted.
- `builder.php`'s `INCLUDE_DIRS` didn't list `controllers/` — a packaged release would have silently
  shipped without the new front controller. Added.

**Not verified end to end:** a live OAuth round-trip against the real Moloni ON authorize screen.
