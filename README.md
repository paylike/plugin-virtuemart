# Joomla VirtueMart plugin for Paylike

This plugin is *not* developed or maintained by Paylike but kindly made
available by a user.

Released under the GNU V2 license: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

## Important

Version `2.*` is not backward compatible with version `1.*` . You need to remove the extension before installing `2.*` . Make sure old orders are all processed before installing the new version.

## Supported VirtueMart versions

* The plugin has been tested with most versions of Virtuemart at every iteration. We recommend using the latest version of Virtuemart, but if that is not possible for some reason, test the plugin with your Virtuemart version and it would probably function properly.
* Virtuemart
 version last tested on: *3.8.9*

## Installation

  Once you have installed VirtueMart on your Joomla setup, follow these simple steps:
  1. Signup at [paylike.io](https://paylike.io) (it’s free)
  1. Create a live account
  1. Create an app key for your Joomla website
  1. Upload the plugin zip trough the 'Extensions' screen in Joomla.
  1. Activate the plugin through the 'Extensions' screen in Joomla.
  1. Under VirtueMart payment methods create a new payment method and select Paylike.
  1. Insert the app key and your public key in the settings for the Paylike payment gateway you just created


## Updating settings

Under the VirtueMart Paylike payment method settings, you can:
 * Update the payment method text in the payment gateways list
 * Update the payment method description in the payment gateways list
 * Update the title that shows up in the payment popup
 * Add test/live keys
 * Set payment mode (test/live)
 * Change the capture type (Instant/Manual by changing the order status)

 ## How to

 1. Capture
 * In Instant mode, the orders are captured automatically
 * In delayed mode you can capture an order by moving the order to the shipped status from pending.
 2. Refund
   * To refund an order move the order into refunded status.
 3. Void
   * To void an order you can move the order into cancelled status.

## Available features
1. Capture
   * Virtuemart admin panel: full capture
   * Paylike admin panel: full/partial capture
2. Refund
   * Virtuemart admin panel: full refund
   * Paylike admin panel: full/partial refund
3. Void
   * Virtuemart admin panel: full void
   * Paylike admin panel: full/partial void