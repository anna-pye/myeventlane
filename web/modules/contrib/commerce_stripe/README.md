Commerce Stripe
===============

CONTENTS OF THIS FILE
---------------------
* Introduction
* Requirements
* Configuration

INTRODUCTION
------------
This module integrates Drupal Commerce with various Stripe payment solutions,
including the Payment Element [1] and the legacy Card Element [2].

1. https://stripe.com/docs/payments/payment-element
2. https://stripe.com/docs/payments/accept-card-payments?platform=web&ui=elements

Stripe supports many payment method types, including credit card, mobile
wallets (e.g., Apple Pay), bank transfers, and more. Both element integrations
support advanced fraud detection, Strong Customer Authentication (e.g., 3D
Secure), and secure payment method tokenization.

## Features

* Configure Payment Element for use on the review page
* Configure the legacy Card Element for use in the payment checkout pane
* Uses the Stripe.js library that ensures card data never touches your server
* Payments in Drupal Commerce synchronized with Stripe
* Supports voids, captures, and refunds through the order management interface


REQUIREMENTS
------------
This module should be added to your codebase via Composer to ensure the Stripe
PHP library dependency is properly managed:

`composer require "drupal/commerce_stripe:^1.0"`

You must also have a Stripe merchant account or developer access to the account
you intend to configure for your integration. You can sign up for one here:

* https://dashboard.stripe.com/register


CONFIGURATION
-------------
Once you've installed the module, you must navigate to the Drupal Commerce
payment gateway configuration screen to define a payment gateway configuration.
This will require providing API keys and configuring the mode (Live vs. Test)
along with a variety of appearance related options depending on the plugin.

Note: Drupal Commerce recommends storing live API credentials outside of the
configuration object and importing them via a configuration override in your
settings.php file. To accomplish this you can input only your test credentials
for validation in the configuration form or input `PLACEHOLDER` and uncheck the
box that instructs the configuration form to validate your API keys.

Payment Element is the current, recommended element Stripe prefers merchants to
use. This element will embed an iframe on the review page that supports credit
card, payment wallet, and a variety of other payment methods. The Card Element
integration is a legacy integration that will incorporate credit card fields on
the payment information checkout pane. Both methods support 3D Secure.

## Stripe review checkout pane

You *must* ensure the _Stripe review_ checkout pane is enabled on the _Review_
page for any checkout flow that supports either Stripe element. Removing it
will break the Payment Element integration and prevent the Card Element
integration from authorizing payments via 3D Secure. (Note: the settings of the
checkout pane apply only to Card Element payments. Payment Element behavior is
entirely controlled by the payment gateway configuration.)

Technically, the Payment Element is integrated as an offsite payment gateway,
which means its iframe would normally render in the _Payment process_ pane that
defaults to the _Payment_ page. To provide a better customer experience, this
module embeds the iframe on the _Stripe review_ pane instead, though it still
uses the redirect behavior of offsite payment gateways to advance upon form
submission. Thanks to the way Stripe payment intents work, any Ajax behaviors
on the _Review_ page will still be accommodated, meaning a customer can still
apply a coupon code even after the Payment Element has loaded. The amount of
the payment intent will be updated as part of the Ajax form refresh.

## Stripe account configuration

In your Stripe account settings, you can adjust the payment methods available
through these elements. To enable Apple Pay support, you will need to
authenticate your domain(s) and ensure a certificate is made accessible on
your web server. (Some server configurations or PaaS build configurations will
need to be adjusted to ensure the web server will allow access to file in the
`/.well-known` directory.)

The Payment Element integration supports webhooks, which keep payments in
Drupal Commerce synchronized with the charges in Stripe when an administrator
updates the payment from the Stripe dashboard. You must configure
the [webhook](https://dashboard.stripe.com/webhooks)
in the Developers section of your Stripe dashboard, adding an endpoint url for
the following path on your
domain: `/payment/notify/[id of your stripe_payment_element payment gateway]`.
e.g. If your gateway ID is foo, then it would be: `/payment/notify/foo`.

Note: Be sure to get your webhook signing secret and set it in the gateway
configuration, so that webhooks will be verified.

Enable webhooks for the following events:

* payment_intent.amount_capturable_updated
* payment_intent.canceled
* refund.created
* charge.refunded
* payment_intent.processing
* payment_intent.succeeded
* payment_intent.payment_failed

You may enable other events as needed based on your own customizations. You can
use the webhooks interface in the Stripe dashboard to view all webhooks sent
to the endpoint. Settings in the Drupal module can be toggled to also log some
webhook related notifications to the site.

### Listen for webhooks locally

Stripe provides a CLI that lets you listen for webhooks sent by your account in
test mode. This can forward to a URL of your choosing, including to the payment
notification URL of your local Drupal Commerce instance. Use the following
guide to install the CLI and login to your account:

https://stripe.com/docs/stripe-cli

Then start listening to webhooks locally using:

`stripe listen --forward-to your.ddev.site/payment/notify/your_payment_gateway`

Obviously, you will need to replace the domain with your local environment's
domain name and `your_payment_gateway` with the machine name of your Stripe
payment gateway configuration. When you start listening, you will be given a
signing secret that you should copy to your payment gateway configuration.

You can now see all incoming webhooks through both the CLI output and Stripe's
user interface, and if you enable the Commerce Stripe Webhook Events module,
you will have a log in the admin interface of all webhook events. These can be
used to troubleshoot delivery or processing failures.

### Simulate a 500 error on the Payment Element return request

If you are experiencing 500 errors on the Payment Element return route, you may
need to use logged webhook events to recover from scenarios where Drupal fails
to recognize a legitimate payment. Reproducing a 500 error on this route may be
challenging, so this module includes a way to force a 500 error upon Payment
Element confirmation. Edit your `settings.php` file and set any number of email
addresses to a settings array, and orders for those emails will always
throw an exception to trigger the 500 error:

`$settings['commerce_stripe.debug']['return_failure_emails'] = ['john.doe@example.com'];`

In the failing scenario, Stripe will process the payment, sending webhooks for
the successful charge and payment intent, but Drupal will have no record of the
payment method or payment that ordinarily would be created. The order will be
stuck in a cart status and cannot be completed, as the payment intent would not
have been voided, and one intent cannot be used to create multiple payments.

Note: if you need to call `StripePaymentElement::onReturn()` manually, you can
skip this return failure simulation by setting the `skip_return_failure` query
parameter in the request object passed to the function to `TRUE`.

## Customizing the JavaScript settings

Stripe elements are initialized via JavaScript using settings arrays that are
outputted by the module. The module does not accommodate _every_ setting you
might want to adjust for your site. To change or add settings before an element
is initialized, you can use `hook_js_settings_alter()`. This module uses a
different key for each of the elements it supports to make finding the correct
array a little easier.
