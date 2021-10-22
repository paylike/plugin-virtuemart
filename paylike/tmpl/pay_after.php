<?php
defined ('_JEXEC') or die();

/**
 * paylike payment plugin:
 * @author Kohl Patrick
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (c) Studio 42. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://shop.st42.fr
 */
$method = $viewData["method"];
$cart = $viewData["cart"];
$billingDetail = $viewData["billingDetails"];
$paylikeCurrency = new PaylikeCurrency();
$price = floatval( str_replace( ",", "", $cart->cartPrices['billTotal'] ) );
$this->getPaymentCurrency( $method );
$currency = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
$priceInCents = ceil( round( $price, 3 ) * $paylikeCurrency->getPaylikeCurrencyMultiplier( $currency ) );
$lang = JFactory::getLanguage();
$languages = JLanguageHelper::getLanguages( 'lang_code' );
$languageCode = $languages[ $lang->getTag() ]->sef;

$data = new stdClass;
$data->publicKey = $this->setKey($method);
$data->testMode = $method->test_mode;

$data->title = jText::_($method->title);
$data->description = jText::_($method->description);
$data->orderId = $billingDetail->virtuemart_order_id;
$data->virtuemart_paymentmethod_id = $billingDetail->virtuemart_paymentmethod_id;
$data->orderNo = $billingDetail->order_number;
$data->products = array();
foreach ( $cart->products as $product ) {
	$data->products[] = array(
		"Id" => $product->virtuemart_product_id,
		"Name" => $product->product_name,
		"Qty" => $product->quantity,
	);
}
$data->amount = round($priceInCents);
$data->currency = $currency;
$data->exponent = $paylikeCurrency->getPaylikeCurrency($currency)['exponent'];

$data->locale = $languageCode;
$data->customer = new stdClass();
$data->customer->name = $billingDetail->first_name . " " . $billingDetail->last_name ;
$data->customer->email = $billingDetail->email ;
$data->customer->phoneNo = $billingDetail->phone_1 ;
$data->customer->IP = $_SERVER["REMOTE_ADDR"];
$data->platform = array(
	'name' => 'Joomla',
	'version' => $this->getJoomlaVersions()
	);
$data->ecommerce = array(
	'name' => 'VirtueMart',
	'version' => $this->getVirtuemartVersions()
	);
$data->version = $this->version;
$data->ajaxUrl = juri::root(true).'/index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=paylike';
?>
<style>
	.paylike-info-hide{display:none;}
</style>
<script src="https://sdk.paylike.io/10.js"></script>


<div id="paylike-temp-info">
	<button type="button" class="btn btn-success btn-large btn-lg" id="paylike-pay"><?php echo jText::_('PAYLIKE_BTN'); ?></button>
	<br>
</div>
<div id="paylike-after-info" class="paylike-info-hide">
<div class="post_payment_payment_name" style="width: 100%">
	<?php echo  $viewData["payment_name"]; ?>
</div>

<div class="post_payment_order_number" style="width: 100%">
	<span class="post_payment_order_number_title"><?php echo vmText::_ ('COM_VIRTUEMART_ORDER_NUMBER'); ?> </span>
	<?php echo  $billingDetail->order_number; ?>
</div>

<div class="post_payment_order_total" style="width: 100%">
	<span class="post_payment_order_total_title"><?php echo vmText::_ ('COM_VIRTUEMART_ORDER_PRINT_TOTAL'); ?> </span>
	<?php echo  $viewData['displayTotalInPaymentCurrency']; ?>
</div>
<?php
if($viewData["orderlink"]){
?>
<a class="vm-button-correct" href="<?php echo JRoute::_($viewData["orderlink"], false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>
<?php
}
?>
</div>
<script>
jQuery(document).ready(function($) {
	var datas = <?php echo json_encode($data) ?>;

	var publicKey = {
		key: datas.publicKey
	};

	paylike = Paylike({key: datas.publicKey});

	$('#paylike-pay').on('click',function(){
		pay();
	});
	function pay(){
		paylike.pay({
			test: ('1' == datas.testMode) ? (true) : (false),
			title: datas.title,
			description: datas.description,
			amount: {
				currency: datas.currency,
				exponent: datas.exponent,
				value:	datas.amount
			},
			locale: datas.locale,
			custom: {
				orderId: datas.orderId,
				orderNo: datas.orderNo,
				products: datas.products,
				customer: datas.customer,
				platform: datas.platform,
				ecommerce: datas.ecommerce,
				paylikePluginVersion: datas.version
				}
			}, function(err, r) {
				if (r != undefined) {
					var payData = {
							'paymentType' : 'captureTransactionFull',
							'transactionId' : r.transaction.id,
							'virtuemart_paymentmethod_id' : datas.virtuemart_paymentmethod_id,
							'format' : 'json'
						};
					$.ajax({
						type: "POST",
						url: datas.ajaxUrl,
						async: false,
						data: payData,
						success: function(data) {
							if(data.success =='1') {
								$('#paylike-after-info').toggleClass('.paylike-info-hide');
								$('#paylike-temp-info').remove();
							} else {
								alert(data.error);
								//callback(r,datas);
							}
						},
						dataType :'json'
					});
				}
			}
		);
	}
	pay();
});
</script>
