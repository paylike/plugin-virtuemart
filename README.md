# Joomla Virtuemart plugin for Paylike

This plugin is *not* developed or maintained by Paylike but kindly made
available by a user.

Released under the GPL V3 license: https://opensource.org/licenses/GPL-3.0

## Supported Virtuemart versions

- \>= 3.2.8


## Supported Joomla versions

- \>= 3.6.5

## Installation

1.Once you have installed Virtuemart on your Joomla setup, follow these simple steps:
  Signup at (paylike.io) [https://paylike.io] (it’s free)
  
  1. Create a live account
  1. Create an app key for your Joomla website
  1. Upload the plugin zip trough the .
  1. Activate the plugin through the 'Extensions' screen in Joomla.
  1. Under Virtuemart payment methods create a new payment method and select Paylike.
  1. Insert the app key and your public key in the settings for the Paylike payment gateway you just created
  

## Updating settings

Under the Virtuemart Paylike payment method settings, you can:
 * Update the payment method text in the payment gateways list
 * Update the payment method description in the payment gateways list
 * Update the title that shows up in the payment popup 
 * Add test/live keys
 * Set payment mode (test/live)
 * Change the capture type (Instant/Manual by changing the order status)
 
 ## How to
 
 1. Capture
 * In Instant mode, the orders are captured automatically
 * In delayed mode you can capture an order by moving the order to the completed/shipped status from pending. 
 2. Refund
   * To refund an order move the order into refunded or half refund status.
 3. Void
   * To void an order you can move the order into cancelled status. 
