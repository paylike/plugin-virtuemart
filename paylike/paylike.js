//alert("dfdff");
window.setTimeout(function () {
    bindPaylikeHandler();
}, 1000);

function bindPaylikeHandler() {
    if (jQuery("[name=virtuemart_paymentmethod_id]").length > 0) {
        var methodId = jQuery("[name=virtuemart_paymentmethod_id]:checked").val();
        var paylikePaymentMethod = jQuery("[name=paylikePaymentMethod]").val();
        jQuery('[name=checkout]').removeClass("payment_pending");
        if (methodId == paylikePaymentMethod) {
            jQuery('[name=confirm]').addClass("payment_pending");
            jQuery(".payment_pending").unbind("click");
            addPaymentClick();
        }
        else {
            jQuery('[name=confirm]').removeClass("payment_pending");
        }
    }
    else {
        jQuery('[name=checkout]').removeClass("payment_pending");
        jQuery('[name=confirm]').addClass("payment_pending");
        jQuery(".payment_pending").unbind("click");
        addPaymentClick();
    }
}

jQuery(document).ready(function () {
    jQuery("[name=virtuemart_paymentmethod_id]").change(function (e) {
        window.setTimeout(function () {
            bindPaylikeHandler();
        }, 1000);
    });
});

jQuery(function () {

    jQuery("#afterOrderPaylike").click(function () {
        var key = get_api_key();
        var paylike = Paylike(key);
        var pCurrency = jQuery("[name=paylikeCurrency]").val();
        var pAmount = jQuery("[name=paylikePrice]").val();
        var paylikeEmail = jQuery("[name=paylikeEmail]").val();
        var paylikeName = jQuery("[name=paylikeName]").val();
        var paylikePhone = jQuery("[name=paylikePhone]").val();
        var paylikeAddress = jQuery("[name=paylikeAddress]").val();
        var paylikeIp = jQuery("[name=paylikeIp]").val();
        var paylikePlatformName = jQuery("[name=paylikePlatformName]").val();
        var paylikePlatformVersion = jQuery("[name=paylikePlatformVersion]").val();
        var paylikeEcommerce = jQuery("[name=paylikeEcommerce]").val();
        var paylikeEcommerceVersion = jQuery("[name=paylikeEcommerceVersion]").val();
        var paylikePopupTitle = jQuery("[name=paylikePopupTitle]").val();
        var paylikeLocale = jQuery("[name=paylikeLocale]").val();
        var virtuemartOrderId = jQuery("[name=virtuemart_order_id]").val();
        data = {};
        i = 0;
        k = 0;
        jQuery("[name='paylikeProductId[]']").each(function () {
            data[k] = Array();
            data[k]["Id"] = jQuery(this).val();
            k++;
        });
        jQuery("[name='paylikeProductName[]']").each(function () {
            data[i]["Name"] = jQuery(this).val();
            i++;
        });
        j = 0;

        jQuery("[name='paylikeQuantity[]']").each(function () {
            data[j]["Qty"] = jQuery(this).val();
            j++;
        });

        paylike.popup({
            title: paylikePopupTitle,
            currency: pCurrency,
            amount: pAmount,
            locale: paylikeLocale,
            custom: {
                orderNo: virtuemartOrderId,
                products: [data],
                customer: {
                    name: paylikeName,
                    email: paylikeEmail,
                    phoneNo: paylikePhone,
                    address: paylikeAddress,
                    IP: paylikeIp
                },
                platform: {
                    name: paylikePlatformName,
                    version: paylikePlatformVersion
                },
                ecommerce: {
                    name: paylikeEcommerce,
                    version: paylikeEcommerceVersion
                },
                paylikePluginVersion: '1.1.1'
            }
        }, function (err, r) {
            if (r != undefined) {
                var paylikeCaptureMode = jQuery("[name=paylikeCaptureMode]").val();
                var paylikePaymentMethod = jQuery("[name=paylikePaymentMethod]").val();
                jQuery("[name=transactionId]").val(r.transaction.id);
                formData = jQuery("#capturePayment").serialize();
                var paylikeBase = jQuery("[name=paylikeBase]").val();
                jQuery.ajax({
                    type: "POST",
                    url: paylikeBase + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&virtuemart_paymentmethod_id=" + paylikePaymentMethod + "&" + formData + "&paylikeCaptureMode=" + paylikeCaptureMode + "&transactionId=" + r.transaction.id,
                    async: false,
                    data: "",
                    success: function (e) {
                        jQuery(".vm-order-done").show();
                        jQuery('#myModal .modal-footer button').trigger('click');
                        postData(r.transaction.id);
                    }
                });
            }
        });
    });
});

function popup() {
    var key = get_api_key();
    var paylike = Paylike(key);
    var pCurrency = jQuery("[name=paylikeCurrency]").val();
    var pAmount = jQuery("[name=paylikePrice]").val();
    var paylikeEmail = jQuery("[name=paylikeEmail]").val();
    var paylikeName = jQuery("[name=paylikeName]").val();
    var paylikePhone = jQuery("[name=paylikePhone]").val();
    var paylikeAddress = jQuery("[name=paylikeAddress]").val();
    var paylikeIp = jQuery("[name=paylikeIp]").val();
    var paylikePlatformName = jQuery("[name=paylikePlatformName]").val();
    var paylikeLocale = jQuery("[name=paylikeLocale]").val();
    var paylikePlatformVersion = jQuery("[name=paylikePlatformVersion]").val();
    var paylikeEcommerce = jQuery("[name=paylikeEcommerce]").val();
    var paylikeEcommerceVersion = jQuery("[name=paylikeEcommerceVersion]").val();
    var paylikePopupTitle = jQuery("[name=paylikePopupTitle]").val();
    data = {};
    i = 0;
    k = 0;
    jQuery("[name='paylikeProductId[]']").each(function () {
        data[k] = Array();
        data[k]["Id"] = jQuery(this).val();
        k++;
    });
    jQuery("[name='paylikeProductName[]']").each(function () {
        data[i]["Name"] = jQuery(this).val();
        i++;
    });
    j = 0;

    jQuery("[name='paylikeQuantity[]']").each(function () {
        data[j]["Qty"] = jQuery(this).val();
        j++;
    });

    paylike.popup({
        title: paylikePopupTitle,
        currency: pCurrency,
        amount: pAmount,
        locale: paylikeLocale,
        custom: {
            orderNo: '',
            products: [data],
            customer: {
                name: paylikeName,
                email: paylikeEmail,
                phoneNo: paylikePhone,
                address: paylikeAddress,
                IP: paylikeIp
            },
            platform: {
                name: paylikePlatformName,
                version: paylikePlatformVersion
            },
            ecommerce: {
                name: paylikeEcommerce,
                version: paylikeEcommerceVersion
            },
            paylikePluginVersion: '1.1.1'
        }
    }, function (err, r) {
        if (r != undefined) {

            var paylikeCaptureMode = jQuery("[name=paylikeCaptureMode]").val();
            var paylikePaymentMethod = jQuery("[name=paylikePaymentMethod]").val();
            jQuery("[name=transactionId]").val(r.transaction.id);
            formData = jQuery("#capturePayment").serialize();
            var paylikeBase = jQuery("[name=paylikeBase]").val();
            jQuery.ajax({
                type: "POST",
                url: paylikeBase + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&virtuemart_paymentmethod_id=" + paylikePaymentMethod + "&" + formData + "&paylikeCaptureMode=" + paylikeCaptureMode + "&transactionId=" + r.transaction.id,
                async: false,
                data: "",
                success: function (e) {
                    jQuery('#checkoutFormSubmit').removeClass("payment_pending");
                    jQuery('#checkoutFormSubmit').trigger("click");
                }
            });
        }
    });
}

function get_api_key() {
    var paylikePaymentMethod = jQuery("[name=paylikePaymentMethod]").val();
    var paylikeBase = jQuery("[name=paylikeBase]").val();
    var s = "";
    jQuery.ajax({
        type: "POST",
        url: paylikeBase + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&virtuemart_paymentmethod_id=" + paylikePaymentMethod,
        async: false,
        data: "",
        success: function (e) {
            s = e
        }
    });
    return s
}

function addPaymentClick() {
    jQuery(".payment_pending").click(function (event) {
        if (jQuery(".payment_pending").length > 0) {
            event.preventDefault();
            var paylikeMode = jQuery("[name=paylikeMode]").val();
            if (paylikeMode != 'after') {
                popup();
            }
            else {
                jQuery('#checkoutFormSubmit').removeClass("payment_pending");
                prevHref = jQuery('#checkoutForm').attr("action");
                if (prevHref.indexOf("?") >= 0) {
                    jQuery('#checkoutForm').attr("action", prevHref + "&payment=1");
                }
                else {
                    jQuery('#checkoutForm').attr("action", prevHref + "?payment=1");
                }

                jQuery('#checkoutFormSubmit').trigger("click");
            }

            return false;
        }
    });
}

jQuery(document).ready(function () {
    i = 0;
    if (jQuery("[name=order_virtuemart_paymentmethod_id]").length > 0) {
        jQuery(".orderStatFormSubmit").unbind('click');
        jQuery("[name=orderStatForm]").attr("name", "orderStatFormTest")
        jQuery(".orderStatFormSubmit").click(function (event) {
            orderNumber = jQuery("[name=orderId]").val();
            delayOrderStatus = jQuery("[name=delayOrderStatus]").val();
            status = jQuery("#order_items_status_chzn span").text().toLowerCase();
            dataToSendForComplete = Array();
            dataToSendForRefund = Array();
            dataToSendForRefundHalf = Array();
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
            var paylikeBase = jQuery("[name=paylikeBase]").val();
            if (i > 0) {
                jQuery.ajax({
                    type: "POST",
                    url: paylikeBase + "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&CapturePayment=true" + "&dataToSendForComplete=" + dataToSendForComplete + "&dataToSendForRefund=" + dataToSendForRefund + "&dataToSendForRefundHalf=" + dataToSendForRefundHalf,
                    async: false,
                    data: "",
                    success: function (e) {
                        jQuery("[name=orderStatFormTest]").attr("name", "orderStatForm");
                        jQuery("[name=orderStatForm]").submit();
                    }
                });
            }
            return false;

        });
    }
});

function postData(transactId = 0) {
    var paylikePaymentMethod = jQuery("[name=paylikePaymentMethod]").val();
    var pAmount = jQuery("[name=paylikePrice]").val();
    var paylikeTitle = jQuery("[name=paylikeTitle]").val();
    var order_number = jQuery("[name=order_number]").val();
    var virtuemart_order_id = jQuery("[name=virtuemart_order_id]").val();
    var transactionId = transactId;
    jQuery.ajax({
        type: "POST",
        url: "index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=paylike&format=ajax&save=1&virtuemart_paymentmethod_id=" + paylikePaymentMethod + "&pAmount=" + pAmount + "&paylikeTitle=" + paylikeTitle + "&order_number=" + order_number + "&virtuemart_order_id=" + virtuemart_order_id + "&transactionId=" + transactionId,
        async: false,
        data: "",
        success: function (e) {

        }
    });
}


