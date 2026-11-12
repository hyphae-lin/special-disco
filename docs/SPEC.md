# Hexxsy spec

> Copied from the working spec on claude.ai (Oct 13, 2026). Decisions get made there; this file is what Claude Code builds from. If they disagree, ask before building. Reader rules: [CODE_OF_CONDUCT_READERS.md](CODE_OF_CONDUCT_READERS.md).

## Overview

Hexxsy is a multi-vendor marketplace where approved readers sell tarot readings and other divinations, sold as entertainment. It runs on WordPress and WooCommerce at hexxsy.com, hosted on DreamHost.

All the marketplace logic lives in one custom plugin, Hexxsy Vendors, version 0.4.0 (needs WordPress 6.5+, PHP 8.1+, WooCommerce active). This spec describes what that code does as of the last push on Oct 12, 2026, then flags what's missing or undecided.

Source: [hyphae-lin/special-disco](https://github.com/hyphae-lin/special-disco). A second repo, [special-disco-wordpress](https://github.com/hyphae-lin/special-disco-wordpress), is an unmodified fork of WordPress core with no Hexxsy code in it.

## Roles and accounts

Every person on the site has a WordPress account. Nobody buys or applies as a guest.

| Role | How they get it | What they can do |
| --- | --- | --- |
| Customer | Creates an account at checkout or on My Account | Buy readings, answer intake questions, see the My readings tab, download delivered PDFs |
| Applicant | Any logged-in account that submits the vendor form | Wait. Status is stored as pending, rejected or approved; no selling until approved |
| Vendor (`hexxsy_vendor`) | Granted when the admin approves | Everything a customer can, plus three vendor tabs in My Account. Only `read` and `upload_files` in wp-admin |
| Admin | Anyone with `manage_woocommerce` | Review applications, approve, reject or revoke, set default and per-vendor commission |

Guest checkout is forced off in code, so it stays off even if someone flips the WooCommerce setting back on.

Deactivating the plugin removes the vendor role but keeps each person's status, so reactivating restores every vendor.

## Vendor flow

A reader goes from account to selling in four steps, and only the admin's approval opens the door.

1. **Apply.** The `[hexxsy_vendor_application]` shortcode shows a form to logged-in users only. Fields: shop name (required, 80 characters), about you (required), specialties, experience, and a required checkbox agreeing that readings are for entertainment only and to the vendor terms.
2. **Wait.** Status becomes pending. The admin gets an email linking to WooCommerce > Vendors. One application per account; a second submit is ignored.
3. **Decision.** The admin approves (grants the vendor role) or rejects. Either way the applicant gets a short email. An approved vendor can later be revoked (see Revoking a reader).
4. **Sell.** Approved vendors get three tabs in My Account.

| Tab | What the vendor does there |
| --- | --- |
| Readings I offer | Add or edit a reading (name, description, price, written or live, turnaround days or call length, intake questions, one image). Pause, resume or remove |
| Reading orders | See their latest 50 orders, each customer's intake answers, what they earn per line, and a running "earned so far" total. Write and deliver written readings here |
| Shop profile | Edit shop name, bio and specialties |

A new reading goes live the moment it's saved. Nobody reviews it first.

The admin can also make readings in the normal WooCommerce product editor, which has the same reading fields.

## Invitations and waitlist (decided Oct 13, built Oct 31, 2026 in 0.6.0, untested on a live site)

Readers join by invitation only, and the number of readers is capped by a setting. Decided Oct 13, 2026. This replaces the open application in step 1 above.

The cap starts at 12 to keep administration manageable at launch. It is a starting point, not a permanent limit: the admin will raise it as Hexxsy grows (clarified Oct 25, 2026). Nothing in code or copy may assume 12.

**Cap**

- A "Reader seats" setting on WooCommerce > Vendors, set to 12 at launch. Any whole number from 1 up; raising it opens new rooms immediately.
- Approved readers plus unused invites can't exceed the cap, so you never invite more people than you have room for.
- Revoking a reader, or an invite expiring, frees a seat.
- The Approve button is disabled when every seat is taken.
- As built: applications waiting for review also hold a seat, alongside approved readers and open invites. Otherwise an invite turning into an application would free its seat and you could over-invite. Approve is only blocked by approved readers reaching the cap.

**Invites**

- You enter an email on the Vendors page. The site emails that person a single-use link.
- Links expire after 14 days.
- The link only works for an account with the same email address. The person creates one if they don't have it.
- An invite also works for someone previously rejected, so you can bring them back.

**Application**

- The application form only opens with a valid invite link. Without one it says Hexxsy readers join by invitation, and points to the waitlist.
- Invited readers still fill in the full application: shop name, bio, specialties, experience.
- The agreement checkbox changes to accepting the Hexxsy reader code of conduct, with a link to it.
- You still approve or reject each application by hand.

**Waitlist**

- A public waitlist form (its own shortcode) asks for name, email, specialties and a short note. No account needed.
- The person gets a short confirmation email. One entry per email address.
- The Vendors page lists the waitlist, oldest first, each with an Invite button and a Remove button.
- Waitlist entries are personal data, so they belong in the privacy policy (gap 10).

**As built (Oct 31, 2026)**

- WooCommerce > Vendors has Reader seats, plus 3 page choices: the application page (invite links point there), the Ledger page (people without an invite are sent there) and the code of conduct page (linked from the agreement checkbox). The Ledger section lists seats, open invites (with Cancel) and the waitlist (with Invite and Remove).
- An invite link survives a trip to log in or sign up: the site remembers it in a cookie for 14 days.
- Only a hash of each invite token is stored, so a copy of the database can't be used to open applications.
- The waitlist form has a hidden spam trap and gives the same reply whether or not the email is already on the list, so it doesn't reveal who's signed.
- Using an invite removes that person's waitlist entry.
- Theme patterns: "Become a reader" (application page) and "The Ledger" (waitlist page), each with Hexx's lines beside the form.
- Wording in the forms and emails is still in the plugin code. It moves to the Hexx's lines screen when that's built.

## Readings and intake

Every reading is a WooCommerce simple product, marked virtual and sold one per order. The product's author is its vendor.

| Format | Vendor sets | Shop button | What the customer fills in |
| --- | --- | --- | --- |
| Written reading | Turnaround in days (1 or more) | Request reading | Their question, always (up to 2,000 characters), plus any the vendor ticks: birth date, birth time, birth place, name of the person the reading is about |
| Live call | Call length in minutes (5 or more) | Book a call | Nothing yet |

**Live calls are off sale (decided Nov 11, 2026, built in 0.8.0).** Until booking exists, live calls can't be bought. "Customers can buy live calls" on WooCommerce > Vendors is off by default. While it's off, live calls are left out of the shop, the rooms list and related products, their own pages show Hexx's line in place of the button, and WooCommerce drops them from any cart they're already in. Readers can't add new live calls; ones made earlier stay in Readings I offer, marked "Hidden until live calls open". Turn the setting on when booking is built.

Shop-page buttons send people to the reading's own page instead of adding to cart, because intake answers have to be collected there.

Every intake field is required. Answers show in the cart and checkout, and are saved on the order line where the vendor reads them.

At checkout the reading's format, turnaround and call length are copied onto the order, so a vendor editing the product later doesn't change what an earlier customer bought.

## Delivery and customer library

Written readings are written on the site, delivered once, and kept forever as a PDF the customer can download.

1. **Paid.** The order moves to Processing. The due date starts: payment date plus the turnaround days, counted as calendar days.
2. **Draft.** Under each paid order line in Reading orders, the vendor writes the reading and saves drafts. The customer sees nothing yet.
3. **Deliver.** After a confirm prompt, the text locks for good. The customer gets an email with the PDF attached, a line about entertainment only, and a link back to their account. An order note records the delivery.
4. **Complete.** When every line on the order is a delivered written reading, the order flips to Completed on its own. Orders with any live call stay open.

The PDF is US Letter, Helvetica, with a "For entertainment purposes only. hexxsy.com" footer and a "Read by \[shop\] for \[first name\], \[date\]" line. It can't show emoji, tarot or astrology symbols, or anything outside Western European text. Example: `docs/sample-reading.pdf`.

Only the customer, that reading's vendor, or an admin can download a PDF, and only after delivery.

The customer's **My readings** tab (first in My Account) lists every reading bought, with reader, order date and status: "Waiting for payment," "In progress, due \[date\]," a Download PDF button, or "Live call."

## Money

The site keeps 20% of each reading by default; the reader gets the rest. Hexxsy holds the customer's payment and pays the reader later (decided Oct 14, 2026). Stripe Connect is approved (Oct 14, 2026). Readers who set up payouts are paid by Stripe transfer; anyone else is paid by hand.

- **Rate.** Default set on WooCommerce > Vendors (0 to 100%). The admin can override it per vendor on their user profile.
- **Base.** Commission is taken on the line total after discounts, before tax. Exception: the welcome offer, which the house absorbs (see Welcome offer below).
- **Rounding.** Math runs in cents. The commission is rounded and the reader gets exactly what's left, so no cent is lost or invented. On a $45.00 reading at 20%, the site keeps $9.00 and the reader earns $36.00.
- **Records.** At checkout every order line is stamped with its vendor, the rate in force, the commission and the reader's earnings. A later rate change never rewrites past orders. Payouts will read these stamps.
- **What readers see.** Reading orders shows three totals across all their orders: on hold, ready to pay, and paid to them. Each order line shows its own payout state.

A product with no approved vendor as its author is treated as the site's own: no split recorded, the site keeps it all.

### Holding payment and payouts

The customer pays Hexxsy. Hexxsy keeps the money until the reading is delivered and a hold period has passed, then pays the reader their share.

| State | When |
| --- | --- |
| On hold | Order paid, reading not delivered yet. Or delivered, and the hold period hasn't ended |
| Ready to pay | Delivered, and the hold period has ended |
| Paid | The admin recorded the payout |
| Nothing owed | Order refunded or cancelled before payout |

- **Hold period.** 7 days after delivery by default, set on WooCommerce > Payouts. The release date is stamped on the order line at delivery, so changing the setting doesn't move earlier readings.
- **Live calls** stay on hold until booking exists, since nothing marks them delivered.
- **Refunds** come off the line before the split, at the rate stamped at purchase. A $15.00 refund on a $45.00 reading at 20% leaves the reader $24.00 and the site $6.00.
- **Payouts.** WooCommerce > Payouts lists each reader's Stripe status and their on-hold and ready-to-pay totals. For a reader set up on Stripe, Send via Stripe makes one transfer per order line, tied to the customer's charge. For anyone else, the admin sends the money outside the site and clicks Mark paid. Either way each line is stamped with the date and amount, and an order note is added. Only lines that were ready when the page loaded are paid.
- **Refunded after payout.** If a customer is refunded after the reader was paid, the Payouts page lists the overpaid amount. Hexxsy has covered the refund. Reverse transfer takes it back from the reader's Stripe account; hand payments are recovered by hand.
- **Revoked readers** still appear on the Payouts page, so money they earned before revoking is still paid.

### Stripe Connect

- **Charges.** Customers pay Hexxsy through the WooCommerce Stripe Payment Gateway. Hexxsy is the seller of record (separate charges and transfers).
- **Keys.** The plugin uses the gateway's secret key, so test and live mode always match how customers were charged. `HEXXSY_STRIPE_SECRET_KEY` in `wp-config.php` overrides it. Test and live connected accounts are stored separately, so test accounts never get live money.
- **Reader onboarding.** Reading orders shows a Set up payouts button. It creates a Stripe Express account and sends the reader to Stripe's hosted signup, which collects ID, bank and tax details. When they come back, the site checks the account can receive transfers. Readers can open their own Stripe dashboard from the same place.
- **Transfers.** Each transfer is tied to the customer's charge, so it can go out before that money has settled. Every transfer has an idempotency key per order line and amount, so a double click or a retry never pays twice. If Stripe refuses partway through, lines already sent stay recorded and the button sends the rest.
- **Not built.** No webhooks: reader status is checked when they return from Stripe or open Reading orders. Readers' own bank payouts run on Stripe's default schedule.
- **Hexxsy's own Stripe payouts** must not sweep readers' held money to Hexxsy's bank. With separate charges and transfers, the whole charge sits in Hexxsy's Stripe balance until the transfer, so an automatic monthly payout would take readers' money with it. Hexxsy's payouts go manual (or only pay out commission) before Connect transfers start.

### Welcome offer (decided Oct 25, 2026, built Oct 31, 2026 in 0.5.0, untested on a live site)

New customers can sign up by email from the homepage and get a discount on their first purchase. The house absorbs the whole discount: readers are paid as if no discount was used.

**Signup and code**

- A short email signup sits in the homepage hero, below the main "Walk the rooms" button. It does not replace it. Wording is Hexx's and editable (see Editable from the back end).
- Signup needs a clear consent checkbox for marketing email. Every email carries an unsubscribe link.
- An autoresponder sends a welcome email with a unique, single-use WooCommerce coupon code. The email's subject and body are editable.
- One welcome code per customer account and per email address. A code can't be combined with other coupons.

**Tool: MailPoet (decided Oct 25, 2026)**

MailPoet runs inside WordPress, handles the signup form, the welcome automation and the coupon. Its coupon block, in an automation email with "Create new" selected, generates a unique code for each subscriber ([MailPoet docs](https://kb.mailpoet.com/article/how-to-add-a-generated-woocommerce-coupon-to-emails-in-the-block-email-editor)).

- **Requirements.** WooCommerce 10.8 or later, coupons enabled in WooCommerce settings, and MailPoet's block-based email editor.
- **Coupon settings to use.** Discount type: Fixed cart discount (not Fixed product, which takes the amount off every item). Minimum spend: see Minimum spend below. Usage limit per coupon: 1. Usage limit per user: 1. Individual use only: on. Limit to recipient's email: on. Expiry in days and category restrictions as decided (see Open).
- **Sending.** Not through DreamHost's own mail, which tends to land in spam. Use MailPoet's sending service or an outside sending service such as Amazon SES.
- **Plan: MailPoet Business (bought Oct 31, 2026).** Installed on the site. Confirmed in the editor: the Coupon block is available in automation emails, and its Usage restriction panel has Minimum spend.
- **Where to find it.** WooCommerce coupons must be enabled (WooCommerce > Settings > General). Then MailPoet > Automations, the "Welcome new subscribers" automation, its Send email step, and the Coupon block in the email editor, set to "Create new." It's a block, not a shortcode.
- **Still to check.** The Business plan's subscriber and monthly sending limits.

MailPoet does not know about Hexxsy's commission split. Two pieces are Hexxsy plugin work:

- **Recognize welcome coupons (built).** MailPoet adds no marker of its own. It writes a description on each coupon it generates: "Auto-generated coupon by MailPoet for email: 12", where 12 is the MailPoet email's ID. The plugin reads that description. On WooCommerce > Vendors, "Welcome offer emails" lists the email IDs that count; left empty, every coupon MailPoet generates counts. Fill it in before sending any other MailPoet email with a coupon, or that coupon will be house-absorbed too. Any coupon can also be marked or unmarked by hand with the "Welcome offer" checkbox on its edit screen in WooCommerce > Coupons, and the checkbox wins.
- **Guard the cap (built).** MailPoet allows any percentage up to 100 and any flat amount. At checkout the plugin caps the welcome discount on each reader's line at the site's commission on that line (see Money). Products the site sells itself have no cap.

**Money**

- The reader's earnings on a welcome-coupon line are figured on the price before the discount, at the rate in force. The site's commission is what's left of what the customer actually paid.
- Example at 20%: a $40.00 reading with 15% off. The customer pays $34.00. The reader earns $32.00, as with no coupon. The site keeps $2.00.
- The line is stamped as a welcome-offer line, with both the undiscounted and paid amounts, so payouts and reports can tell them apart.
- **The discount must never exceed the site's commission on the line.** Above it, the site owes the reader more than the customer paid (25% off a $40.00 reading: customer pays $30.00, reader is owed $32.00). Stripe transfers tied to a charge can't send more than that charge. For a percentage, that means no more than the commission rate. For a flat amount, the amount must be no more than the commission on the cheapest discounted item ($5 off is safe on readings of $25 or more at 20%). The plugin caps the discount on any line where it would go over, so the house never takes less than nothing.
- Refunds on a welcome-coupon line (built and approved Oct 31, 2026): a refund takes the same fraction off the reader's share as it took off what the customer paid. Half the paid amount refunded, the reader loses half their share; a full refund leaves them nothing. Example: $40.00 reading, $5 off, customer paid $35.00, reader's share $32.00. A $17.50 refund leaves the reader $16.00.

**Shelves (decided Oct 31, 2026).** The welcome code is good on everything, on any cart of $25 or more. No category restrictions on the coupon. Readers whose Wares are discounted are still paid in full, since the house absorbs the discount.

**Expiry (decided Oct 31, 2026).** Codes expire 30 days after they're sent. Set in the coupon block, adjustable like the amount.

**Minimum spend (decided Oct 31, 2026)**

The coupon only works on carts over a minimum spend. WooCommerce splits a Fixed cart discount evenly across items, not in proportion to price (corrected Oct 31, 2026, from the WooCommerce source). So a cheap item can be handed more discount than its commission: $5 off a $20 reading and a $5 Ware puts $2.50 on each, and the Ware's commission is $1.00. The plugin guard caps the Ware at $1.00, and WooCommerce moves the other $1.50 to the reading, which has room. The customer still gets the full $5.

- **The rule.** Minimum spend is at least the discount divided by the commission rate. At 20%, that's 5 times the discount: $5 off needs a $25 minimum, $10 off needs $50. Then the cart's total commission always covers the discount, and with the guard moving it between lines, the customer always gets all of it.
- **Both numbers are changeable.** The discount amount and the minimum spend are admin settings, not fixed values. They live in MailPoet's coupon block, and a change applies to codes sent after it; codes already sent keep the terms they went out with. Nothing in code, emails or Hexx's lines may assume a particular amount. Any text that names the discount or the minimum reads it from the setting, or is edited along with it.
- **Help the admin get it right (built).** The Vendors page has a "Welcome offer check". It shows the lowest commission rate in force (the site default or any approved reader's override, whichever is lower) and the latest welcome coupon generated. It warns if that coupon's minimum spend is below the safe minimum, if its type isn't Fixed cart, or if a percentage discount is above the lowest rate. It can only check a coupon after MailPoet has generated one, so send yourself the welcome email first.
- **The plugin guard does the real work.** Minimum spend alone doesn't keep each line safe; it only makes sure there's enough room in the cart overall. The guard caps any line that would go over. Cases it catches:
  - A cheap item in the cart, which WooCommerce's even split would push past its commission.
  - A reader whose commission override is lower than the rate the minimum was set for. A $25 reading at 10% has $2.50 commission, less than $5 off.
  - A coupon restricted to some shelves (not the plan now, but possible if the settings change) with other items in the cart. Minimum spend counts the whole cart, but the discount lands only on readings: a $10 reading plus a $20 Ware clears $25, and the full $5 comes off the $10 reading, where the commission is $2.00.
  - Rounding to the cent. WooCommerce hands out the last few cents of a split one at a time, past the guard, so a line can go over by a cent. On that line the reader's share is capped at what the customer paid, so the house never goes below zero.
  - Someone changing the coupon to Fixed product, or raising the discount without raising the minimum.
- When the guard caps a line and no other line has room, the customer gets a smaller discount. The house never pays out of pocket.
- **Limits of the build.** The guard works on the cart and checkout. A coupon an admin applies to an order by hand in wp-admin isn't capped. The cap assumes prices are entered without tax (WooCommerce > Settings > Tax).

**Open**

- Starting discount amount (leaning $5). Minimum spend starts at $25 (decided Oct 31, 2026), which supports up to $5 off at a 20% commission. Both stay adjustable.
- Signup email addresses are personal data and belong in the privacy policy (gap 10).

## Policies

### Written readings: cancellations and refunds (decided Nov 11, 2026, built in 0.8.0, untested on a live site)

The customer-facing wording is in [REFUND_POLICY.md](REFUND_POLICY.md), and the theme's "Refund policy" pattern lays it out as a page.

| Situation | What happens |
| --- | --- |
| Customer cancels within 24 hours of paying, reading not delivered | Full refund. The customer does it from My readings |
| After 24 hours, reading not yet due | The order stands |
| Reading passes its due date undelivered | Reader and customer are both emailed. The customer can cancel for a full refund from My readings, or wait |
| Still undelivered 7 days after the due date | Cancelled and refunded in full, automatically |
| Reading delivered | No refund. A customer with a real complaint writes to Hexxsy within 7 days of delivery (the payout hold), and the admin decides case by case |
| Reader revoked before delivering | Refunded in full, or the reader finishes it (see Revoking a reader) |

- **Settings.** WooCommerce > Vendors has Cancellation window (hours, 24), Remind the reader (days before due, 1) and Refund late readings after (days, 7). 0 turns the window or the reminder off; 0 days of grace refunds a reading the moment it's late. A Refund policy page setting chooses the page the product note links to.
- **Reminders.** Once an hour the site checks every paid, undelivered written reading. One day before the due date the reader gets a reminder (skipped when the turnaround is one day, since it would land on the order itself). When the due date passes, the reader and the customer each get one email, and the customer's names the date the automatic refund will happen.
- **Cancelling.** My readings shows "Cancel for a full refund" on a reading while the customer is allowed to use it: inside the window, or once it's late. A late reading shows "Late. It was due [date]".
- **One refund path.** Customer cancellations, the automatic late refund and revoking a reader all go through the same code. The order line is marked cancelled, with the reason. What's left on the line, with its tax, is refunded through the payment gateway as a WooCommerce refund, so the payout ledger sees it and the reader's share drops to nothing. The customer and the reader are each emailed. If the other readings on the order are all delivered, the order completes; if every line is refunded, WooCommerce marks the order refunded.
- **If the gateway refuses.** The line stays cancelled, so the reader can't deliver something the customer was told is off. The order gets a note, and the admin gets an email with a link to refund it by hand.
- **A cancelled line can't be delivered.** Reading orders shows "Cancelled and refunded. Nothing to deliver." A line refunded in full by hand in WooCommerce counts as cancelled too.
- **Exceptions after delivery** are made by hand with WooCommerce's Refund button on the order. The reader's share shrinks with the refund; if they were already paid, WooCommerce > Payouts lists what to take back.
- **On the reading's page.** A short note above the button gives the cancellation window and links to the policy page. It's one of Hexx's lines.
- **Shortcode.** [hexxsy_setting key="cancel_hours"], "late_refund_days" or "hold_days" prints the number, so the policy page never disagrees with the settings.
- **Emails.** Each is its own WooCommerce email (see Emails under Editable from the back end).

Limits of the build:

- The hourly check runs on WordPress's own scheduler, which only fires when someone visits the site. On a quiet day an email or refund can run hours late. A real cron job on DreamHost calling wp-cron.php fixes that.
- WooCommerce sends its own "order refunded" email as well as Hexx's. Turn it off in WooCommerce > Settings > Emails if one is enough.
- Due dates still count calendar days, weekends included.
- The cancel button asks nothing about why. No partial refunds from the customer side.
- Whether a refunded customer's welcome code is reissued isn't decided. As built it stays used.
- The contact address for complaints isn't decided. The policy pattern has a placeholder.
- No checkout tick box for the written-reading policy. The live call acknowledgment below is still not built either.

### Revoking a reader (decided Nov 11, 2026, built in 0.8.0, untested on a live site)

Revoking closes a reader's room. It is no longer the same action as rejecting an applicant.

- **Readings come off sale at once.** Every published product of theirs is set to draft. As a backstop, a product whose author applied to read and isn't an approved reader can't be bought at all, so a sale can never be recorded with no split.
- **Open orders: the admin chooses each time.** If the reader has paid orders they haven't delivered, the Vendors page shows how many and offers two buttons. "Revoke and refund open orders" refunds each one in full and emails the customers. "Revoke, let them finish" leaves the orders with the reader.
- **Finishing.** A reader left to finish keeps the Reading orders tab and nothing else: no Readings I offer, no Shop profile, no new sales. They're listed on the Vendors page under "Revoked, finishing open orders", with a "Refund what is left" button. They're paid for what they deliver, as usual. Anything that runs late follows the late-reading rule. When the last order is delivered or refunded they move to Rejected on their own.
- **Live calls** on open orders are refunded either way, since nobody can fulfil them.
- **Seats.** A revoked reader frees their seat straight away, finishing or not.
- **Email.** The reader gets one email saying the room is closed, and either that open orders were refunded or how many they still owe. Both messages are on the "Hexxsy: Room closed" email.
- **Coming back.** Approving them again restores the tabs. Their readings stay paused until they resume them.
- **Money already earned** is still paid (see Holding payment and payouts).

### Live calls

The live call policy is written in the README but nothing in the code enforces it yet, since booking doesn't exist.

| Situation | Customer gets |
| --- | --- |
| Customer cancels 24+ hours before the call | Full refund |
| Customer cancels inside 24 hours | No refund |
| Customer doesn't show | No refund |
| Reader cancels, any time | Full refund |
| Reader doesn't show | Full refund |

**Policy acknowledgment (decided Oct 13, 2026, not built).** Customers must tick a required box agreeing to the live call policy before they can pay or book:

- **At checkout**, only when the cart holds a live call. Checkout won't go through unticked.
- **When booking a time**, as part of the scheduler (not built yet).
- The tick is saved on the order with a date and time, so there's a record if a customer disputes a no-show charge.

Wording (approved): *I understand I can cancel for a full refund up to 24 hours before my call. Cancellations inside 24 hours, and missed calls, are not refunded.*

The entertainment-only framing appears in three places: the vendor application checkbox, the delivery email, and the PDF footer. The application also mentions "vendor terms," but no terms page exists in the repo. A reader code of conduct is now drafted and will replace that wording.

## Editable from the back end (decided Oct 13, 2026, not built)

Every customer-facing word, image and label on the site must be editable from wp-admin. Nothing visible is hard-coded in theme or plugin templates. If a string appears on screen or in an email, there is a box in the admin that controls it.

This applies to the custom theme (see "Theme" under gaps) and to everything the Hexxsy Vendors plugin outputs.

**Context.** The site has a host character, Hexx, the Proprietress, whose voice runs through the site. Readers' pages are "rooms," and each room sorts its offerings onto 4 "shelves": Sittings (live readings), Letters (written or recorded readings, delivered later), Papers (digital goods) and Wares (physical goods). Design mockup: Hexxsy homepage canvas on claude.ai. The product side of Papers and Wares isn't specced yet; today the plugin only knows written readings and live calls.

| Area | What must be editable | How |
| --- | --- | --- |
| Hexx's lines | Every line she speaks in templates: shelf intros, the empty-rooms note and ledger link, both ledger messages (rooms open / all rooms full), offering-page asides, 404, footer sign-off, booking and order confirmations | One admin settings screen ("Hexx's lines"), grouped by page, one labeled field per line. Advanced Custom Fields options page or equivalent |
| Static pages | Homepage welcome, house notice (disclaimer), Hexx's page (portrait, bio, Rules of the House, contact box) | Block-editor pages built from the theme's block patterns. Nothing is locked (decided Nov 11, 2026): words, images and layout all change in the block editor |
| Shelves | The 4 category names, Hexx's line for each, button labels ("Book," "Request," "Take a copy," "Add to basket") | Editable per category, e.g. product category name, description and term meta |
| Readers | Everything a reader writes: door line (one sentence on their homepage card), introduction, "How I read," a note per shelf, product descriptions, "Before you buy" | Reader profile and product fields, through the approval queue below |
| Hexx's note | One or 2 lines from Hexx about each reader, shown in their room | Admin-only field on the reader's profile. Readers can never see an edit box for it |
| Masthead | The "Hexxsy" title at the top of the homepage (and the smaller one on inner pages) | An admin setting with 3 modes: (1) styled text, the default; (2) a logo image in place of the text; (3) the text over a background image. The image modes take uploads from the media library. Mode 3 adds a darkening overlay with an adjustable strength (0 to 80%) so the title stays readable over any picture. The site name stays in the page's code as the main heading even when a logo image replaces it, for screen readers and search engines |
| Navigation and footer | Menu items, footer text, both disclaimers (Hexx's in-world version and the plain one) | WordPress menus and editable footer content |
| Images | Every picture slot: the homepage hero (the crossroads woodcut in the mockup), Hexx's portrait, ornaments, reader portraits, product photos | Each slot is an image field filled from the media library, with alt text. The mockup's drawn art (inline SVG) is placeholder only and must not be hard-coded into templates; the admin can upload a PNG, JPG, WebP or SVG in its place. Slots keep their frame and size and crop the upload to fit, with a focal point the admin can set. If a slot is left empty, the default art shows |
| Look | Colors (ink, paper, oxblood accent) and fonts (see Brand below) | Theme global styles (theme.json), so the accent is one setting |
| Room settings | Room cap (starts at 12, adjustable; see Invitations), whether empty rooms are hidden or shown. Any customer-facing text that mentions the number of rooms reads it from this setting | Admin options. Default: hide empty rooms and show Hexx's empty-rooms line with the ledger link |
| Welcome offer | Discount amount and minimum spend (see Welcome offer), plus the signup wording and welcome email | Set in MailPoet's signup form, automation email and coupon block. The Vendors settings page shows the safe minimum and warns when the coupon's minimum is too low |
| Emails | Subject and body text of every customer and reader email, including the delivery email | Editable templates, so Hexx's voice can carry into the inbox |

**Brand (decided Oct 31, 2026)**

The light direction from the homepage mockup ("Homepage" board on the Hexxsy homepage canvas) is the brand. The dark boards (brass lamp, brass with a second accent, Victorian parlor, midnight zodiac) are set aside.

| Role | Value |
| --- | --- |
| Paper (page background) | #F1E9D8 |
| Ink (text, rules, borders) | #1B1714 |
| Oxblood (accent: main button, links on hover, room numerals, focus ring) | #6E1B17 |
| Muted ink (secondary text, captions) | #4A3F37 |
| Band (alternate section background) | #EAE0CB |
| Card (raised panels, notice box) | #F6F0E3 |
| Hatch (image placeholder ground) | #E6DCC6 |

- **Type.** IM Fell English SC for the masthead, headings, navigation and buttons. IM Fell English (roman and italic) for Hexx's lines and display text. Libre Caslon Text for body copy. All 3 are Google Fonts. Fallback: Georgia, then serif.
- **Ornament.** Thin ink rules, 4px double rules between sections, a double-bordered notice box, the ❦ fleuron, line-drawn woodcut art. Square corners throughout.
- **Buttons.** Main: oxblood fill, paper text, small caps, square, at least 48px tall. Secondary: ink outline, no fill, at least 44px tall.
- **Email.** Same palette. Most inboxes (Gmail, Outlook) ignore web fonts, so emails name the Fell and Caslon fonts first and fall back to Georgia. The "Hexxsy" masthead goes in as an image so it looks right everywhere, with "Hexxsy" as its alt text. Light background only; no dark-mode-specific design.

**Your own block patterns (decided Oct 31, 2026)**

The admin can make new block patterns at any time in Appearance > Editor > Patterns, synced or not, and use them on any page. The theme supports this:

- The editor's color and font choices are the brand palette and fonts (see Brand), so new patterns stay on brand. Custom colors stay available in Styles for the rare exception.
- The theme registers Hexxsy pattern categories so the admin's patterns sort alongside the theme's.
- No pattern is ever locked, the theme's or the admin's (decided Nov 11, 2026, replacing the earlier plan to lock the theme's structural pieces). Rebecca adjusts any of them as needed in the WordPress block editor. Don't add lock, templateLock or contentOnly settings to any pattern, template or template part.
- Admin-made patterns, and any template or template part edited in the Site Editor, are stored in the database. Deploys replace the theme folder but never touch them. The catch: once a template is edited in the Site Editor, later changes to that template in the theme files don't show until the edit is reset ("Reset" in the Site Editor).

**Hexx's lines (built Oct 31, 2026, plugin 0.7.0)**

- WooCommerce > Hexx's lines holds every line the plugin speaks, on a tab for each part of the site: Homepage rooms, The Ledger, Invitations, Reader rooms, Offering pages, Buying a reading, Late and refund emails, Closing a room.
- Changed Nov 11, 2026 (plugin 0.9.1), after Rebecca found the first version unusable: it was one long list, and each box was empty with the wording shown only as grey hint text, so changing a word meant retyping the whole line. Now each box holds the line as it reads, ready to edit. Emptying a box and saving brings back the original. A changed line shows its original underneath. Saving one tab leaves the others alone.
- On the site, a logged-in admin has "Hexx's lines" in the admin bar. It opens the tab for the page in view: the homepage, a reader's room, an offering, the Ledger or the application page.
- Rebecca wants lines editable where they appear as well as here (Nov 11, 2026). Lines on block-editor pages already are. Lines inside the rooms list, a room and an offering page need those pages rebuilt as blocks (see Known, not built). The admin bar link is the stopgap.
- The emails moved out of Hexx's lines on Nov 12, 2026 (see Emails below), which took 24 boxes and 2 tabs with them. What's left here is page wording only: Homepage rooms, The Ledger, Invitations, Reader rooms, Offering pages, Buying a reading.

**Emails (built Nov 12, 2026, plugin 0.10.0, untested on a live site)**

Rebecca asked for the emails to live where WordPress users expect them. Every email Hexxsy sends to a customer, a reader or someone on the waitlist is now a WooCommerce email, listed under WooCommerce > Settings > Emails with "Hexxsy:" in front of its name.

| Email | Goes to | When |
| --- | --- | --- |
| Ledger: name signed | The person who signed | They sign the waitlist |
| Reader invitation | The invited address | You invite someone |
| Reader application approved | The applicant | You approve them |
| Reader application not accepted | The applicant | You reject them |
| Reading delivered | The customer | A written reading is delivered. The PDF is attached |
| Reading due soon | The reader | Before a written reading is due |
| Reading late (reader) | The reader | A written reading passes its due date |
| Reading late (customer) | The customer | The same moment |
| Reading cancelled and refunded | The customer | Any cancellation and refund. One box per reason |
| Order cancelled (reader) | The reader | The same. One box per reason |
| Room closed | The reader | You revoke them. A second message for when they still have orders to deliver |

- Each has WooCommerce's usual screen: an on/off switch, subject, heading, message, and plain text or HTML. The boxes hold the wording as it reads now. Emptying the message and saving brings back the original.
- They go out in WooCommerce's email design, so the header image, colours and footer text set at the top of WooCommerce > Settings > Emails apply to them. Before this they were plain text.
- {braces} work as before, and each screen says which ones that email can use.
- The approval, rejection and delivery emails were fixed text in the code until now. Their wording is unchanged, and is not yet in Hexx's voice.
- Wording already changed in Hexx's lines is carried over to the new screens once, when the plugin updates.
- Switching an email off stops it. Leave Reader invitation on: the link in it is the only way into the application.
- Still in code: the 2 emails to the admin (a new application, and a refund the payment gateway refused).
- WooCommerce shows "Customer" in the recipient column for all of them, including the ones that go to readers. Each email's description says who really gets it.
- {placeholders} are filled in automatically, and {link}…{/link} marks the words that become a link.
- [hexxsy_line key="…"] places any line on a page. [hexxsy_line key="ledger"] shows the "rooms open" or "every room taken" pair, whichever is true.
- Lines in the theme (homepage hero, footer, 404) are edited in the Site Editor.
- Added in 0.9.0: the labels on a reader's room and an offering's page. The shelves' words are on the shelf categories.
- Added in 0.8.0: the note on a written reading's page and the live-calls-closed line.
- Still in code: reader dashboard text, button labels and notices in My readings, and admin screens.

**Rooms list (built Oct 31, 2026)**

- [hexxsy_rooms] shows approved readers in the order they applied, numbered I, II, III.
- Each card: portrait, shop name, specialties, door line, the shelves they have in stock, lowest price, Knock. Shelves are the 4 shelf categories (see Shelves); Knock opens the reader's room once a Room page is chosen.
- Readers set the door line (140 characters) and portrait in My Account > Shop profile. These go live straight away for now; the approval queue below isn't built.
- Empty rooms: hidden with Hexx's empty-rooms line and the Ledger link (default), or shown as unlit. Set on WooCommerce > Vendors.

**Shelves (built Nov 11, 2026, plugin 0.9.0, untested on a live site)**

- The 4 shelves are WooCommerce product categories, made once when the plugin updates: Sittings, Letters, Papers and Wares. If a category with one of those names already exists, it's used.
- Each is edited in Products > Categories. The category's name is the shelf's name. Six more boxes hold the words that go with it: what it is, Hexx's line in a room, the button on a room's cards, the button on the offering's page, the "How it goes" steps (one per line), and Hexx's line on the offering's page. They start with the mockup's wording.
- Readings file themselves: live calls under Sittings, written readings under Letters, every time one is saved. Readings that existed before the update are filed when it runs.
- Papers and Wares are ordinary WooCommerce products the admin files under those categories by hand. Readers can't add them from their dashboard yet, and their product side still isn't specced.
- The mockup says Letters are "written or recorded". Only written readings exist, so the shelf says "Written readings, sent later". Change it on the category if recorded readings come.

**Reader rooms (built Nov 11, 2026, plugin 0.9.0, theme 0.5.0, untested on a live site)**

- [hexxsy_room] is a reader's room, built to the Room board on the homepage canvas: portrait, "Room IV", shop name, specialties, the reader's introduction, "How I read", Hexx's note, a menu of the shelves they stock, then each shelf with Hexx's line and a card per offering (name, a line of detail, "More about this", price, button).
- Setting it up: make one page, give it the Full width template, insert the "Reader room" pattern, and choose it as the Room page on WooCommerce > Vendors. Every room then lives under that page, at its address plus the shop name: /room/moth-candle/. Knock on the homepage goes there. With no Room page chosen, Knock opens the filtered shop as before.
- The address comes from the shop name, so it changes if the reader renames their shop. Two shops with the same name: the later one gets its account number on the end. An address nobody keeps is a 404.
- Room numbers follow the order readers applied, as on the homepage. Revoking a reader renumbers the rooms after theirs.
- Readers add "How I read" and a note per shelf in Shop profile, where "About you" is now labelled "Introduction". These go live straight away, like the door line, until the approval queue is built.
- Hexx's note is a box on the reader's user profile in wp-admin, under Hexxsy vendor. Only an admin can see or change it. With no note, the block doesn't show.
- A shelf shows only when the reader has something in stock on it. Live calls don't count while they're off sale.
- The button on a reading's card opens its page, because the questions have to be answered there. On Papers and Wares it adds to the basket.
- Not built: the reader's contact details or hours, and a reader's own page layout. Every room has the same layout.

**Offering page (built Nov 11, 2026, plugin 0.9.0, theme 0.5.0, untested on a live site)**

- Every product page is laid out by [hexxsy_offering], which the theme's product template holds. Built to the Product board: a trail (The Rooms / Room IV · reader / shelf), the shelf and what it is, the name, "Offered by" with a link to the room, the picture if there is one, "In [reader]'s words" (the product description), "Before you buy", "How it goes", and "Back to Room IV".
- The price box holds the price, the facts, the intake questions, the refund note and the button. Facts for a written reading are format and arrival; for a live call, format and length; for Papers and Wares, whatever attributes the product shows.
- "Before you buy" is a new box on the reader's reading form, and on the product in wp-admin.
- "How it goes", the button and Hexx's line under the box come from the offering's shelf. The labels are Hexx's lines, under "An offering's page".
- A product Hexxsy sells itself (no reader) leaves out the room links and says "About this".
- Not in this build: the mockup's "Available times" block (booking doesn't exist), an "Includes" fact, a gallery of more than one picture, reviews and related products.
- This replaces WooCommerce's own product page layout. If the template has been edited in the Site Editor, that edit wins until it's reset.

**Hexx's page (built Nov 11, 2026, theme 0.5.0)**

- The "Hexx's page" pattern is the Hexx board: framed portrait with caption, "Proprietress", her name, her line, the 4 paragraphs about her, Rules of the House and the "Find me at the gate" box. Use it on a page with the Full width template. Every word and the portrait are edited in the page.
- The portrait is a placeholder drawing of the lamp. Click it and Replace.
- The contact button has no address yet. Click it in the editor and set its link.
- Two new block styles go with it: Framed (images) and House rules (numbered lists, in Roman numerals).
- The pattern isn't locked, like every other (see Your own block patterns).

**Masthead (built Nov 11, 2026, theme 0.5.0)**

- Appearance > Masthead has the 3 modes: styled text (default), a logo image in place of the text, or the text over a background image. Images come from the media library.
- Logo: its height on the homepage is a setting (40 to 320 pixels, 120 to start). On other pages it's 48 pixels.
- Background image: darkened by a setting from 0 to 80% (45 to start), and the name turns paper-coloured. A focal point (across and down, in percent) says which part stays in view when it's cropped.
- With no image chosen for a mode, the styled text shows.
- The site name is always in the page as text. Behind a logo it's hidden from sight but read by screen readers. On the homepage it's the page's main heading.
- The masthead is now large on the homepage only. On every other page the name is small and the tagline is hidden, as in the inner-page mockups.
- It works on the header as rendered, so it survives edits in the Site Editor as long as the masthead group and the site title block are still in the header.
- The focal point is typed as 2 numbers, not picked by clicking on the picture.

**What the block editor can and can't reach (as built Nov 11, 2026)**

- Fully editable as blocks: the header, the footer, every template, the homepage, the Ledger and application pages, Hexx's page and the refund policy page.
- Not blocks: the inside of a reader's room, an offering's page and the rooms list. Each is one shortcode block whose layout is in the plugin's code and whose look is in the theme's style.css. In the editor you can move that block, and put anything above or below it, but not rearrange what's inside it. Their words are edited in Hexx's lines, on the shelf categories, and by the readers.

**Reader text approval**

- Anything a reader writes or changes goes into a pending queue. Their live room keeps showing the last approved version until the admin approves.
- Every edit goes back in the queue, not just the first version.
- Each reader has a "trusted" switch, off by default. Trusted readers publish text straight to their room. The admin can still edit anything and switch trust off again.
- Whether new products and prices also need review for trusted readers is open (see gap 5).
- Multi-vendor plugins usually only moderate products, so the profile-text queue will likely need custom code.

**Shelves only show when stocked.** A shelf, and its button in the room's shelf menu, only appears when that reader has at least 1 published, in-stock product in that category. The same rule drives the shelf list on each reader's homepage card.

**Reader count drives copy.** The homepage room list, the empty-rooms line and the ledger messages all read the same count of approved readers, so they never disagree.

## Gaps and open decisions

Three pieces are known to be missing. Reading the code turned up more, and a few of them can cost real money or a customer's trust before launch.

### Known, not built

- **Paying readers automatically.** The payout ledger is built (see Money). Stripe Connect is approved for live (Oct 14, 2026) and reader onboarding and transfers are built, but untested against real Stripe. Run the whole flow in test mode before going live.
- **Live calls.** No availability, booking, scheduling or video provider.
- **Possible later: room and offering pages as separate blocks.** Today the inside of a reader's room, an offering's page and the rooms list are each one shortcode block, laid out in code (see What the block editor can and can't reach). They could be broken into blocks Rebecca arranges herself in the block editor: portrait, introduction, Hexx's note, shelf menu, shelves, price box, "How it goes". Rebecca said on Nov 11, 2026 that the fixed layout is fine for now, and later the same day that she wants Hexx's lines editable where they appear. This is the work that would do it: once these are blocks, their wording is typed in place. Ask her before building.
- **Theme.** Foundation built Oct 31, 2026 (theme 0.1.0, untested on a live site): brand styles, fonts, header, footer, basic templates, house block styles, pattern categories. Pass 2 built Oct 31, 2026 (theme 0.3.0, plugin 0.7.0): homepage patterns (hero with the woodcut as a replaceable image, Readers' Rooms band, house notice), the rooms list ([hexxsy_rooms]), and the Hexx's lines screen. Pass 3 built Nov 11, 2026 (theme 0.5.0, plugin 0.9.0): reader room pages, the offering page, Hexx's page and the masthead's modes (see Editable from the back end). Still to build: the reader text approval queue, and Papers and Wares for readers.

### Found in the code

| # | Gap | Why it matters | Decision needed |
| --- | --- | --- | --- |
| 1 | ~~Live calls can be bought today~~ | Fixed Nov 11, 2026 (0.8.0): live calls are off sale until a setting turns them on. See Readings and intake | None |
| 2 | ~~Revoking a reader leaves their readings on sale~~ | Fixed Nov 11, 2026 (0.8.0): readings are paused, and the admin chooses to refund open orders or let the reader finish. See Revoking a reader | None |
| 3 | ~~Nothing happens when a reading is late~~ | Fixed Nov 11, 2026 (0.8.0): reader reminder, late emails to both, customer can cancel, automatic refund after 7 days. Turnaround still counts weekends | Count business days? |
| 4 | ~~No refund or cancellation policy for written readings~~ | Decided and built Nov 11, 2026 (0.8.0). See Policies and REFUND_POLICY.md. The policy page still has to be made on the site, and needs a contact address | Contact address for complaints |
| 5 | Readings go live with no review | A new reader's first offering is public instantly | Approve each reading, or just the first few? |
| 6 | Rejected applicants can never reapply | The code says an admin can reset status, but there's no button for it | Allow reapplying after a wait? Add a reset? |
| 7 | ~~Reader profiles are invisible~~ (fixed Nov 11, 2026: every reader has a room page. Earlier, Oct 31, 2026: the homepage rooms list shows shop name, specialties, door line, portrait, shelves and lowest price; Knock opens the shop filtered to that reader until room pages exist) | Shop name, bio and specialties are collected but never shown to customers. No reader page, no directory | How do customers choose a reader? |
| 8 | No messaging | A reader can't ask a follow-up about a vague question; the customer can't reach the reader | In-site messages, email relay, or none? |
| 9 | Terms pages don't exist | No customer terms. The refund policy is written (Nov 11, 2026). Deferred Nov 11, 2026: terms come later | Write vendor terms and customer terms |
| 10 | Birth data has no privacy plan | Birth date, time and place are personal data, kept on orders indefinitely | Privacy policy, retention, deletion on request |
| 11 | PDF can't show symbols | No ♈, ☽ or emoji, which astrology readers will want | Accept it, or add a font library |
| 12 | ~~Earnings view caps at 50 orders~~ | Fixed: payout totals read every order. The order list itself still shows the latest 50 | None |
| 13 | Thin test coverage | Tests cover the pure rules only (commission, payouts, welcome offer, invites, cancellation and late-reading timing, the PDF). Nothing that touches WooCommerce is tested: checkout, delivery, refunds through the gateway, permissions | Tests for checkout, delivery and permissions before launch |

### Outside the code

- Some US states and cities have fortune-telling ordinances. The entertainment-only language helps, but it's worth checking where readers and customers are.
- Readers are independent sellers, not Hexxsy's contractors (decided Oct 13, 2026). Hexxsy sets platform rules through the reader code of conduct but doesn't direct how readers read, price or schedule.
- Holding payment (decided Oct 14, 2026) replaces the earlier plan of Stripe Connect direct charges. Hexxsy is the seller of record, so chargebacks and refunds land on Hexxsy first. Readers stay independent sellers. Stripe collects readers' tax details and files and mails their 1099s.
