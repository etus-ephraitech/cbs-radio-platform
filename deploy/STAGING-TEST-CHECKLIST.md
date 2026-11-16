# CBS Radio — staging test checklist

For testers on the staging site. Each step says what to do and what should happen. Tick it, or note what
happened instead (page address, what you clicked, a screenshot) and send it to the development team.

Staging is a test copy: nothing here is broadcast, nobody is charged, and no SMS or email goes out. Every page
shows a striped **STAGING** bar; if it's missing you're on the wrong site.

Try each part on a **phone** as well as a computer. Use made-up names, but realistic messages.

**You'll need:** the staging address, the site password (browser prompt), and your own staff sign-in.

---

## A. Homepage

- [ ] **A1.** Open the staging address. *Homepage loads with "Say it on air.", the occasions with prices, How it
  works, the shows, the gifts, Questions, and the gold "Ready when you are." band.*
- [ ] **A2.** Click each item in the top menu (Greetings, How it works, Gifts, Questions). *The page scrolls to
  that section.*
- [ ] **A3.** Open two or three of the Questions. *Each opens and closes; the answers make sense for CBS Radio.*
- [ ] **A4.** On a phone, use the Menu button. *The menu opens and every link works.*

## B. Sending a greeting (the main flow)

- [ ] **B1.** Click **Send a greeting**. *A page of occasions, each with its own icon.*
- [ ] **B2.** Choose **Birthdays**. *The form opens at step 2, Package. A summary on the right says "Not chosen
  yet" (on a phone the total is in a bar at the bottom).*
- [ ] **B3.** Click **Continue** without choosing. *"Choose a package to continue." appears; you stay on the step.*
- [ ] **B4.** Choose a package, **Continue**. *Step 3, Your message. The summary shows the package and its price.*
- [ ] **B5.** Leave the name and message empty, **Continue**. *Both are marked with a message; typing makes the
  message disappear straight away.*
- [ ] **B6.** Click one of the example messages under "Not sure what to say?". *The message box fills in, with
  the name you typed where it fits.*
- [ ] **B7.** Type a message longer than 500 characters. *A counter warns you; Continue refuses until it's shorter.*
- [ ] **B8.** *(Package with voice note.)* Record a voice note of about 10 seconds, play it back, then remove it
  and record again. *Recording, playback and removal all work; the browser asks for the microphone first.*
- [ ] **B9.** **Continue** to step 4, Gifts. *Gift tiles with prices; "Skip gifts" button.*
- [ ] **B10.** **Add** a gift, press **+** twice, then **−** once. *Quantity 2; the summary and total update.*
- [ ] **B11.** Add a **Flower bouquet** (or any delivered gift) and **Continue** without an address. *"Add a
  delivery address…" appears.* Fill the address and the recipient's phone, **Continue**.
- [ ] **B12.** Step 5, Your details: fill your name and phone. Choose a **show**, but no day, and click
  **Review**. *"Choose the day you would like it read on …" appears.*
- [ ] **B13.** Pick a day the show does **not** run (e.g. a weekday-only show on a Saturday). *That show
  greys out and can't be picked.* Pick a day it runs and select it.
- [ ] **B14.** **Review**. *Every part is listed (package, message, gifts, details, when it airs) with an Edit
  button each. Click one Edit, change something, and come back with Continue.*
- [ ] **B15.** **Continue to payment**. *"Pay for your greeting" page: the total equals the package plus the
  gifts, the reference (CBS-00…), the merchant code with a Copy button, and the order listed on the right.*
- [ ] **B16.** Click **View status**. *Timeline: Greeting received ✓, Waiting for payment (current). The gifts
  are listed as Pending Payment.*
- [ ] **B17.** Write down the reference. You'll use it in section E.

## C. Sending a gift on its own

- [ ] **C1.** From the homepage, **Send a gift**. Choose one, fill the form, **Continue to payment**. *"Pay for
  your gift" page with a GFT-… reference and the right amount.*
- [ ] **C2.** **View status**. *"Thank you for your gift", Waiting for payment.*

## D. Staff sign-in

- [ ] **D1.** Open `/login`. *The CBS Radio staff sign-in (navy panel and form), not a plain white page.*
- [ ] **D2.** Sign in with a wrong password. *"The credentials provided are incorrect.", your email stays filled in.*
- [ ] **D3.** Use the eye button. *The password shows and hides.* Turn Caps Lock on while typing. *"Caps Lock is on."*
- [ ] **D4.** Sign in properly. *You land on the admin dashboard.*
- [ ] **D5.** Open `/register`. *Page not found: nobody can create an account themselves.*
- [ ] **D6.** Account menu (your initials, top right) → **Change password**. *The change-password page; change
  it and sign in again with the new one.*

## E. Running the day (admin)

- [ ] **E1.** Dashboard. *"Needs attention" counts; Pending payments includes your greeting from section B.*
- [ ] **E2.** Type your reference (from B17) in the search box at the top. *The bookings list shows just that
  greeting.* Open it. *Message, gifts sent with it ("In this greeting's payment"), sender, payments.*
- [ ] **E3.** **Payments** → your payment → **Mark paid** → confirm. *Success message.* Back on the greeting's
  status page (B16 link): *Payment confirmed ✓; the gifts now show Paid.*
- [ ] **E4.** **Moderation queue**: your greeting is there. Add a note, **Approve**. *It leaves the queue. The
  status page now says "Approved and queued for the show".*
- [ ] **E5.** Try **Reject** on another greeting. *A confirmation box first; after confirming, its status page
  says "We couldn't air this greeting".*
- [ ] **E6.** **Gift orders**: the gifts from B are **Paid** and say "Paid with the greeting". **Mark fulfilled**
  one. *Its status changes to Fulfilled.*
- [ ] **E7.** **Notifications**: entries may be listed as failed or not sent at all. *Expected on staging (SMS
  and email are off).*

## F. Presenter portal

- [ ] **F1.** Account menu or side menu → **Presenter portal**. *Today's queue per show.*
- [ ] **F2.** If your approved greeting was booked for today, it's in its show's card; otherwise use **Other
  days** and pick its day. *The greeting appears with its message in large text.*
- [ ] **F3.** Play its voice note if it has one, then **Mark aired**. *It leaves the queue. The status page now
  says "Your greeting has been read on air".*

## G. Changing what the public sees

- [ ] **G1.** **Categories**: edit one category's description, save. *The homepage and the occasions page
  show the new text.*
- [ ] **G2.** **Packages**: change a price. *The new price appears on the homepage and in the greeting form.*
- [ ] **G3.** **Gifts**: deactivate a gift. *It disappears from the homepage, the gift shop and step 4 of the form.*
  Activate it again.
- [ ] **G4.** **Shows**: create a show that runs on weekends only. *It appears on the homepage with "Weekends"
  (or the days chosen), and in step 5 it can only be picked for Saturday or Sunday.*
- [ ] **G5.** **Message templates**: add an example for Birthdays. *It appears under the message box in step 3.*
- [ ] **G6.** **Settings → Public site**: set an announcement. *A gold bar with it appears on the public pages.*
  Change the headline. *The homepage headline changes.* Put both back.
- [ ] **G7.** **Settings → General**: set a contact phone. *It appears on the homepage and payment pages.*

## H. Look and feel

- [ ] **H1.** In the admin, switch to dark mode (moon icon). *Everything stays readable; switch back.*
- [ ] **H2.** On a phone, go through section B again quickly. *Nothing is cut off or overlapping; the bottom bar
  shows the total and the Continue button.*
- [ ] **H3.** Note anything that looks wrong, is hard to read, or uses words CBS listeners wouldn't use.

---

**When you report a problem, include:** the page address, what you did, what you expected, what happened, the
device and browser, and a screenshot if you can.
