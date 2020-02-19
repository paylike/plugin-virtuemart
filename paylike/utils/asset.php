<?php

defined ('_JEXEC') or die('Restricted access');
$loadBootstrap = (int)$method->bootstrap == 0;
/* load scripts and stylesheets*/
$document = JFactory::getDocument();
$document->addScript( 'https://sdk.paylike.io/3.js' );
JHtml::_( 'jquery.framework' );
$document->addScript( JURI::base() . 'plugins/vmpayment/paylike/paylike.js' );
$document->addStyleSheet( JURI::base() . 'plugins/vmpayment/paylike/paylike.css' );
if($loadBootstrap) {
	$document->addScript( JURI::base() . 'plugins/vmpayment/paylike/bootstrap.min.js' );
	$document->addStyleSheet( JURI::base() . 'plugins/vmpayment/paylike/bootstrap.min.css' );
}
?>
<script>
if (typeof vmPaylike === "undefined"){
	var vmPaylike = {};
}
vmPaylike.method = {};
vmPaylike.site = '<?php echo juri::root(); ?>';
vmPaylike.methodId = <?php echo (int)$method->virtuemart_paymentmethod_id ?>;
</script>