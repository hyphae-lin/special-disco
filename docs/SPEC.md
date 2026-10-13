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
3. **Decision.** The admin approves (grants the vendor role) or rejects. Either way the applicant gets a short email. An approved vendor can later be revoked, which is the same action as reject.
4. **Sell.** Approved vendors get three tabs in My Account.

| Tab | What the vendor does there |
| --- | --- |
| Readings I offer | Add or edit a reading (name, description, price, written or live, turnaround days or call length, intake questions, one image). Pause, resume or remove |
| Reading orders | See their latest 50 orders, each customer's intake answers, what they earn per line, and a running "earned so far" total. Write and deliver written readings here |
| Shop profile | Edit shop name, bio and specialties |

A new reading goes live the moment it's saved. Nobody reviews it first.

The admin can also make readings in the normal WooCommerce product editor, which has the same reading fields.

## Invitations and waitlist (decided, not built)

Hexxsy will have at most 12 readers, and readers join by invitation only. Decided Oct 13, 2026. This replaces the open application in step 1 above.

**Cap**

- A "Reader seats" setting on WooCommerce > Vendors, set to 12.
- Approved readers plus unused invites can't exceed the cap, so you never invite more people than you have room for.
- Revoking a reader, or an invite expiring, frees a seat.
- The Approve button is disabled when every seat is taken.

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

## Readings and intake

Every reading is a WooCommerce simple product, marked virtual and sold one per order. The product's author is its vendor.

| Format | Vendor sets | Shop button | What the customer fills in |
| --- | --- | --- | --- |
| Written reading | Turnaround in days (1 or more) | Request reading | Their question, always (up to 2,000 characters), plus any the vendor ticks: birth date, birth time, birth place, name of the person the reading is about |
| Live call | Call length in minutes (5 or more) | Book a call | Nothing yet |

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

The site keeps 20% of each reading by default; the reader gets the rest. No money actually moves to readers yet.

- **Rate.** Default set on WooCommerce > Vendors (0 to 100%). The admin can override it per vendor on their user profile.
- **Base.** Commission is taken on the line total after discounts, before tax.
- **Rounding.** Math runs in cents. The commission is rounded and the reader gets exactly what's left, so no cent is lost or invented. On a $45.00 reading at 20%, the site keeps $9.00 and the reader earns $36.00.
- **Records.** At checkout every order line is stamped with its vendor, the rate in force, the commission and the reader's earnings. A later rate change never rewrites past orders. Payouts will read these stamps.
- **What readers see.** "Earned so far" in Reading orders adds up Processing and Completed orders among their latest 50, with a note that payouts aren't set up.

A product with no approved vendor as its author is treated as the site's own: no split recorded, the site keeps it all.

## Policies

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
| Static pages | Homepage welcome, house notice (disclaimer), Hexx's page (portrait, bio, Rules of the House, contact box) | Block-editor pages built from locked block patterns, so words and images change without breaking layout |
| Shelves | The 4 category names, Hexx's line for each, button labels ("Book," "Request," "Take a copy," "Add to basket") | Editable per category, e.g. product category name, description and term meta |
| Readers | Everything a reader writes: door line (one sentence on their homepage card), introduction, "How I read," a note per shelf, product descriptions, "Before you buy" | Reader profile and product fields, through the approval queue below |
| Hexx's note | One or 2 lines from Hexx about each reader, shown in their room | Admin-only field on the reader's profile. Readers can never see an edit box for it |
| Navigation and footer | Menu items, footer text, both disclaimers (Hexx's in-world version and the plain one) | WordPress menus and editable footer content |
| Images | Hexx's portrait, the crossroads woodcut, ornaments, reader portraits | Media library, swappable without code |
| Look | Colors (ink, paper, oxblood accent) and fonts | Theme global styles (theme.json), so the accent is one setting |
| Room settings | Room cap (12, see Invitations), whether empty rooms are hidden or shown | Admin options. Default: hide empty rooms and show Hexx's empty-rooms line with the ledger link |
| Emails | Subject and body text of every customer and reader email, including the delivery email | Editable templates, so Hexx's voice can carry into the inbox |

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

- **Paying readers.** Splits are recorded but no money moves. Waiting on Stripe Connect approval for this business type. If Stripe says no, what's plan B?
- **Live calls.** No availability, booking, scheduling or video provider.
- **Theme.** No custom design; the site runs on whatever theme is installed.

### Found in the code

| # | Gap | Why it matters | Decision needed |
| --- | --- | --- | --- |
| 1 | Live calls can be bought today | A customer can pay for "Book a call" and nothing happens. The order sits in Processing forever | Hide live-call readings until booking exists? |
| 2 | Revoking a reader leaves their readings on sale | New sales record no split, so the site silently keeps 100%. The reader also loses the dashboard, so paid orders can't be delivered | On revoke: pause their readings? Who fulfils or refunds open orders? |
| 3 | Nothing happens when a reading is late | Due dates show, but no reminder to the reader, no notice to the customer, no refund path. Turnaround counts weekends | Reminders? Grace period? Auto-refund after X days? |
| 4 | No refund or cancellation policy for written readings | Only live calls have one | Can a customer cancel before delivery? After? |
| 5 | Readings go live with no review | A new reader's first offering is public instantly | Approve each reading, or just the first few? |
| 6 | Rejected applicants can never reapply | The code says an admin can reset status, but there's no button for it | Allow reapplying after a wait? Add a reset? |
| 7 | Reader profiles are invisible | Shop name, bio and specialties are collected but never shown to customers. No reader page, no directory | How do customers choose a reader? |
| 8 | No messaging | A reader can't ask a follow-up about a vague question; the customer can't reach the reader | In-site messages, email relay, or none? |
| 9 | Terms pages don't exist | The application asks readers to agree to "vendor terms" that aren't written. No customer terms either | Write vendor terms, customer terms, refund policy |
| 10 | Birth data has no privacy plan | Birth date, time and place are personal data, kept on orders indefinitely | Privacy policy, retention, deletion on request |
| 11 | PDF can't show symbols | No ♈, ☽ or emoji, which astrology readers will want | Accept it, or add a font library |
| 12 | Earnings view caps at 50 orders | Busy readers' "earned so far" will undercount | Fine until payouts; payouts must read all records |
| 13 | Thin test coverage | Tests cover commission math and the PDF only | Tests for checkout, delivery and permissions before launch |

### Outside the code

- Some US states and cities have fortune-telling ordinances. The entertainment-only language helps, but it's worth checking where readers and customers are.
- Readers are independent sellers, not Hexxsy's contractors (decided Oct 13, 2026). Hexxsy sets platform rules through the reader code of conduct but doesn't direct how readers read, price or schedule. With Stripe Connect direct charges, readers are paid into their own Stripe accounts and Stripe handles their 1099-Ks. Confirm with an accountant.
