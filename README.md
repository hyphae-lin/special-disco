# Hexxsy

A multi-vendor marketplace for novelty tarot readings and divinations, built on WordPress and WooCommerce, for hexxsy.com (hosted on DreamHost).

## What's here

`wp-content/plugins/hexxsy-vendors/` is the custom vendor plugin. Version 0.5.0 covers:

- **Vendor applications.** Put `[hexxsy_vendor_application]` on a page. Applicants need an account first (WooCommerce's My Account page handles sign-up).
- **Manual approval.** WooCommerce > Vendors lists applications. Approving grants the `hexxsy_vendor` role; both decisions email the applicant.
- **Commission.** Site default is 20%, set on the Vendors page. Override per vendor on their user profile.
- **Reading offerings.** Any product can be marked a written reading or a live call, with turnaround days or call length. The product's author is its vendor.
- **Intake questions.** Written readings ask for the customer's question, plus any of birth date, birth time, birth place, or the name of the person the reading is about. Answers are saved on the order.
- **Vendor dashboard.** Approved vendors get three extra tabs in My Account: Readings I offer (add, edit, pause, remove, with an image), Reading orders (their own order lines, intake answers, and earnings) and Shop profile. New readings go live as soon as they're saved.
- **Written reading delivery.** In Reading orders, the vendor writes the reading under each paid order, saves drafts, then delivers. Delivery locks the text, emails the customer a PDF, and adds a download button to the customer's order page. The order completes once every reading on it is delivered. The PDF uses the standard Helvetica fonts, so it needs no libraries, but it can't show emoji or symbols outside Western European text. See `docs/sample-reading.pdf` for an example.
- **Accounts required.** Guest checkout is forced off, whatever WooCommerce's settings say; customers create an account at checkout.
- **Customer reading library.** A My readings tab lists every reading the customer has bought, with its reader, date, due date, and a PDF download once delivered.
- **Shop-page buttons.** Readings show "Request reading" or "Book a call" and link to the reading's page, where intake questions (and later, booking) happen.
- **Split records.** Each order line stores its vendor, the rate at purchase, the site's commission and the vendor's earnings. Payouts will read these.
- **Welcome offer.** Coupons MailPoet generates from the welcome email are recognized at checkout. The discount on each reader's line is capped at the site's commission, and readers are paid on the full price; the house absorbs the discount. Set which MailPoet emails count on WooCommerce > Vendors, where the Welcome offer check also warns if the coupon's minimum spend is too low. Any coupon can be marked by hand with the Welcome offer checkbox in WooCommerce > Coupons.
- **Payouts.** Hexxsy holds each payment. A written reading's share becomes payable once it's delivered and a hold period (7 days by default) has passed. Readers set up payouts from Reading orders, which creates a Stripe Express account. WooCommerce > Payouts sends what's owed by Stripe transfer, or records a payment made by hand, and can reverse a transfer after a refund.

## Not built yet

- Live-call availability, booking and the video provider
- Custom theme

## Live call policy

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

Every push to `main` runs the tests, and if they pass, `.github/workflows/deploy.yml` copies the plugin to DreamHost over SSH. Tests, `vendor/` and Composer files stay behind. You can also run it by hand from the Actions tab.

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
5. Delete the key files from your computer once they're saved in GitHub and on DreamHost.

The deploy replaces the plugin folder to match the repo exactly, so never edit plugin files on the server. It refuses any path that doesn't end in `/wp-content/plugins/hexxsy-vendors`, so a typo can't wipe the rest of the site. After the first deploy, activate the plugin under Plugins.

## Stripe

1. Install the WooCommerce Stripe Payment Gateway and enter your keys. Leave it in test mode until a full test run works: reader onboards, customer pays, reader delivers, hold ends, Send via Stripe, then a refund and Reverse transfer.
2. Hexxsy Vendors reads the gateway's secret key. To use a different key, add `define( 'HEXXSY_STRIPE_SECRET_KEY', 'sk_...' );` to `wp-config.php`. Never commit a key to this repo.
3. Before going live, set Hexxsy's own Stripe payouts to manual. Readers' shares sit in Hexxsy's Stripe balance until they're transferred, and an automatic payout would sweep them to Hexxsy's bank.

## Spec

`docs/SPEC.md` describes what the plugin does, what's decided but not built, and the open gaps. `docs/CODE_OF_CONDUCT_READERS.md` is the reader code of conduct the application links to. Read both before building a new feature.

## Working on this repo

`main` is the real, current version of the site. Start new work from `main`. Work on a branch if you like, but when Rebecca approves the change, merge it into `main` and push. Don't leave finished work sitting on a side branch.
