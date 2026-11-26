# Partner shop gifts (Katale Plus)

Gifts from Katale Plus are delivered by Katale, so they are bought differently from our
own e-cards and airtime: the customer pays **Katale**, not us.

1. **Catalogue.** Add the vendor under Admin > Gift Vendors with Integration
   *Katale Plus*, name it `Katale` (so the slug is `katale`), leave the address empty
   (it defaults to `https://kataleplus.com/api/v1/partner`) and put the partner key in
   `.env` as `vendor.katale.apiKey`. `php spark gifts:sync` (or the *Sync now* button)
   mirrors every page of the catalogue into the gifts table: title, picture, the offered
   price (or the selling price when there is no offer), the notice Katale needs, and the
   units left. A sync that cannot read the whole catalogue is reported as failed; it never
   stores a partial one as if it were complete.
2. **Cart.** Katale gifts appear on the gifts page under *Gifts delivered to their door*,
   with an *Add to cart* button, instead of in the pay-us form. The cart is kept in the
   visitor's session (up to 20 different gifts, 100 of each).
3. **Checkout.** *Continue to delivery and payment* re-reads each gift from Katale (stock,
   price, inventory), opens a checkout session with an `Idempotency-Key`, and sends the
   visitor to the address Katale returns. The visitor gives the delivery address and
   arrival dates and pays on Katale. The final price is Katale's; ours is an estimate.
4. **Coming back.** `gifts/checkout/{token}` asks Katale for the real state (at most every
   8 seconds per purchase) and shows payment and delivery progress. Returning to the
   site, or an order id in the address, never counts as payment. Only Katale's status
   endpoint saying `paid` does, and a paid purchase is never put back to unpaid.
5. **Staff.** Admin > Shop Purchases lists every purchase with its state, final amount,
   Katale's reference and each gift's delivery state, with *Check with the shop now*.
   `php spark gifts:reconcile` (cron, every 10 minutes) finds customers who paid and
   never came back, because Katale sends no webhook.

Things to know:

- Keep the partner key in `.env` only. It is never sent to the browser, stored, or logged.
- Ask Katale to register the site's https address for the key, so the return link works.
  On a machine without https the return link is left out and Katale uses the registered one.
- Katale allows 60 requests a minute per IP, and 10 checkouts a minute. The cart reads each
  gift once per checkout, so a 20-gift cart uses 21 requests.
- A hosted-checkout gift cannot be ordered through the pay-us form, even by a hand-made
  request. A greeting booking cannot have a Katale gift attached (they are separate checkouts).
- Test a purchase with Katale before enabling it for customers, as their guide asks.

- `php spark gifts:reconcile` — asks each partner shop (Katale) about purchases still
  waiting to be paid or being delivered. Schedule it every 10 minutes.
