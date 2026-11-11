# Hexxsy

A multi-vendor marketplace for novelty tarot readings and divinations, built on WordPress and WooCommerce, for hexxsy.com (hosted on DreamHost).

## What's here

`wp-content/plugins/hexxsy-vendors/` is the custom vendor plugin. Version 0.9.0 covers:

- **Invitations and the Ledger.** Readers join by invitation. Put `[hexxsy_waitlist]` on the Ledger page so anyone can sign the waitlist, no account needed. On WooCommerce > Vendors, set Reader seats and choose the application, Ledger and code of conduct pages, then invite people from the waitlist or by email. Invites last 14 days and work once, for the account with that email.
- **Rooms list.** `[hexxsy_rooms]` lists approved readers as numbered rooms. Readers add a portrait and door line in My Account > Shop profile. Empty rooms are hidden or shown unlit (WooCommerce > Vendors).
- **Shelves.** Sittings, Letters, Papers and Wares are product categories the plugin makes once. Edit each one's name, Hexx's lines, button labels and "How it goes" steps in Products > Categories. Live calls file under Sittings and written readings under Letters by themselves.
- **Reader rooms.** `[hexxsy_room]` is a reader's room: portrait, introduction, "How I read", Hexx's note and their stocked shelves. Put it on one page (the theme's "Reader room" pattern, Full width template) and choose that page as the Room page on WooCommerce > Vendors. Each room's address is that page plus the shop name, e.g. `/room/moth-candle/`. Hexx's note is on the reader's user profile in wp-admin.
- **Offering page.** `[hexxsy_offering]` lays out every product page: where it sits, the reader's description, "Before you buy", "How it goes", and a price box with the questions and the button. The theme's product template holds it.
- **Hexx's lines.** WooCommerce > Hexx's lines edits every line the plugin speaks, including the invite and waitlist emails. `[hexxsy_line key="…"]` places one on a page.
- **Vendor applications.** Put `[hexxsy_vendor_application]` on a page. It opens only for someone holding a valid invite, logged in with the invited email (WooCommerce's My Account page handles sign-up).
- **Manual approval.** WooCommerce > Vendors lists applications. Approving grants the `hexxsy_vendor` role; both decisions email the applicant.
- **Revoking a reader.** Revoke pauses their readings at once. If they have paid orders they haven't delivered, you choose: refund those orders now, or let the reader finish them. A reader finishing keeps only the Reading orders tab.
- **Commission.** Site default is 20%, set on the Vendors page. Override per vendor on their user profile.
- **Reading offerings.** Any product can be marked a written reading or a live call, with turnaround days or call length. The product's author is its vendor.
- **Live calls are off sale.** Until booking is built, live calls are hidden and can't be bought. "Customers can buy live calls" on WooCommerce > Vendors turns them on.
- **Intake questions.** Written readings ask for the customer's question, plus any of birth date, birth time, birth place, or the name of the person the reading is about. Answers are saved on the order.
- **Vendor dashboard.** Approved vendors get three extra tabs in My Account: Readings I offer (add, edit, pause, remove, with an image), Reading orders (their own order lines, intake answers, and earnings) and Shop profile. New readings go live as soon as they're saved.
- **Written reading delivery.** In Reading orders, the vendor writes the reading under each paid order, saves drafts, then delivers. Delivery locks the text, emails the customer a PDF, and adds a download button to the customer's order page. The order completes once every reading on it is delivered. The PDF uses the standard Helvetica fonts, so it needs no libraries, but it can't show emoji or symbols outside Western European text. See `docs/sample-reading.pdf` for an example.
- **Accounts required.** Guest checkout is forced off, whatever WooCommerce's settings say; customers create an account at checkout.
- **Late readings.** The reader is reminded a day before a reading is due. If it goes late, reader and customer are both emailed, and the customer can cancel for a full refund. Still undelivered 7 days later, it's refunded automatically. The numbers are on WooCommerce > Vendors.
- **Cancellations and refunds.** A customer can cancel a written reading from My readings within 24 hours of paying, if it isn't delivered, or once it's late. The refund goes back through the payment gateway and the reader's share drops to nothing. If the gateway refuses, you get an email to refund it by hand. Make the policy page from the theme's "Refund policy" pattern and choose it under Refund policy page; `[hexxsy_setting key="cancel_hours"]` (or `late_refund_days`, `hold_days`) prints the current number. Wording: `docs/REFUND_POLICY.md`.
- **Customer reading library.** A My readings tab lists every reading the customer has bought, with its reader, date, due date, and a PDF download once delivered.
- **Shop-page buttons.** Readings show "Request reading" or "Book a call" and link to the reading's page, where intake questions (and later, booking) happen.
- **Split records.** Each order line stores its vendor, the rate at purchase, the site's commission and the vendor's earnings. Payouts will read these.
- **Welcome offer.** Coupons MailPoet generates from the welcome email are recognized at checkout. The discount on each reader's line is capped at the site's commission, and readers are paid on the full price; the house absorbs the discount. Set which MailPoet emails count on WooCommerce > Vendors, where the Welcome offer check also warns if the coupon's minimum spend is too low. Any coupon can be marked by hand with the Welcome offer checkbox in WooCommerce > Coupons.
- **Payouts.** Hexxsy holds each payment. A written reading's share becomes payable once it's delivered and a hold period (7 days by default) has passed. Readers set up payouts from Reading orders, which creates a Stripe Express account. WooCommerce > Payouts sends what's owed by Stripe transfer, or records a payment made by hand, and can reverse a transfer after a refund.

`wp-content/themes/hexxsy/` is the house theme, a block theme. Version 0.5.0 covers:

- **Brand as global styles.** The palette, fonts and sizes live in `theme.json`, so changing the accent is one setting in Appearance > Editor > Styles. The fonts (IM Fell English, IM Fell English SC, Libre Caslon Text) are served from the theme, not from Google.
- **Header and footer.** The masthead with the site title and tagline, navigation, and both disclaimers. Edit them in Appearance > Editor > Patterns > Template parts.
- **Templates.** Page, full-width page, post, archive, search and 404. WooCommerce supplies its own shop, product, cart and checkout templates, which pick up the header, footer and styles.
- **House styles.** Block styles the editor offers by name: Double rule and Thin rule (separators), Notice, Card and Band (groups), and Hexx speaks, Small caps label and Fleuron heading (paragraphs).
- **Homepage.** The Homepage pattern (hero with the woodcut, Readers' Rooms, house notice). Use it on a page with the full width template and set that page as the homepage in Settings > Reading. The woodcut is an ordinary image block: click it and Replace to use your own.
- **Forms.** The plugin's forms and WooCommerce's account forms match the Ledger mockup. Patterns "Become a reader" and "The Ledger" lay out those pages.
- **Rooms and offerings.** Styles for the reader room and the offering page, the "Reader room" pattern, and a product template that replaces WooCommerce's layout.
- **Hexx's page.** The "Hexx's page" pattern: portrait, who she is, Rules of the House, and a contact box. Replace the placeholder portrait and set the button's link in the editor.
- **Masthead.** Appearance > Masthead: styled text, a logo image, or the text over a darkened background image. The name is large on the homepage and small on every other page.
- **Refund policy.** The "Refund policy" pattern is the policy page, with its numbers read from the plugin's settings. It has a placeholder for your contact address.
- **Your own patterns.** Make them in Appearance > Editor > Patterns. Four Hexxsy categories are there to file them under. Patterns you make are kept in the database, so deploys never touch them.

## Not built yet

- Live-call availability, booking and the video provider
- The reader text approval queue
- Possible later: reader room and offering pages as separate blocks you can rearrange in the editor (today each is one fixed block)
- Papers and Wares that readers can add themselves
- Vendor terms and customer terms

## Refunds

Written readings: `docs/REFUND_POLICY.md`. To refund a delivered reading as an exception, open the order in WooCommerce and use its Refund button; the reader's share follows.

The late-reading check runs hourly on WordPress's scheduler, which only fires when someone visits the site. For it to run on time on a quiet site, add a cron job in the DreamHost panel that fetches `https://hexxsy.com/wp-cron.php` every 15 minutes.

## Live call policy

Live calls are off sale until booking is built. When they open:

- Customers can cancel free of charge up to 24 hours before the call.
- Inside 24 hours, no refund. A customer who doesn't show isn't refunded either.
- If the reader doesn't show, or cancels at any time, the customer gets a full refund.

## Development

```sh
cd wp-content/plugins/hexxsy-vendors
composer install
composer test
```

## Deploying to DreamHost

Every push to `main` runs the tests, and if they pass, `.github/workflows/deploy.yml` copies the plugin and the theme to DreamHost over SSH. Tests, `vendor/` and Composer files stay behind. You can also run it by hand from the Actions tab.

One-time setup:

1. In the DreamHost panel, make the site's user an SSH user (Websites > Manage Websites, or Users).
2. Make a key pair just for deploys, with no passphrase: `ssh-keygen -t ed25519 -f hexxsy-deploy -N ""`. Add the contents of `hexxsy-deploy.pub` to `~/.ssh/authorized_keys` on DreamHost.
3. Run `ssh-keyscan <your DreamHost server>`, and check the fingerprint against what DreamHost shows when you first log in by SSH.
4. In GitHub, go to Settings > Secrets and variables > Actions and add:
   - `DREAMHOST_HOST`: the server name, e.g. `pdx1-shared-a1-01.dreamhost.com`
   - `DREAMHOST_USER`: the SSH user
   - `DREAMHOST_SSH_KEY`: the contents of `hexxsy-deploy` (the private key)
   - `DREAMHOST_KNOWN_HOSTS`: the output from step 3
   - `DREAMHOST_PLUGIN_PATH`: the full path to the plugin folder, e.g. `/home/<user>/staging.hexxsy.com/wp-content/plugins/hexxsy-vendors`
   - `DREAMHOST_THEME_PATH`: the full path to the theme folder, e.g. `/home/<user>/staging.hexxsy.com/wp-content/themes/hexxsy`. Optional; without it the theme isn't deployed.
5. Delete the key files from your computer once they're saved in GitHub and on DreamHost.

The deploy replaces the plugin folder to match the repo exactly, so never edit plugin files on the server. It refuses any path that doesn't end in `/wp-content/plugins/hexxsy-vendors`, so a typo can't wipe the rest of the site. After the first deploy, activate the plugin under Plugins and the theme under Appearance > Themes. The theme path must end in `/wp-content/themes/hexxsy`, for the same reason.

## Stripe

1. Install the WooCommerce Stripe Payment Gateway and enter your keys. Leave it in test mode until a full test run works: reader onboards, customer pays, reader delivers, hold ends, Send via Stripe, then a refund and Reverse transfer.
2. Hexxsy Vendors reads the gateway's secret key. To use a different key, add `define( 'HEXXSY_STRIPE_SECRET_KEY', 'sk_...' );` to `wp-config.php`. Never commit a key to this repo.
3. Before going live, set Hexxsy's own Stripe payouts to manual. Readers' shares sit in Hexxsy's Stripe balance until they're transferred, and an automatic payout would sweep them to Hexxsy's bank.

## Spec

`docs/SPEC.md` describes what the plugin does, what's decided but not built, and the open gaps. `docs/CODE_OF_CONDUCT_READERS.md` is the reader code of conduct the application links to. Read both before building a new feature.

## Working on this repo

`main` is the real, current version of the site. Start new work from `main`. Work on a branch if you like, but when Rebecca approves the change, merge it into `main` and push. Don't leave finished work sitting on a side branch.
