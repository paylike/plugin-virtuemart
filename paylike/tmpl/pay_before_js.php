<?php defined ('_JEXEC') or die();
// set default paymentmethod_id and add script that dont need instance
$vponepagecheckout = JPluginHelper::isEnabled('system', 'vponepagecheckout');
 ?>
<?php if ($vponepagecheckout) { ?>
	<input style="display:none;" class="required" required="required" type="text" value="" id="vponepagecheckout">
<?php } ?>
<script>
if (typeof vmPaylike === "undefined"){
	var vmPaylike = {};
	 jQuery.getScript("https://sdk.paylike.io/10.js", function(){});
}
vmPaylike.method = {};
vmPaylike.site = '<?php echo juri::root(true); ?>/index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=json';
vmPaylike.methodId = <?php echo (int)$method->virtuemart_paymentmethod_id ?>;
vmPaylike.paymentDone = false;

<?php if ($vponepagecheckout) { ?>
	jQuery(document).ready(function() {
	  var bindCheckoutForm = function() {
        var form = jQuery('#checkoutForm');

        if (!form.data('vmPaylike-ready')) {
            form.on('submit', function(e) {
                if (!form.data('vmPaylike-verified')) {
                    e.preventDefault();
					var $selects = jQuery("[name=virtuemart_paymentmethod_id]"),
						methodId  = $selects.length ? jQuery("[name=virtuemart_paymentmethod_id]:checked").val() : 0,
						id = 0,
						data = {'paylikeTask' : 'cartData'};
					//set default method, if no select list of payments
					if($selects.length ===0) {
						id = vmPaylike.methodId;
					} else if (vmPaylike.method.hasOwnProperty("ID"+methodId)) {
						id = vmPaylike.method["ID"+methodId];
					}
					if(id !== 0) {
						data.virtuemart_paymentmethod_id = id;
						// Get payment info for this method ID
						jQuery.getJSON( vmPaylike.site, data, function( datas ) {

							publicKey = {
								key: datas.publicKey
							};

							paylike = Paylike(publicKey);

							paylike.pay({
								test: ('1' == datas.testMode) ? (true) : (false),
								title: vmPaylike.popup_title,
								description: datas.description,
								amount: {
									currency: datas.currency,
									exponent: datas.exponent,
									value:	datas.amount
								},
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
										jQuery.ajax({
											type: "POST",
											url: vmPaylike.site,
											async: false,
											data: payData,
											success: function(data) {
												if(data.success =='1') {
													validate = true;
													form.data('vmPaylike-verified', true);
													form.submit();
												} else {
													ProOPC.setmsg(data.error);
													// alert(data.error);
													cancelSubmit();
													//callback(r,datas);
												}
											},
											dataType :'json'
										});
									} else {
										cancelSubmit();
									}
								}
							);
						});

						return false;
					} else form.data('vmPaylike-verified', true);

                }
            });

            form.data('vmPaylike-ready', true);

        }
      };
	 bindCheckoutForm();
	jQuery(document).on('vpopc.event', function(event, type) {
		var form = jQuery('#checkoutForm');
			if(type == 'checkout.updated.shipmentpaymentcartlist'
				|| type == 'checkout.updated.cartlist'
				|| type == 'prepare.data.payment') form.data('vmPaylike-verified', false);
		if(type == 'checkout.finalstage') {
			validate = form.data('vmPaylike-verified', false);
		}
	});
     // Bind on ajaxStop
     jQuery(document).ajaxStop(function() {
        bindCheckoutForm();
     });

	function cancelSubmit() {
		var form = jQuery('#checkoutForm');
		validate = form.data('vmPaylike-verified', false);
		ProOPC.removePageLoader();
		ProOPC.enableSubmit();
		document.location.reload(true);
	}
	});
<?php } else { ?>

jQuery(document).ready(function() {
	var $container = jQuery(Virtuemart.containerSelector),
		paymentDone = false;
	$container.find('#checkoutForm').on('submit',function(e) {
		// payment is done, then submit
		if(paymentDone === true) return;
		//check the selected paymentmethod
		var $selects = jQuery("[name=virtuemart_paymentmethod_id]"),
			methodId  = $selects.length ? jQuery("[name=virtuemart_paymentmethod_id]:checked").val() : 0,
			id = 0,
			data = {'paylikeTask' : 'cartData'},
			confirm = jQuery(this).find('input[name="confirm"]').length,
			$btn = jQuery('#checkoutForm').find('button[name="confirm"]'),
			checkout = $btn.attr('task');

		// return false;
		if(confirm === 0 || checkout ==='checkout') return;
		//set default method, if no select list of payments
		if($selects.length ===0) {
			id = vmPaylike.methodId;
		} else if (vmPaylike.method.hasOwnProperty("ID"+methodId)) {
			id = vmPaylike.method["ID"+methodId];
		}
		if(id === 0) return;
		data.virtuemart_paymentmethod_id = id;

		// Get payment info for this method ID
		jQuery.getJSON( vmPaylike.site, data, function( datas ) {
			$btn.prop('disabled', false).addClass('vm-button-correct').removeClass('vm-button');
			jQuery(this).vm2front('stopVmLoading');

			publicKey = {
				key: datas.publicKey
			};

			paylike = Paylike(publicKey);

			paylike.pay({
				test: ('1' == datas.testMode) ? (true) : (false),
				title: vmPaylike.popup_title,
				description: datas.description,
				amount: {
					currency: datas.currency,
					exponent: datas.exponent,
					value:	datas.amount
				},
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
						jQuery.ajax({
							type: "POST",
							url: vmPaylike.site,
							async: false,
							data: payData,
							success: function(data) {
								if(data.success =='1') {
									paymentDone = true;
									$container.find('#checkoutForm').submit();
									jQuery(this).vm2front('startVmLoading');
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
		e.preventDefault();
		return false;
	});
	// TODO jQuery(this).attr('disabled', 'false');
	// CheckoutBtn = Virtuemart.bCheckoutButton ;
	// if(Virtuemart.container
	// Virtuemart.bCheckoutButton = function(e) {
		// e.preventDefault();
	// }
});

<?php } ?>
</script>
