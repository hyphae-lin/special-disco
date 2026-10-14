# Hexxsy

A multi-vendor marketplace for novelty tarot readings and divinations, built on WordPress and WooCommerce, for hexxsy.com (hosted on DreamHost).

## What's here

`wp-content/plugins/hexxsy-vendors/` is the custom vendor plugin. Version 0.4.0 covers:

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
- **Payout ledger.** Hexxsy holds each payment. A written reading's share becomes payable once it's delivered and a hold period (7 days by default) has passed. WooCommerce > Payouts shows what each reader is owed; send the money by hand, then click Mark paid. Readers see on-hold, ready-to-pay and paid totals in Reading orders.

## Not built yet

- Automatic payouts through Stripe Connect (waiting on approval for this business type)
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

Upload `wp-content/plugins/hexxsy-vendors/` to the same path on the server (skip `vendor/` and `tests/`), then activate it under Plugins. WooCommerce must be active first.

## Spec

`docs/SPEC.md` describes what the plugin does, what's decided but not built, and the open gaps. `docs/CODE_OF_CONDUCT_READERS.md` is the reader code of conduct the application links to. Read both before building a new feature.

## Working on this repo

`main` is the real, current version of the site. Start new work from `main`. Work on a branch if you like, but when Rebecca approves the change, merge it into `main` and push. Don't leave finished work sitting on a side branch.
