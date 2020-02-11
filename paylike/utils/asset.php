<?php

defined ('_JEXEC') or die('Restricted access');

/* load scripts and stylesheets*/
$document = JFactory::getDocument();
$document->addScript( 'https://sdk.paylike.io/3.js' );
JHtml::_( 'jquery.framework' );
$document->addScript( JURI::base() . 'plugins/vmpayment/paylike/paylike.js' );
$document->addScript( JURI::base() . 'plugins/vmpayment/paylike/bootstrap.min.js' );
$document->addStyleSheet( JURI::base() . 'plugins/vmpayment/paylike/paylike.css' );
$document->addStyleSheet( JURI::base() . 'plugins/vmpayment/paylike/bootstrap.min.css' );
return;
// <data> {
	// transactionId: String,	// required
	// descriptor: String,		// optional, will fallback to merchant descriptor
	// currency: String,		// required, three letter ISO
	// amount: Number,			// required, amount in minor units
	// custom:	Object,			// optional, any custom data
// }
$ch = curl_init();
$query = http_build_query($data, '', '&');
curl_setopt($ch, CURLOPT_URL, 'https://api.paylike.io/merchants/az13/transactions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
curl_setopt($ch, CURLOPT_USERPWD, $apiKey);

$headers = array();
$headers[] = 'Content-Type: application/x-www-form-urlencoded';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);