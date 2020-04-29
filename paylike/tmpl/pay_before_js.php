<?php defined ('_JEXEC') or die();
// set default paymentmethod_id and add script that dont need instance
 ?>

<script>
if (typeof vmPaylike === "undefined"){
	var vmPaylike = {};
	 jQuery.getScript("https://sdk.paylike.io/4.js", function(){});
}
vmPaylike.method = {};
vmPaylike.site = '<?php echo juri::root(true); ?>/index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=json';
vmPaylike.methodId = <?php echo (int)$method->virtuemart_paymentmethod_id ?>;


jQuery(document).ready(function($) {
	var $container = $(Virtuemart.containerSelector),
		paymentDone = false;
	$container.find('#checkoutForm').on('submit',function(e) {
		// payment is done, then submit
		if(paymentDone === true) return;
		//check the selected paymentmethod
		var $selects = $("[name=virtuemart_paymentmethod_id]"),
			methodId  = $selects.length ? $("[name=virtuemart_paymentmethod_id]:checked").val() : 0,
			id = 0,
			data = {'paylikeTask' : 'cartData'},
			confirm = $(this).find('input[name="confirm"]').length,
			$btn = jQuery('#checkoutForm').find('button[name="confirm"]'),
			checkout = $btn.attr('task');
			
		console.log($btn);
		// return false;
		// console.log(confirm+' checkout '+checkout);
		if(confirm === 0 || checkout ==='checkout') return;
		//set default method, if no select list of payments
		if($selects.length ===0) {
			id = vmPaylike.methodId;
			
			console.log("default method :"+id);
		} else if (vmPaylike.method.hasOwnProperty("ID"+methodId)) {
			id = vmPaylike.method["ID"+methodId];
			console.log("method from list:"+id);
		}
		if(id === 0) return;
		console.log(id);
		data.virtuemart_paymentmethod_id = id;
		
		// Get payment info for this method ID 
		$.getJSON( vmPaylike.site, data, function( datas ) {
			$btn.prop('disabled', false).addClass('vm-button-correct').removeClass('vm-button');
			$(this).vm2front('stopVmLoading');
			paylike = Paylike(datas.publicKey);
			paylike.popup({
				title: datas.title,
				description: datas.description,
				currency: datas.currency,
				amount: datas.amount,
				locale: datas.locale,
				custom: {
					//orderId: datas.orderId,
					paylikeID: datas.paylikeID,
					products: datas.products,
					customer: datas.customer,
					platform: datas.platform,
					ecommerce: datas.ecommerce,
					paylikePluginVersion: datas.version
					}
				}, function(err, r) {
					if (r != undefined) {
						var payData = {
								'paylikeTask' : 'saveInSession',
								'transactionId' : r.transaction.id,
								'virtuemart_paymentmethod_id' : data.virtuemart_paymentmethod_id
							};
						$.ajax({
							type: "POST",
							url: vmPaylike.site,
							async: false,
							data: payData,
							success: function(data) {
								// console.log('captureTransactionFull',txt);
								console.log('paylike datas',e,r);
								if(data.success =='1') {
									paymentDone = true;
									$container.find('#checkoutForm').submit();
									$(this).vm2front('startVmLoading');
									$btn.attr('disabled', 'true');
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
		});
		console.log(id + " paylike");
		e.preventDefault();
		return false;
	});
	// TODO jQuery(this).attr('disabled', 'false');
	// CheckoutBtn = Virtuemart.bCheckoutButton ;
	// if(Virtuemart.container
	// Virtuemart.bCheckoutButton = function(e) {
		// e.preventDefault();
		// console.log('submit now');
	// }
});
</script>
