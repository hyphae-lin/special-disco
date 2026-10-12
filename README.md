# Hexxsy

A multi-vendor marketplace for novelty tarot readings and divinations, built on WordPress and WooCommerce, for hexxsy.com (hosted on DreamHost).

## What's here

`wp-content/plugins/hexxsy-vendors/` is the custom vendor plugin. Version 0.2.0 covers:

- **Vendor applications.** Put `[hexxsy_vendor_application]` on a page. Applicants need an account first (WooCommerce's My Account page handles sign-up).
- **Manual approval.** WooCommerce > Vendors lists applications. Approving grants the `hexxsy_vendor` role; both decisions email the applicant.
- **Commission.** Site default is 20%, set on the Vendors page. Override per vendor on their user profile.
- **Reading offerings.** Any product can be marked a written reading or a live call, with turnaround days or call length. The product's author is its vendor.
- **Intake questions.** Written readings ask for the customer's question, plus any of birth date, birth time, birth place, or the name of the person the reading is about. Answers are saved on the order.
- **Vendor dashboard.** Approved vendors get three extra tabs in My Account: My readings (add, edit, pause, remove, with an image), Reading orders (their own order lines, intake answers, and earnings) and Shop profile. New readings go live as soon as they're saved.
- **Shop-page buttons.** Readings show "Request reading" or "Book a call" and link to the reading's page, where intake questions (and later, booking) happen.
- **Split records.** Each order line stores its vendor, the rate at purchase, the site's commission and the vendor's earnings. Payouts will read these.

## Not built yet

- Payment splitting (waiting on Stripe Connect approval for this business type)
- Live-call availability, booking and the video provider
- Written reading delivery
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
