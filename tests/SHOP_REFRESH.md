# Shop refresh verification

Run:

```text
php tests/shop-product-reader.php
node --test tests/cart-allocation.test.cjs
node tests/cart-allocation-browser.cjs
```

The browser test needs Playwright on NODE_PATH and Microsoft Edge (or set
PLAYWRIGHT_CHANNEL). It uses a PHP-rendered fixture and mocked refresh responses;
it does not write production data or test the database endpoint.

## Behavior

- Refresh runs sequentially in the current page, including temporarily hidden
  sources in the selected shop. Closing the page stops further requests; a
  request already sent may finish. Successful updates are saved immediately.
- Only exact, unambiguous JPY offers update prices. Explicit out-of-stock marks
  a source `unavailable`; it is excluded from the cart and new orders. The
  wishlist keeps the record, and editing/saving it restores availability.
- Failed fetches, redirects, challenges, unknown HTML, and seller ambiguity
  leave stored prices and availability unchanged.
- Platform locks and a three-second cooldown reduce duplicate requests across
  tabs. Each request has a 20-second timeout and a 4MB response limit.
- Saved HTML import parses without executing scripts or loading external assets.
  It verifies product identity and seller where applicable. It does not share
  browser cookies with the server or solve challenges.

## Current coverage

- Melonbooks: scoped price/inventory DOM selectors verified against a live public
  product page (product_id=3062038, 5280 JPY, low stock). A cart sample returned an
  age-confirmation page and was not used to modify live data.
- Suruga-ya: tested challenge detection. Product/Offer JSON-LD is supported, but
  real post-verification pages still need validation. Store-specific URLs must
  include tenpo_cd; an unqualified URL is only accepted for the main shop.
- Mandarake: Product/Offer JSON-LD requires seller.name matching the saved shop.
  The tested URL did not expose usable product data. Real confirmed HTML samples
  are still required before claiming full branch-level extraction support.
- No schema migration is needed. Uses existing price, availability_status,
  checked_at and updated_at columns. CSRF and existing admin authentication
  protect the refresh endpoint.
