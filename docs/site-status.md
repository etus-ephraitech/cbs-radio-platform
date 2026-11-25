# Coming soon and maintenance modes

Under **Admin > Settings > Site status** staff can close the public site without a
deploy:

- **Live** (default): everything works.
- **Under maintenance**: visitors get a "Back soon" page with HTTP 503 and a
  `Retry-After` header (counting down to the expected return time, clamped to between
  one minute and 24 hours, one hour when no time is set), so search engines treat it as
  temporary.
- **Coming soon**: visitors get an "Opening soon" page with HTTP 200.

Both pages show the optional message and the expected time (`Expected back` or
`Opening`), are marked `noindex`, are never cached, and carry no navigation or links to
the closed pages. The station's contact details are shown when set. The **Preview**
buttons on that tab show each page without changing anything.

What stays working in every mode: the admin area, the presenter portal and the payment
provider callback (so a payment already in flight is still recorded). Signed-in staff
see the real site with a red reminder banner; every admin page shows a yellow reminder
while the site is closed, and the dashboard's Configuration row flags it.

The expected time is entered and stored in the station's timezone, so
`app.appTimezone` in `.env` must be `Africa/Kampala`. Reaching the time does **not**
open the site by itself: staff switch it to Live when ready.

