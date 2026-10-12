# Module broke on PrestaShop 9 and misbehaved on 1.7

**Symptom:** PrestaShop 9.1+ refused to install the module (version not compliant). On 9.0 the whole back office answered 500 (`Cannot autowire service "MoloniOn\Controller\Admin\MoloniController"`), and once that was fixed the module pages failed with `Cannot call constructor` and then `"…\Login\Login" has no container set`. On 8.x the install reported a duplicate `ps_hook_module` key. On 1.7 a CLI uninstall stopped half-way (tabs left behind), and saving a product never reached Moloni ON in debug mode. Error flashes collapsed into a bare "Read More" that expanded into a full payload dump.

**Cause:**
- `ps_versions_compliancy` capped `max` at 9.0.0.
- PrestaShop 9 (Symfony 6.4) registers every class that *directly* `extends FrameworkBundleAdminController` as an autowired service, so the abstract `MoloniController` needed `MoloniContext` resolvable by class name. Our concrete controllers extend `MoloniController`, so PrestaShop's pass never autowires/autoconfigures them, and Symfony 6.4 only hands a container to controllers that are service subscribers. `FrameworkBundleAdminController` also lost its constructor in 9.
- `Module::install()` calls `enable()` first, so `Install::createCommon()` ran twice in one request, and PrestaShop's "already registered?" check reads a hook-id cache that is stale for hooks it just created.
- On 1.7, `Module::get()` returns `false` outside the back office (CLI), and the `catch (\Exception)` didn't catch the resulting `Error`.
- `Product::$product_type` only exists since PrestaShop 8.
- PrestaShop strips `style` and `onclick` from flash messages, and collapses alerts taller than 5 lines.

**Rule / what changed:**
- Compliancy `max` is `_PS_VERSION_`.
- `core.yml` aliases `MoloniOn\MoloniContext` to `@molonion.context`; `MoloniController` only calls `parent::__construct()` when it exists.
- `config/services/admin/controllers.php` (imported with `ignore_errors: true`, since 1.7 has no PHP loader for module services) turns on autowire + autoconfigure for `MoloniOn\Controller\*` **only on Symfony ≥ 6**. Never unconditionally: on 8 the subscriber container replaces the full one and `$this->get('molonion.*')` breaks. `services-9.yml` is not an option — only 9.2+ reads version-specific services files.
- `Install::registerHooks()` checks `hook_module` directly per shop and only registers what is missing.
- `Remove::removeLogin()` falls back to `Db` when Doctrine isn't available and catches `\Throwable`.
- Use `Helpers\ProductType::hasCombinations()` instead of reading `product_type`.
- Error details in flashes use classes (`.moloni-error-toggle` / `.moloni-error-details`), hidden by `app.css` and toggled in `app.js`.
- `src/Entity` has no `index.php` (PrestaShop 9 deletes it, because API Platform `require_once`s every file there and the guard's `exit` would kill the request); `auto-index` excludes it.
- Assets get `?v=<mtime>` from PrestaShop 8 on; 1.7's `Media::getMediaPath()` can't resolve a query string (the asset wouldn't load).

**Testing note:** the local `vendor/` holds dev tools (php-cs-fixer pulls in Symfony components) that clash with PrestaShop 9.0's Symfony on uninstall (`Stopwatch not found`). Test with `composer install --no-dev`, like the release.

**Verified** on fresh installs of 1.7.6.9 (PHP 7.2), 1.7.8.11, 8.0.5, 8.1.7, 8.2.8, 9.0.0 and 9.2.0 (PHP 8.5): install / disable / enable / uninstall / reinstall with no duplicate hooks, every module page 200 against the live API, product save → Moloni ON on 1.7.6 and 9.2.
