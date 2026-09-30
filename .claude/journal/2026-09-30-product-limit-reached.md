# Product create fails once the plan's product limit is reached

**Symptom:** with a full Moloni ON plan, every product create failed with a generic error. This covered product save (`addProductsToMoloni` on), the Tools "Export products" run, and a missing order-line or shipping product while creating a document. The logs showed "Error saving Moloni ON product" / "Error creating product (REF)" / "Error creating shipping product". The export retried every remaining product and filed each one as an error inside the INFO log.

**Cause:** Moloni ON limits products per plan. `productCreate` then returns `errors: [{ msg: "Number of items is over the allowed limit.", field: "*" }]` with `data: null`. The HTTP layer does not throw on payload `errors`, and `MoloniProductSyncAbstract::insert()` only threw a generic `MoloniProductException`, so the real message stayed inside `data.mutation`. The same count is available on the company query (`limits { resource remaining }`, the entry with `resource: "products"`). The module only selected `active`/`moduleId`, and `Context\Company` dropped every entry outside its module allow-list.

**Rule / what changed:**
- `Context\Company::canCreateProducts()` reads the `products` entry (`remaining > 0`). If there's no entry, or the company failed to load, it doesn't block and Moloni ON decides.
- `Exceptions\Product\MoloniProductLimitException` (a `MoloniProductException`, so existing catches still apply) holds the message (`MESSAGE`, `{0}` = reference) plus `isApiError($mutation)`, which matches Moloni ON's exact message in the `productCreate` response.
- `MoloniProductSyncAbstract::insert()` and `OrderShipping::insert()` check first and map the API error. `OrderShipping` throws a `MoloniDocumentShippingException` with the same message.
- Product save and the export log it as a **warning**. The export still tries each product and logs one warning per page with the references. Nothing is remembered for the rest of the run, to keep it simple. Document creation still fails, now with the clear message (`OrderProduct` keeps it when wrapping into `MoloniDocumentProductException`). Using a generic product instead would change what gets invoiced.
- Translations: the new strings are registered in `DummyTranslations::errors()` and added to both `ModulesMolonionErrors` XLIFF files. The two "Product Properties module is not active" skip warnings were registered but had no XLIFF entries, so they were added too.

**Not verified end to end** against a company at its product limit.
