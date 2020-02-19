paylikeSubmitHandler();

jQuery(function() {

	jQuery("#afterOrderPaylike").click(function() {
		popup(function(r,datas) {
			jQuery(".vm-order-done").show();
			jQuery('#myModal .modal-footer button').trigger('click');
			postData(r.transaction.id,datas.paymentmethod_id);
		});
	});
});

function popup(callback) {
	var datas = get_api_info();
	var paylike = Paylike(datas.publicKey);
	var virtuemartOrderId = jQuery("[name=virtuemart_order_id]").val();
	data = {};
	i = 0;
	k = 0;
	// jQuery("[name='paylikeProductId[]']").each(function() {
		// data[k] = Array();
		// data[k]["Id"] = jQuery(this).val();
		// k++;
	// });
	// jQuery("[name='paylikeProductName[]']").each(function() {
		// data[i]["Name"] = jQuery(this).val();
		// i++;
	// });
	// j = 0;

	// jQuery("[name='paylikeQuantity[]']").each(function() {
		// data[j]["Qty"] = jQuery(this).val();
		// j++;
	// });

	paylike.popup({
		title: datas.popupTitle,
		currency: datas.currency,
		amount: datas.amount,
		locale: datas.locale,
		custom: {
			orderNo: virtuemartOrderId,
			products: datas.products,
			customer: datas.customer,
			platform: datas.platform,
			ecommerce: datas.ecommerce,
			paylikePluginVersion: datas.version,
			paylikeID : datas.paylikeID
		}
	}, function(err, r) {
		if (r != undefined) {
			var payData = {
					'paymentType' : 'captureTransactionFull',
					'transactionId' : r.transaction.id,
					'virtuemart_paymentmethod_id' : datas.paymentmethod_id,
				}
			jQuery.ajax({
				type: "POST",
				url: vmSiteurl + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax",
				async: false,
				data: payData,
				success: function(e) {
					console.log('captureTransactionFull',e,r);
					callback(r,datas);
				}
			});
		}
	});
}

function get_api_key() {
	var paylikePaymentMethod = jQuery("[name=paylikePaymentMethod]").val();
	var s = "";
	jQuery.ajax({
		type: "POST",
		url: vmSiteurl + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&virtuemart_paymentmethod_id=" + paylikePaymentMethod,
		async: false,
		data: "",
		success: function(e) {
			
			console.log(e);
			s = e
		}
	});
	return s
}
function get_api_info() {
	var methodId  = jQuery("[name=virtuemart_paymentmethod_id]:checked").val();
	if(typeof (methodId) === "undefined") methodId = vmPaylike.methodId;
	var s = "";
	jQuery.ajax({
		type: "POST",
		url: vmPaylike.site + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&virtuemart_paymentmethod_id=" + methodId,
		async: false,
		data: "",
		success: function(e) {
			s = e;
			console.log(s);
		},
        dataType: "json"
	});
	return s
}

function paylikeSubmitHandler() {
	jQuery(document).on('submit', '#checkoutForm', function(event) {
		var $submit = jQuery('#checkoutFormSubmit');
		var name = $submit.attr('name');
		if (name !== 'confirm') {
			return true;
		}
		
		var methodId = jQuery("[name=virtuemart_paymentmethod_id]:checked").val();
		if (!vmPaylike.method.hasOwnProperty(methodId)) {
			//in case we have no methods, then use default
			if(jQuery("[name=virtuemart_paymentmethod_id]").length == 0) {
				methodId = vmPaylike.methodId;
				console.log(methodId + " use default");
			} else console.log(methodId + "not found");
		}
		var data = vmPaylike.method[methodId];

		var paylikeMode = jQuery("[name=paylikeMode]").val();
		var payment_sent = $submit.hasClass('payment_sent');
		var payment_after = $submit.hasClass('payment_after');
		if (payment_sent || payment_after) {
			return true;
		}

		jQuery(this).vm2front('stopVmLoading');
		jQuery('#checkoutFormSubmit').attr('disabled',false)
			.removeClass( 'vm-button' )
			.addClass( 'vm-button-correct' );
		var name = jQuery('#checkoutFormSubmit').attr('name');
		jQuery('#checkoutForm').find('input:hidden[name="'+name+'"]').remove();
		console.log(name);
		event.preventDefault();
		if (paylikeMode == 'after') {
			$submit.addClass("payment_after");
			var prevHref = jQuery(this).attr("action");
			if (prevHref.indexOf("?") >= 0) {
				jQuery(this).attr("action", prevHref + "&payment=1");
			} else {
				jQuery(this).attr("action", prevHref + "?payment=1");
			}
			jQuery(this).submit();
			return false;
		}
		popup(function() {
			jQuery('#checkoutFormSubmit').addClass("payment_sent");
			jQuery('#checkoutFormSubmit').trigger("click");
		});
		return false;
	});
}

jQuery(document).ready(function() {

	i = 0;
	if (jQuery("[name=order_virtuemart_paymentmethod_id]").length > 0) {
		jQuery(".orderStatFormSubmit").unbind('click');
		jQuery("[name=orderStatForm]").attr("name", "orderStatFormTest")
		jQuery(".orderStatFormSubmit").click(function(event) {
			var orderNumber = jQuery("[name=orderId]").val();
			var delayOrderStatus = jQuery("[name=delayOrderStatus]").val();
			var status = jQuery("#order_items_status_chzn span").text().toLowerCase();
			var dataToSendForComplete = Array();
			var dataToSendForRefund = Array();
			var dataToSendForRefundHalf = Array();
			if (status == "completed" || status == "shipped" || status == delayOrderStatus) {
				dataToSendForComplete.push(orderNumber);
				i++;
			}
			if (status == "refunded" || status == "cancelled") {
				dataToSendForRefund.push(orderNumber);
				i++;
			}
			if (status == "refund half") {
				dataToSendForRefundHalf.push(orderNumber);
				i++;
			}
			if (dataToSendForRefund.length == 0) {
				dataToSendForRefund = "";
			} else {
				dataToSendForRefund = JSON.stringify(dataToSendForRefund);
			}
			if (dataToSendForComplete.length == 0) {
				dataToSendForComplete = "";
			} else {
				dataToSendForComplete = JSON.stringify(dataToSendForComplete);
			}
			if (dataToSendForRefundHalf.length == 0) {
				dataToSendForRefundHalf = "";
			} else {
				dataToSendForRefundHalf = JSON.stringify(dataToSendForRefundHalf);
			}
			if (i > 0) {
				jQuery.ajax({
					type: "POST",
					url: vmSiteurl + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&CapturePayment=true" + "&dataToSendForComplete=" + dataToSendForComplete + "&dataToSendForRefund=" + dataToSendForRefund + "&dataToSendForRefundHalf=" + dataToSendForRefundHalf,
					async: false,
					data: "",
					success: function(e) {
						jQuery("[name=orderStatFormTest]").attr("name", "orderStatForm");
						jQuery("[name=orderStatForm]").submit();
					}
				});
			}
			return false;

		});
	}
});

function postData(transactionId,methodId) {
	var virtuemart_order_id = jQuery("[name=virtuemart_order_id]").val();
	jQuery.ajax({
		type: "POST",
		url: vmSiteurl + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&save=1&transactionId=" + transactionId+"&virtuemart_paymentmethod_id="+methodId+"&virtuemart_order_id="+virtuemart_order_id,
		async: false,
		data: "",
		success: function(e) {
			
		}
	});
}


