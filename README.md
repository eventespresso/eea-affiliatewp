EE4 AffiliateWP integration
=========

This integrates Event Espresso with the AffiliateWP plugin for tracking affiliate conversions.

## Installation

This plugin/addon needs to be uploaded to the "/wp-content/plugins/" directory on your server or installed using the WordPress plugins installer.

## Usage

As long as AffiliateWP is active and setup, this plugin just transparently integrates to track completed transactions from
affiliate referrals.  There is no additional setup required.

> Note: Currently only event registration transactions completed within the duration of the affiliate cookie will get tracked. This means that if you offer offline or offsite payment methods and the user does not pay within the cookie expiration time for an affiliate, the conversion will not be tracked.

> Also, since conversion is tracked on completed transactions, registration status is not accounted for. So if you have your default registration status set to not approved, this integration will still track affiliate conversions if the transaction is completed. 

> This integration also does NOT track admin-side registrations and/or recorded payments/completed transactions.
  

