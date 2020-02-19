<?php

include_once( 'Paylike/Client.php' );
include_once( 'utils/PaylikeCurrency.php' );
if ( ! class_exists( 'vmPSPlugin' ) ) {
	require( JPATH_VM_PLUGINS . DS . 'vmpsplugin.php' );
}

class plgVmPaymentPaylike extends vmPSPlugin {

	// instance of class
	public static $_this = false;
	public $version = '1.1.4';
	function __construct( & $subject = false, $config = false ) {

		parent::__construct( $subject, $config );
		$this->id = array();
		$this->paylikeCurrency = new PaylikeCurrency();
		
		$this->_loggable = true;
		$this->tableFields = array_keys( $this->getTableSQLFields() );
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->addOptionForOrderStatus(); // add Refund half option to database

		$varsToPush = array(
			'title'              => array( '', 'text' ),
			'active'             => array( '', 'text' ),
			'test_mode'          => array( '', 'int' ),
			'description'        => array( '', 'text' ),
			'live_api_key'       => array( '', 'char' ),
			'live_public_key'    => array( '', 'char' ),
			'test_api_key'       => array( '', 'char' ),
			'test_public_key'    => array( '', 'char' ),
			'popup_title'        => array( '', 'char' ),
			'checkout_mode'      => array( '', 'char' ),
			'capture_mode'       => array( '', 'char' ),
			'card'               => array( '', 'char' ),
			'mastercard'         => array( '', 'char' ),
			'maestro'            => array( '', 'char' ),
			'visa'               => array( '', 'char' ),
			'visaelectron'       => array( '', 'char' ),
			'status_capture'     => array( '', 'char' ),
			'status_success'     => array( '', 'char' ),
			'status_refunded'    => array( '', 'char' ),
			'delay_order_status' => array( '', 'char' ),
			'cost_per_transaction'    => array( '', 'int' ),
			'cost_min_transaction'    => array( '', 'int' ),
			'cost_percent_total'      => array( '', 'int' ),
			'tax_id'                  => array( 0, 'int' ),
			'bootstrap'                  => array( 0, 'int' )
		);
		if(method_exists($this,'addVarsToPushCore')) $this->addVarsToPushCore($varsToPush, 1);
		/* save setting params to database */
		$this->setConfigParameterable( $this->_configTableFieldName, $varsToPush );
		$this->setConvertable(array('cost_per_transaction','cost_min_transaction'));
		$this->setConvertDecimal(array('cost_per_transaction','cost_min_transaction','cost_percent_total'));
	}

	/* create a new table for paylike transaction id and order detail */
	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL( 'Payment PAYLIKE Table' );
	}

	function getTableSQLFields() {
		$SQLfields = array(
			'id'                          => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => ' char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'amount'                      => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'status'                      => 'varchar(225)',
			'mode'                        => 'varchar(225)',
			'productinfo'                 => 'text',
			'txnid'                       => 'varchar(29)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_min_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)',
			'status_pending'              => 'char(3)',
		);

		return $SQLfields;
	}

	/* check if module is enabled or not before showing option in frontend */
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		$view = vRequest::get('view');
		if($view !== 'plugin') {
			$method->min_amount = (float)str_replace(',','.',$method->min_amount);
			$method->max_amount = (float)str_replace(',','.',$method->max_amount);
			$amount = $this->getCartAmount($cart_prices);
			$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
			$valid = false;
			if($this->_toConvert){
				$this->convertToVendorCurrency($method);
			}
			//vmdebug('standard checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
			$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
				OR
				($method->min_amount <= $amount AND ($method->max_amount == 0)));
			if (!$amount_cond) {
				return FALSE;
			}
			$countries = array();
			if (!empty($method->countries)) {
				if (!is_array ($method->countries)) {
					$countries[0] = $method->countries;
				} else {
					$countries = $method->countries;
				}
			}

			// probably did not gave his BT:ST address
			if (!is_array ($address)) {
				$address = array();
				$address['virtuemart_country_id'] = 0;
			}

			if (!isset($address['virtuemart_country_id'])) {
				$address['virtuemart_country_id'] = 0;
			}
			if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
				$valid = TRUE;
			} else return FALSE;

			

			/* check if module is active */
			// vmdebug($method);
			require_once(__DIR__.'/utils/asset.php');
			if ( ! in_array( $method->virtuemart_paymentmethod_id, $this->id ) ) {
				array_push( $this->id, $method->virtuemart_paymentmethod_id );
				/* if sandbox mode */
				if ( $method->test_mode == 1 ) {
					$apiKey = $method->test_api_key;
					$publicKey = $method->test_public_key;
					$mode = "test";
				} /* if live mode */
				else {
					$apiKey = $method->live_api_key;
					$publicKey = $method->live_public_key;
					$mode = "live";
				}
				

				/* load params  to send in Paylike */
				$data = new stdClass();
				$data->id = $method->virtuemart_paymentmethod_id;
				 ?>
				<script>
				vmPaylike.method[<?php echo $method->virtuemart_paymentmethod_id ?>] = <?php echo json_encode($data); ?>;
				</script>
			<?php 
				}
				
				vmdebug('Paylike checkConditions', $mode);
		}
		// return parent::checkConditions($cart, $method, $cart_prices);
		return true;
	}
	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

			if (preg_match ('/%$/', $method->cost_percent_total)) {
				$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
			} else {
				$cost_percent_total = $method->cost_percent_total;
			}
			return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}
	
	function plgVmOnStoreInstallPaymentPluginTable( $jplugin_id ) {
		return $this->onStoreInstallPluginTable( $jplugin_id );
	}
	
	/*
	* plgVmonSelectedCalculatePricePayment
	* Calculate the price (value, tax_id) of the selected method
	* It is called by the calculator
	* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	* @cart: VirtueMartCart the current cart
	* @cart_prices: array the new cart prices
	* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	*
	*
	*/
	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}
	
	/* On Select Payment Method on frontend, show related detail */
	public function plgVmOnSelectCheckPayment( VirtueMartCart $cart ) {
		return $this->OnSelectCheck( $cart );
	}

	/*Show list of payment methods */
	public function plgVmDisplayListFEPayment( VirtueMartCart $cart, $selected = 0, &$htmlIn ) {
		//$htmlIn ="dsgsgsgsd";
		return $this->displayListFE( $cart, $selected, $htmlIn );
	}



	public function plgVmOnShowOrderFEPayment( $virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name ) {
		return $this->onShowOrderFE( $virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name );
	}

	/* if single payment method enable, show automatically selected paylike payment method */
	function plgVmOnCheckAutomaticSelectedPayment( VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter ) {
		return $this->onCheckAutomaticSelected( $cart, $cart_prices, $paymentCounter );
	}

	function plgVmonShowOrderPrintPayment( $order_number, $method_id ) {
		return $this->onShowOrderPrint( $order_number, $method_id );
	}

	function plgVmDeclarePluginParamsPayment( $name, $id, &$data ) {
		return $this->declarePluginParams( 'payment', $name, $id, $data );
	}

	protected function renderPluginName( $plugin ) {
		// echo "<pre>";print_r($card);
		$return = "<div class='paylike-wrapper' style='display: inline-block'><div class='paylike_title' >" . $plugin->title . "</div>";
		$return .= "<div class='payment_logo' >";
		if(!empty($plugin->card)) $card = implode( "~", $plugin->card );
		$version = explode( ".", $this->getJoomlaVersions() );
		$virtVersion = $this->getVirtuemartVersions();
		if ( ( $version[0] >= 3 ) || ( $virtVersion >= 2.7 && $version[0] < 3 ) ) {
			$path = JURI::root();
		} else {
			$path = JURI::root()."plugins/vmpayment/paylike/images/";
		}

		if (empty($card )) {
			$card = 'mastercard,maestro,visa,visaelectron';
		}

		if ( $plugin->mastercard != "" && strpos( $card, "mastercard" ) !== false ) {
			$return .= "<img src='" . $path . $plugin->mastercard . "' />";
		}
		if ( $plugin->maestro != "" && strpos( $card, "maestro" ) !== false ) {
			$return .= "<img src='" . $path . $plugin->maestro . "' />";
		}
		if ( $plugin->visa != "" && strpos( $card, "visa" ) !== false ) {
			$return .= "<img src='" . $path . $plugin->visa . "' />";
		}
		if ( $plugin->visaelectron != "" && strpos( $card, "visaelectron" ) !== false ) {
			$return .= "<img src='" . $path . $plugin->visaelectron . "' />";
		}
		$return .= "</div></div>";
		$return .= '<div class="paylike_desc" >' . $plugin->description . '</div>';
		if ( $plugin->test_mode == 1 ) {
			$return .= '<div class="payment_box payment_method_paylike"><p>TEST MODE ENABLED. In test mode, you can use the card number 4100 0000 0000 0000 with any CVC and a valid expiration date. "<a href="https://github.com/paylike/sdk">See Documentation</a>".</p></div>';
		}


		return $return;
	}

	function plgVmConfirmedOrder( $cart, $order ) {
		$BT = $order['details']['BT'];
		if (!($method = $this->getVmPluginMethod ($BT->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		$currency_model = VmModel::getModel( 'currency' );
		$currency_id = $order["details"]["BT"]->order_currency;
		$displayCurrency = $currency_model->getCurrency( $currency_id );
		$currency = $displayCurrency->currency_symbol;
		$currency_code_3 = $displayCurrency->currency_code_3;
		
		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($BT->order_total,$method->payment_currency);

		//store order payment 
		$dbValues = array();
		$dbValues['payment_name'] = $method->title;
		if ( $plugin->test_mode == 1 ) {
			$dbValues['payment_name'] .= '<div class="payment_box payment_method_paylike"><p>TEST MODE ENABLED. In test mode, you can use the card number 4100 0000 0000 0000 with any CVC and a valid expiration date. "<a href="https://github.com/paylike/sdk">See Documentation</a>".</p></div>';
		}
		$dbValues['order_number'] = $BT->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $BT->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_min_transaction'] = $method->cost_min_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['amount'] = $totalInPaymentCurrency['value'];
		$dbValues['tax_id'] = $method->tax_id;
		$dbValues['mode'] = $method->checkout_mode.' | '.$method->capture_mode;
		$dbValues['txnid '] = $method->tax_id;
		$this->storePSPluginInternalData ($dbValues);
		$session = JFactory::getSession();
		if ( $method->checkout_mode != "after" ) { ?>
			<script type="text/javascript">
						jQuery(window).load(function() {
							setTimeout(function() {
								jQuery("body").append('<input type="hidden" name="virtuemart_order_id" value="<?php echo $BT->virtuemart_order_id; ?>" /><input type="hidden" name="order_number" value="<?php echo $order["details"]["BT"]->order_number; ?>" />');
								postData('<?php echo $session->get( 'transactionId' ); ?>','<?php echo $BT->virtuemart_paymentmethod_id; ?>');
							}, 2000);
						});
			</script>
			<?php
		} else { ?>
			<script type="text/javascript">
						jQuery(window).load(function() {
							setTimeout(function() {
								jQuery("body").append('<button type="button" class="orderDetail" class="btn btn-info btn-lg" data-toggle="modal" data-target="#myModal" style="display:none;">Open Modal</button><div class="modal fade" id="myModal" role="dialog"> <div class="modal-dialog" style="text-align: left;"> <div class="modal-content">     <div class="modal-header"> <button type="button" class="close" data-dismiss="modal">&times;</button> <h4 class="modal-title">Pay for Order</h4> </div> <div class="modal-body" style="padding: 10px 30px;"> <p><div class="orderDetail"><ul class="order_details"><li class="order">Order number:<strong><?php echo $order["details"]["BT"]->order_number; ?></strong></li><li class="date">Date:<strong><?php echo date( "M d,Y", strtotime( $order["details"]["BT"]->created_on ) ); ?></strong></li><li class="total">Total:<strong><?php echo $currency . number_format( $order["details"]["BT"]->order_total, 2 ); ?></strong></li><li class="method">Payment method:<strong><?php echo $method->title; ?></strong></li><li><a href="javascript:void(0);" id="afterOrderPaylike" >Pay</a></li></ul></div></p> </div> <div class="modal-footer"> <button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div> </div> </div><style>.modal-backdrop.fade.in{ opacity: .4;}#myModal { background: none;margin-left: auto;width: 100%;overflow: hidden;}</style><input type="hidden" name="virtuemart_order_id" value="<?php echo $order["details"]["BT"]->virtuemart_order_id; ?>" /><input type="hidden" name="order_number" value="<?php echo $order["details"]["BT"]->order_number; ?>" /><script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"><script src="<?php echo JURI::base(); ?>plugins/vmpayment/paylike/bootstrap.min.js"><\\/script>');
								jQuery("#myModal").modal();

								setTimeout(function() {
									jQuery("body").append('<script src="<?php echo JURI::base(); ?>plugins/vmpayment/paylike/paylike.js"><\/script>');
								}, 1000);
							}, 2000);
						});
			</script>
			<?php
		}
		$cart->emptyCart();
		$cart->removeCartFromSession();
	}
	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		vmLanguage::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('', $paymentTable->amount . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency) {
			$html .= $this->getHtmlRowBE ('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency );
		}
		$html .= '</table>' . "\n";
		return $html;
	}
	/*
		Predefined function of Virtualmart to save selected setting as plugin parameters
	*/
	function plgVmSetOnTablePluginParamsPayment( $name, $id, &$table ) {
		return $this->setOnTablePluginParams( $name, $id, $table );
	}

	function plgVmOnUpdateOrderPayment( $order, $old_order_status ) {

		if (!($method = $this->getVmPluginMethod ($order->virtuemart_paymentmethod_id))) {
			return NULL;
		}
		// Another method was selected, do nothing
		if ( ! $this->selectedThisElement( $method->payment_element ) ) {
			return false;
		}
		// vminfo('plgVmOnUpdateOrderPayment '.$method->payment_element.' '.$order->order_status);
		//Load only when updating status to shipped
		// var_dump($method); jexit();
		if ($order->order_status != $method->status_capture 
			&& $order->order_status != $method->status_success
			&& $order->order_status != $method->status_refunded
			
			) {
			// vminfo('Order_status not found '.$order->order_status.' in '.$method->status_capture.', '.$method->status_success.', '.$method->status_refunded );
			return null;
		}
		// vminfo('Order_status change ',$order->order_status);
		/* if testing mode */
		if ( $method->test_mode == 1 ) {
			$privateKey = $method->test_api_key ;
			$publicKey = $method->test_public_key ;
		} else /* if live mode */ {
			$privateKey = $method->live_api_key ;
			$publicKey = $method->live_public_key ;
		}
		$orderDetail = $this->getAllOrderDetails( $order->order_number );
		\Paylike\Client::setKey( $privateKey );
		if ( ($order->order_status == $method->status_capture && $method->capture_mode == "delayed")
			|| ($order->order_status == $method->status_success && $method->capture_mode == "instant") ) {
			
			$txtid = $orderDetail->txnid;
			$response = \Paylike\Transaction::fetch( $txtid );
			$amount = $response['transaction']['pendingAmount'];
			$currency = $response['transaction']['currency'];
			$data = array(
				'amount'   => $amount,
				'currency' => $currency
			);
			if ( $amount > 0 ) {
				$response = \Paylike\Transaction::capture( $txtid, $data );
			}
		} else if ( $order->order_status == $method->status_refunded ) {
			$txtid = $orderDetail->txnid;
			$response = \Paylike\Transaction::fetch( $txtid );
			/* refund payment if already captured */
			if ( $response['transaction']['capturedAmount'] > 0 ) {
				$amount = $response['transaction']['capturedAmount'];
				$data = array(
					'amount'     => $amount,
					'descriptor' => ""
				);
				$response = \Paylike\Transaction::refund( $txtid, $data );
			} else {
				/* void payment if not already captured */
				$data = array(
					'amount' => $response['transaction']['amount']
				);
				$response = \Paylike\Transaction::void( $txtid, $data );
			}
		} else if (  $order->order_status == "refund half" ) {
			// TODO ? not possible in Virtuemart
			// $orderDetail = $this->getAllOrderDetails( $order->order_number );
			// $txtid = $orderDetail->txnid;
			// $response = \Paylike\Transaction::fetch( $txtid ); // fetch order detail from paylike
			// /* refund payment if already captured */
			// if ( $response['transaction']['capturedAmount'] > 0 && $response['transaction']['refundedAmount'] <= 0 ) {
				// $amount = $response['transaction']['capturedAmount'];
				// $data = array(
					// 'amount'     => round( $amount / 2 ),
					// 'descriptor' => ""
				// );
				// $response = \Paylike\Transaction::refund( $txtid, $data );
			// } else {
				// /* void payment if not already captured */
				// $data = array(
					// 'amount' => $response['transaction']['amount']
				// );
				// $response = \Paylike\Transaction::void( $txtid, $data );
			// }
		}
	}

	/*
		Predefined function of Virtualmart to declare all settings params in payment variable
	*/
	function plgVmDeclarePluginParamsPaymentVM3( &$data ) {
		return $this->declarePluginParams( 'payment', $data );
	}

	function plgVmgetPaymentCurrency( $virtuemart_paymentmethod_id, $type = false ) {
		if ( ! ( $method = $this->getVmPluginMethod( $virtuemart_paymentmethod_id ) ) ) {
		}
		
		// Another method was selected, do nothing
		if ( ! $this->selectedThisElement( $method->payment_element ) ) {
			return false;
		}
		$this->getPaymentCurrency( $method );
		$paymentCurrencyId = $method->payment_currency;
		$currency_model = VmModel::getModel( 'currency' );
		$displayCurrency = $currency_model->getCurrency( $paymentCurrencyId );
		if ( $type == "code" ) {
			return $displayCurrency->currency_code_3;
		} else {
			return $displayCurrency->currency_symbol;
		}
	}


	/* load address of user */
	function loadAddress($billingDetail) {
		$address = "";
		if ( $billingDetail['address_1'] != "" ) {
			$address .= $billingDetail['address_1'] . ", ";
		}
		if ( $billingDetail['address_2'] != "" ) {
			$address .= $billingDetail['address_2'] . ", ";
		}
		if ( $billingDetail['city'] != "" ) {
			$address .= $billingDetail['city'] . ", ";
		}
		if ( $billingDetail['virtuemart_country_id'] != "" ) {
			$address .= $this->getCountryName( $billingDetail['virtuemart_country_id'] ) . ", ";
		}
		if ( $billingDetail['virtuemart_state_id'] != "" ) {
			$address .= $this->getStateName( $billingDetail['virtuemart_state_id'] ) . ", ";
		}
		if ( $billingDetail['zip'] != "" ) {
			$address .= $billingDetail['zip'] . " ";
		}

		return $address;
	}

	/* Used for many different purposes (Payment Capture, Refund, Half Refund and Void) */
	function plgVmOnSelfCallFE( $type, $name, &$render ) {
		$id = vRequest::getInt('virtuemart_paymentmethod_id',0);
		if ( ! ( $method = $this->getVmPluginMethod( $id ) ) ) {
			return NULL;
		}
		// Another method was selected, do nothing
		if ( ! $this->selectedThisElement( $method->payment_element ) ) {
			return false;
		}
		$session = JFactory::getSession();
		// var_dump($method); jexit();
		/* if testing mode */
		if ( $method->test_mode == 1 ) {
			$privateKey = $method->test_api_key;
			$publicKey = $method->test_public_key;
		} else /* if live mode */ {
			$privateKey = $method->live_api_key;
			$publicKey = $method->live_public_key;
		}
		\Paylike\Client::setKey( $privateKey ); // set private key for further paylike functions
		$transactionId = vRequest::get('transactionId');

		$cart = VirtueMartCart::getCart( false );
		$cart->prepareCartData();

		$currency = $this->plgVmgetPaymentCurrency( $method->virtuemart_paymentmethod_id, "code" );
		$price = floatval( str_replace( ",", "", $cart->cartPrices['billTotal'] ) );
		$priceInCents = ceil( round( $price, 3 ) * $this->paylikeCurrency->getPaylikeCurrencyMultiplier( $currency ) );

		if ( vRequest::getVar('paymentType') == "captureTransactionFull" ) {
			/* capture payment when order created (instant payment) */
			$session->set( 'transactionId', $transactionId );
			// if ( $method->capture_mode == 'instant' ) {
			// echo $transactionId;
			$data = array(
				'amount'   => round($priceInCents),
				'currency' => $currency
			);
			$response = \Paylike\Transaction::capture( $transactionId, $data );
			// $user = JFactory::getUser();
			// $isroot = $user->authorise('core.admin');
			// if($isroot ) {
				// echo json_encode($response);
				// jexit();
			// } else 
			echo "1";
				//HERE WE NEED TO VERIFY THE $response, to prevent hack ! 
			// }
		} else if ( vRequest::getInt('save',0)) {
			// get transaction to verify some values before save
			$response = \Paylike\Transaction::fetch( $transactionId);
			
			// print("<pre>".print_r($response,true)."</pre>");
			if(!isset($response['transaction']['custom']['paylikeID'])) {
				echo 'ERROR Fetching transaction';
			}
			$paylikeID = $response['transaction']['custom']['paylikeID'];
			$sessionPaylikeID = $session->get( 'paylikeID', '');
			if($paylikeID !== $sessionPaylikeID ) {
				echo 'Bad Transaction !';
			}
			/* save order transactionId after order is created from frontend */
			// We have a confirmation, set the 
			$db = JFactory::getDbo();
			$order_id = vRequest::getInt('virtuemart_order_id',0);
			$data = new stdClass();
			$data->txnid = $transactionId;
			$data->virtuemart_order_id = $order_id;
			// print("<pre>".print_r($response['transaction']['custom']['paylikeID'],true)."</pre>");
			// var_dump($response);
			$db->updateObject('#__virtuemart_payment_plg_paylike', $data, 'virtuemart_order_id');
			$modelOrder = VmModel::getModel ('orders');
			$order['order_status'] = 'C';// confirmed payment need a new setting $this->getNewStatus ($method);
			$order['customer_notified'] = 1;
			$order['comments'] = '';
			$modelOrder->updateStatusForOneOrder ($order_id, $order, TRUE);
			// do we need perhaps to verify amount ?
			jexit();
		} else {
			$paylikeID = uniqid('paylike_');
			$session->set( 'paylikeID', $paylikeID);
			// this data is send to prepare the paylike payment content and datas.
			// SECURE ????
			$lang = JFactory::getLanguage();
			$languages = JLanguageHelper::getLanguages( 'lang_code' );
			$languageCode = $languages[ $lang->getTag() ]->sef; 
			// remove thousand seperators(,)

			$data = new stdClass();
			$data->paylikeID = $paylikeID;
			$data->publicKey = $publicKey;
			$data->captureMode = $method->capture_mode;
			$data->paymentmethod_id = $method->virtuemart_paymentmethod_id;
			$data->locale = $languageCode;
			$data->currency = $currency;
			$data->popupTitle = jText::_($method->popup_title);
			$data->version = $this->version;
			$data->products = array();
			foreach ( $cart->products as $product ) {
				$data->products[] = array(
					"Id" => $product->virtuemart_product_id,
					"Name" => $product->product_name,
					"Qty" => $product->quantity,
				);
			}
			$data->amount = round($priceInCents);
			$billingDetail = $cart->BT;
			$data->customer = new stdClass();
			$data->customer->name = $billingDetail['first_name'] . " " . $billingDetail['last_name'];
			$data->customer->email = $billingDetail['email'];
			$data->customer->phoneNo = $billingDetail['phone_1'];
			$data->customer->IP = $_SERVER["REMOTE_ADDR"];
			$data->platform = array(
				'name' => 'Joomla',
				'version' => $this->getJoomlaVersions()
				);
			$data->ecommerce = array(
				'name' => 'VirtueMart',
				'version' => $this->getVirtuemartVersions()
				);
			/* get API Key and cart */
			echo json_encode($data);
			jexit();
		}

	}

	/* get country name from Id */
	function getCountryName( $virtuemart_country_id ) {
		$db = JFactory::getDBO();
		$q = 'SELECT country_name FROM `#__virtuemart_countries` '
			. 'WHERE `virtuemart_country_id` = ' . $virtuemart_country_id;
		$db->setQuery( $q );
		$country_name = $db->loadResult();

		return $country_name;
	}

	/* get state name from Id */
	function getStateName( $virtuemart_state_id ) {
		$db = JFactory::getDBO();
		$q = 'SELECT state_name FROM `#__virtuemart_states` '
			. 'WHERE `virtuemart_state_id` = ' . $virtuemart_state_id;
		$db->setQuery( $q );
		$state_name = $db->loadResult();

		return $state_name;
	}

	/* get current joomla version */
	function getJoomlaVersions() {
		$db = JFactory::getDBO();
		$q = 'SELECT manifest_cache FROM `#__extensions` '
			. 'WHERE `element` = "joomla" AND `name` = "files_joomla"';
		$db->setQuery( $q );
		$version = $db->loadResult();
		$data = json_decode( $version );

		return JVERSION ? JVERSION : $data->version;
	}

	/* get current Virtuemart version */
	function getVirtuemartVersions() {
		$db = JFactory::getDBO();
		$q = 'SELECT manifest_cache FROM `#__extensions` '
			. 'WHERE `element` = "com_virtuemart"';
		$db->setQuery( $q );
		$version = $db->loadResult();
		$data = json_decode( $version );

		return $data->version;
	}

	/* get order detail from database with order number */
	function getAllOrderDetails( $orderNumber ) {
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `#__virtuemart_payment_plg_paylike` '
			. 'WHERE `order_number` = ' . $db->quote( $orderNumber );
		$db->setQuery( $q );
		$data = $db->loadobject();

		return $data;
	}

	/* add refund half order status in database */
	function addOptionForOrderStatus() {
		$db = JFactory::getDbo();
		$q = 'SELECT * FROM `#__virtuemart_orderstates` '
			. 'WHERE `order_status_code` = "W"';
		$db->setQuery( $q );
		$data = $db->loadobject();
		$q2 = 'SELECT * FROM `#__virtuemart_orderstates` '
			. 'WHERE `order_status_code` = "E"';
		$db->setQuery( $q2 );
		$sentdata = $db->loadobject();
		$q3 = 'SELECT * FROM `#__virtuemart_orderstates` '
			. 'WHERE `order_status_code` = "C"';
		$db->setQuery( $q3 );
		$confirmdata = $db->loadobject();
		// Create a new query object.
		if ( empty( $data ) || empty( $sentdata ) || empty( $confirmdata ) ) {
			$query = $db->getQuery( true );

			// Insert columns.
			$columns = array( 'order_status_code', 'order_status_name' );

			// Insert values.
			$val = array();
			$i = 0;
			if ( empty( $data ) ) {
				$val[ $i ] = array( $db->quote( "W" ), $db->quote( "Refund Half" ) );
				$i ++;
			}
			if ( empty( $confirmdata ) ) {
				$val[ $i ] = array( $db->quote( "C" ), $db->quote( "Confirmed" ) );
				$i ++;
			}
			if ( empty( $sentdata ) ) {
				$val[ $i ] = array( $db->quote( "E" ), $db->quote( "Sent" ) );
				$i ++;
			}

			// Prepare the insert query.
			$query
				->insert( $db->quoteName( '#__virtuemart_orderstates' ) )
				->columns( $db->quoteName( $columns ) );
			foreach ( $val as $key => $v ) {
				$query->values( implode( ",", $val[ $key ] ) );
			}
			// Set the query using our newly populated query object and execute it.
			$db->setQuery( $query );
			$db->execute();
		}
	}

	function getOrderStateName( $state ) {
		$db = JFactory::getDbo();
		$q = 'SELECT * FROM `#__virtuemart_orderstates` '
			. 'WHERE `order_status_code` = "' . $state . '"';
		$db->setQuery( $q );
		$data = $db->loadobject();

		return $data->order_status_name;
	}


}