EE4 AffiliateWP integration
=========

This integrates Event Espresso with the AffiliateWP plugin for tracking affiliate conversions.

## Installation

This plugin/addon needs to be uploaded to the "/wp-content/plugins/" directory on your server or installed using the WordPress plugins installer.

## Usage

As long as AffiliateWP is active and setup, this plugin just transparently integrates to track completed transactions from
affiliate referrals.  There is no additional setup required.

Affiliate commission will be tracked for:

1. Registrations that are set to not approved and then later receive a payment that completes the transaction.
2. Offline payment method is chosen for initial registration(s) and then later marked as paid in the admin.
3. Payment is made with the registration that completes the trnsaction.

Affiliate commission is not tracked for:

1. Any registrations done in the backend by administration.
  

