<?php

include_once( 'Paylike/Client.php' );
if ( ! class_exists( 'vmPSPlugin' ) ) {
	require( JPATH_VM_PLUGINS . DS . 'vmpsplugin.php' );
}

class plgVmPaymentPaylike extends vmPSPlugin {

	// instance of class
	public static $_this = false;

	function __construct( & $subject = false, $config = false ) {

		parent::__construct( $subject, $config );
		$this->id          = array();
		$this->_loggable   = true;
		$this->tableFields = array_keys( $this->getTableSQLFields() );
		$this->_tablepkey  = 'id';
		$this->_tableId    = 'id';
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
			'delay_order_status' => array( '', 'char' ),
		);
		/* save setting params to database */
		$this->setConfigParameterable( $this->_configTableFieldName, $varsToPush );
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
			'status'                      => 'varchar(225)',
			'mode'                        => 'varchar(225)',
			'productinfo'                 => 'text',
			'txnid'                       => 'varchar(29)',
		);

		return $SQLfields;
	}

	/* check if module is enabled or not before showing option in frontend */
	protected function checkConditions( $cart, $method, $cart_prices ) {
		/* check if module is active */
		if ( $method->active == 1 ) {
			if ( ! in_array( $method->virtuemart_paymentmethod_id, $this->id ) ) {
				array_push( $this->id, $method->virtuemart_paymentmethod_id );
				/* if sandcox mode */
				if ( $method->test_mode == 1 ) {
					$apiKey    = $method->test_api_key;
					$publicKey = $method->test_public_key;
					$mode      = "test";
				} /* if live mode */
				else {
					$apiKey    = $method->live_api_key;
					$publicKey = $method->live_public_key;
					$mode      = "live";
				}
				/* load scripts and stylesheets*/
				$document = JFactory::getDocument();
				$document->addScript( 'https://sdk.paylike.io/3.js' );
				$document->addScript( 'https://ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js' );
				$document->addScript( JURI::base() . 'plugins/vmpayment/paylike/paylike.js' );
				$document->addScript( JURI::base() . 'plugins/vmpayment/paylike/bootstrap.min.js' );
				$document->addStyleSheet( JURI::base() . 'plugins/vmpayment/paylike/paylike.css' );
				$document->addStyleSheet( JURI::base() . 'plugins/vmpayment/paylike/bootstrap.min.css' );

				/* load needed params from cart to send in Paylike */
				$this->loadParamsForPaylike( $publicKey, $method );

			}

			return true;
		} else {
			return false;
		}
	}

	function plgVmOnStoreInstallPaymentPluginTable( $jplugin_id ) {
		return $this->onStoreInstallPluginTable( $jplugin_id );
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

	/*Show selected payment method */
	public function plgVmonSelectedCalculatePricePayment( VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name ) {

		return $this->onSelectedCalculatePrice( $cart, $cart_prices, $cart_prices_name );
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
		//echo "<pre>";print_r($card);
		$return      = "<div class='paylike-wrapper' style='display: inline-block'><div class='paylike_title' >" . $plugin->title . "</div>";
		$return      .= "<div class='payment_logo' >";
		$card        = implode( "~", $plugin->card );
		$version     = explode( ".", $this->getJoomlaVersions() );
		$virtVersion = $this->getVirtuemartVersions();
		if ( ( $version[0] >= 3 ) || ( $virtVersion >= 2.7 && $version[0] < 3 ) ) {
			$path = "";
		} else {
			$path = "plugins/vmpayment/paylike/images/";
		}

		if ( ! $card ) {
			$card = 'mastercard,maestro,visa,visaelectron';
		}

		if ( $plugin->mastercard != "" && strpos( $card, "mastercard" ) !== false ) {
			$return .= "<img src='" . JURI::root() . $path . $plugin->mastercard . "' />";
		}
		if ( $plugin->maestro != "" && strpos( $card, "maestro" ) !== false ) {
			$return .= "<img src='" . JURI::root() . $path . $plugin->maestro . "' />";
		}
		if ( $plugin->visa != "" && strpos( $card, "visa" ) !== false ) {
			$return .= "<img src='" . JURI::root() . $path . $plugin->visa . "' />";
		}
		if ( $plugin->visaelectron != "" && strpos( $card, "visaelectron" ) !== false ) {
			$return .= "<img src='" . JURI::root() . $path . $plugin->visaelectron . "' />";
		}
		$return .= "</div></div>";
		$return .= '<div class="paylike_desc" >' . $plugin->description . '</div>';
		if ( $plugin->test_mode == 1 ) {
			$return .= '<div class="payment_box payment_method_paylike"><p>TEST MODE ENABLED. In test mode, you can use the card number 4100 0000 0000 0000 with any CVC and a valid expiration date. "<a href="https://github.com/paylike/sdk">See Documentation</a>".</p></div>';
		}


		return $return;
	}

	function plgVmConfirmedOrder( $cart, $order ) {
		//echo "<pre>";print_r($order);
		$currency_model  = VmModel::getModel( 'currency' );
		$currency_id     = $order["details"]["BT"]->order_currency;
		$displayCurrency = $currency_model->getCurrency( $currency_id );
		$currency        = $displayCurrency->currency_symbol;
		$session         = JFactory::getSession();
		if ( $this->getPaymentDetail( $order["details"]["BT"]->virtuemart_paymentmethod_id )['checkout_mode'] != "after" ) { ?>
			<script type="text/javascript">
                jQuery(window).load(function () {
                    setTimeout(function () {
                        jQuery("body").append('<input type="hidden" name="virtuemart_order_id" value="<?php echo $order["details"]["BT"]->virtuemart_order_id; ?>" /><input type="hidden" name="order_number" value="<?php echo $order["details"]["BT"]->order_number; ?>" />');
                        postData('<?php echo $session->get( 'transactionId' ); ?>');
                    }, 2000);
                });
			</script>			
			<?php
		} else { ?>
			<script type="text/javascript">
                jQuery(window).load(function () {
                    setTimeout(function () {
                        jQuery("body").append('<button type="button" class="orderDetail" class="btn btn-info btn-lg" data-toggle="modal" data-target="#myModal" style="display:none;">Open Modal</button><div class="modal fade" id="myModal" role="dialog"> <div class="modal-dialog" style="text-align: left;"> <div class="modal-content">     <div class="modal-header"> <button type="button" class="close" data-dismiss="modal">&times;</button> <h4 class="modal-title">Pay for Order</h4> </div> <div class="modal-body" style="padding: 10px 30px;"> <p><div class="orderDetail"><ul class="order_details"><li class="order">Order number:<strong><?php echo $order["details"]["BT"]->order_number; ?></strong></li><li class="date">Date:<strong><?php echo date( "M d,Y", strtotime( $order["details"]["BT"]->created_on ) ); ?></strong></li><li class="total">Total:<strong><?php echo $currency . number_format( $order["details"]["BT"]->order_total, 2 ); ?></strong></li><li class="method">Payment method:<strong><?php echo $this->getPaymentDetail( $order["details"]["BT"]->virtuemart_paymentmethod_id )['title']; ?></strong></li><li><a href="javascript:void(0);" id="afterOrderPaylike" >Pay</a></li></ul></div></p> </div> <div class="modal-footer"> <button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div> </div> </div><style>.modal-backdrop.fade.in{ opacity: .4;}#myModal { background: none;margin-left: auto;width: 100%;overflow: hidden;}</style><input type="hidden" name="virtuemart_order_id" value="<?php echo $order["details"]["BT"]->virtuemart_order_id; ?>" /><input type="hidden" name="order_number" value="<?php echo $order["details"]["BT"]->order_number; ?>" /><script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"><\/script><script src="<?php echo JURI::base(); ?>plugins/vmpayment/paylike/bootstrap.min.js"><\/script>');
                        jQuery("#myModal").modal();

                        setTimeout(function () {
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

	/*
		Predefined function of Virtualmart to save selected setting as plugin parameters
	*/
	function plgVmSetOnTablePluginParamsPayment( $name, $id, &$table ) {
		return $this->setOnTablePluginParams( $name, $id, $table );
	}

	function plgVmOnUpdateOrderPayment( $orders, $old_order_status ) {
		$method = $this->getPaymentDetail( $orders->virtuemart_paymentmethod_id ); // get method detail
		/* if testing mode */
		if ( $method['test_mode'] == 1 ) {
			$privateKey = $method['test_api_key'];
			$publicKey  = $method['test_public_key'];
		} else /* if live mode */ {
			$privateKey = $method['live_api_key'];
			$publicKey  = $method['live_public_key'];
		}
		\Paylike\Client::setKey( $privateKey );
		if ( strtolower( $this->getOrderStateName( $orders->order_status ) ) == "completed" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_COMPLETED" || strtolower( $this->getOrderStateName( $orders->order_status ) ) == "shipped" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_SHIPPED" ) {
			$orderDetail = $this->getAllOrderDetails( $orders->order_number );
			$txtid       = $orderDetail->txnid;
			$response    = \Paylike\Transaction::fetch( $txtid );
			$amount      = $response['transaction']['pendingAmount'];
			$currency    = $response['transaction']['currency'];
			$data        = array(
				'amount'   => $amount,
				'currency' => $currency
			);
			if ( $amount > 0 ) {
				$response = \Paylike\Transaction::capture( $txtid, $data );
			}
		} else if ( strtolower( $this->getOrderStateName( $orders->order_status ) ) == "refunded" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_REFUNDED" || strtolower( $this->getOrderStateName( $orders->order_status ) ) == "cancelled" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_CANCELLED" ) {

			$orderDetail = $this->getAllOrderDetails( $orders->order_number );
			$txtid       = $orderDetail->txnid;
			$response    = \Paylike\Transaction::fetch( $txtid );
			/* refund payment if already captured */
			if ( $response['transaction']['capturedAmount'] > 0 ) {
				$amount   = $response['transaction']['capturedAmount'];
				$data     = array(
					'amount'     => $amount,
					'descriptor' => ""
				);
				$response = \Paylike\Transaction::refund( $txtid, $data );
			} else {
				/* void payment if not already captured */
				$data     = array(
					'amount' => $response['transaction']['amount']
				);
				$response = \Paylike\Transaction::void( $txtid, $data );
			}
		} else if ( strtolower( $this->getOrderStateName( $orders->order_status ) ) == "refund half" ) {
			$orderDetail = $this->getAllOrderDetails( $orders->order_number );
			$txtid       = $orderDetail->txnid;
			$response    = \Paylike\Transaction::fetch( $txtid ); // fetch order detail from paylike
			/* refund payment if already captured */
			if ( $response['transaction']['capturedAmount'] > 0 && $response['transaction']['refundedAmount'] <= 0 ) {
				$amount   = $response['transaction']['capturedAmount'];
				$data     = array(
					'amount'     => round( $amount / 2 ),
					'descriptor' => ""
				);
				$response = \Paylike\Transaction::refund( $txtid, $data );
			} else {
				/* void payment if not already captured */
				$data     = array(
					'amount' => $response['transaction']['amount']
				);
				$response = \Paylike\Transaction::void( $txtid, $data );
			}
		}
	}

	/*
		Predefined function of Virtualmart to declare all settings params in payment variable
	*/
	function plgVmDeclarePluginParamsPaymentVM3( &$data ) {
		return $this->declarePluginParams( 'payment', $data );
	}
    
    /*   Function to get the shop curreny*/
	/*function plgVmgetShopCurrency($virtuemart_paymentmethod_id, $type = false)
	{
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
		$currency_model = VmModel::getModel('currency');
		$displayCurrency = $currency_model->getCurrency( $paymentCurrencyId );
		if($type == "code")
		{
			return $currency =  $displayCurrency->currency_code_3;
		}
		else {
			return $currency =  $displayCurrency->currency_symbol;
		}
	}*/
		
	/*   Function to change the price based on select currency*/		
	/*function plgVmgetPriceExchanger($currentCurreny,$shopCurrency,$price)
	{
		if(!class_exists('convertECB')) 
			require 'convertECB.php'; 
			$this->_currencyConverter = new convertECB();
			$price = round($this ->_currencyConverter->convert($price,$shopCurrency,$currentCurreny),3);
			return $price;
	}*/	
      
    
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, $type = false)
	{
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);	
		$paymentCurrencyId = $method->payment_currency;
		$currency_model = VmModel::getModel('currency');
		$displayCurrency = $currency_model->getCurrency($paymentCurrencyId);
		if($type == "code")
		{
			return $currency =  $displayCurrency->currency_code_3;
		}
		else {
			return $currency =  $displayCurrency->currency_symbol;
		}
	}

	/*static function getPaymentCurrency (&$method, $selectedUserCurrency = false) {

		if (empty($method->payment_currency)) {
			$vendor_model = VmModel::getModel('vendor');
			$vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
			$method->payment_currency = $vendor->vendor_currency;
			return $method->payment_currency;
		} else {

			$vendor_model = VmModel::getModel( 'vendor' );
			$vendor_currencies = $vendor_model->getVendorAndAcceptedCurrencies( $method->virtuemart_vendor_id );

			if(!$selectedUserCurrency) {
				if($method->payment_currency == -1) {
					$mainframe = JFactory::getApplication();
					$selectedUserCurrency = $mainframe->getUserStateFromRequest( "virtuemart_currency_id", 'virtuemart_currency_id', vRequest::getInt( 'virtuemart_currency_id', $vendor_currencies['vendor_currency'] ) );
				} else {
					$selectedUserCurrency = $method->payment_currency;
				}
			}

			$vendor_currencies['all_currencies'] = explode(',', $vendor_currencies['all_currencies']);
			if(in_array($selectedUserCurrency,$vendor_currencies['all_currencies'])){
				$method->payment_currency = $selectedUserCurrency;
			} else {
				$method->payment_currency = $vendor_currencies['vendor_currency'];
			}

			return $method->payment_currency;
		}

	}*/


	/* load hidden field to send data on paylike */
	function loadParamsForPaylike( $publicKey, $method ) {
		/* get billing detail from cart */
		$billingDetail = VirtueMartCart::getCart(false)->BT;
		$price = number_format(VirtueMartCart::getCart(false)->cartPrices['withTax'],2);		
		$currency =  $this->plgVmgetPaymentCurrency($method->virtuemart_paymentmethod_id,"code"); //Currency code from currency id
		
		$priceInCents= ceil(round($price, 3) * $this->getPaylikeCurrencyMultiplier($currency));
		$address = $this->loadAddress();
		foreach (VirtueMartCart::getCart(false)->products as $product) {
			echo "<input type='hidden' value='".$product->product_name."' name='paylikeProductName[]' />";
			echo "<input type='hidden' value='".$product->virtuemart_product_id."' name='paylikeProductId[]' />";
			echo "<input type='hidden' value='".$product->quantity."' name='paylikeQuantity[]' />";
			if($priceInCents == 0)
			{
				$price += $product->prices['salesPrice']*$product->quantity;
			}
		}
		if(VirtueMartCart::getCart(false)->cartPrices['salesPriceShipment']){
			$price += VirtueMartCart::getCart(false)->cartPrices['salesPriceShipment'];
			
		}
		// remove thousand seperators(,)
		$price = floatval(str_replace(",","",$price));
		
		//echo $price = vmPSPlugin::getAmountValueInCurrency($price, $method->payment_currency);
		//$cd = CurrencyDisplay::getInstance($this->cart->pricesCurrency);

		$priceInCents= ceil(round($price, 3) * $this->getPaylikeCurrencyMultiplier($currency));
		/* load hidden field having user and product data */
		echo "<input type='hidden' value='".round($priceInCents)."' name='paylikePrice' />";
		echo "<input type='hidden' value='".$currency."' name='paylikeCurrency' />";
		echo "<input type='hidden' value='".$method->checkout_mode."' name='paylikeMode' />";
		echo "<input type='hidden' value='".$method->capture_mode."' name='paylikeCaptureMode' />";
		echo "<input type='hidden' value='".dirname(__FILE__)."' name='paylikePath' />";
		echo "<input type='hidden' value='".$method->virtuemart_paymentmethod_id."' name='paylikePaymentMethod' />";
		echo "<input type='hidden' value='".JURI::base()."' name='paylikeBase' />";
		if(isset($billingDetail['first_name']))
		{
			$lang = JFactory::getLanguage();
			$languages = JLanguageHelper::getLanguages('lang_code');
			$languageCode = $languages[ $lang->getTag() ]->sef;
			echo "<input type='hidden' value='".$billingDetail['first_name']." ".$billingDetail['last_name']."' name='paylikeName' />";
			echo "<input type='hidden' value='".$billingDetail['email']."' name='paylikeEmail' />";
			echo "<input type='hidden' value='".$billingDetail['phone_1']."' name='paylikePhone' />";
			echo "<input type='hidden' value='".$address."' name='paylikeAddress' />";
			echo "<input type='hidden' value='".$_SERVER["REMOTE_ADDR"]."' name='paylikeIp' />";
			echo "<input type='hidden' value='Joomla' name='paylikePlatformName' />";
			echo "<input type='hidden' value='".$this->getJoomlaVersions()."' name='paylikePlatformVersion' />";
			echo "<input type='hidden' value='VirtueMart' name='paylikeEcommerce' />";
			echo "<input type='hidden' value='".$this->getVirtuemartVersions()."' name='paylikeEcommerceVersion' />";			
			echo "<input type='hidden' value='".$languageCode."' name='paylikeLocale' />";
			echo "<input type='hidden' value='".$method->popup_title."' name='paylikePopupTitle' />";
			echo "<input type='hidden' value='".$method->title."' name='paylikeTitle' />";
			echo "<form action='' id='capturePayment' method='post' ><input type='hidden' value='".round($priceInCents)."' name='paymentprice' /><input name='paymentType' type= 'hidden' value='captureTransactionFull'/><input type='hidden' value='".$currency."' name='paymentcurrency' /></form>";
		}

		return true;
	}

	/* load address of user */
	function loadAddress() {
		$billingDetail = VirtueMartCart::getCart( false )->BT;
		$address       = "";
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

		$session = JFactory::getSession();
		$method  = $this->getPaymentDetail( $_REQUEST['virtuemart_paymentmethod_id'] ); // get method detail

		/* if testing mode */
		if ( $method['test_mode'] == 1 ) {
			$privateKey = $method['test_api_key'];
			$publicKey  = $method['test_public_key'];
		} else /* if live mode */ {
			$privateKey = $method['live_api_key'];
			$publicKey  = $method['live_public_key'];
		}
		\Paylike\Client::setKey( $privateKey ); // set private key for further paylike functions

		if ( isset( $_REQUEST['paymentType'] ) && $_REQUEST['paymentType'] == "captureTransactionFull" ) {
			/* capture payment when order created (instant payment) */
			$session->set( 'transactionId', $_REQUEST['transactionId'] );
			if ( $_REQUEST['paylikeCaptureMode'] == 'instant' ) {
				$amount        = $_REQUEST['paymentprice'];
				$currency      = $_REQUEST['paymentcurrency'];
				$data          = array(
					'amount'   => $amount,
					'currency' => $currency
				);
				$transactionId = $_REQUEST['transactionId'];
				$response      = \Paylike\Transaction::capture( $transactionId, $data );
			}
			echo "1";
		} else if ( isset( $_REQUEST['save'] ) ) {
			/* save order detail after order is created from frontend */
			$db = JFactory::getDbo();
			// Create a new query object.
			$query = $db->getQuery( true );

			// Insert columns.
			$columns = array(
				'virtuemart_order_id',
				'order_number',
				'virtuemart_paymentmethod_id',
				'amount',
				'txnid',
				'payment_name'
			);

			// Insert values.
			$values = array(
				$_REQUEST['virtuemart_order_id'],
				$db->quote( $_REQUEST['order_number'] ),
				$_REQUEST['virtuemart_paymentmethod_id'],
				$db->quote( $_REQUEST['pAmount'] / 100 ),
				$db->quote( $_REQUEST['transactionId'] ),
				$db->quote( $_REQUEST['paylikeTitle'] )
			);

			// Prepare the insert query.
			$query
				->insert( $db->quoteName( '#__virtuemart_payment_plg_paylike' ) )
				->columns( $db->quoteName( $columns ) )
				->values( implode( ',', $values ) );

			// Set the query using our newly populated query object and execute it.
			$db->setQuery( $query );
			$db->execute();
		} else {
			/* get API Key */
			echo $publicKey;
		}

	}

	/* get country name from Id */
	function getCountryName( $virtuemart_country_id ) {
		$db = JFactory::getDBO();
		$q  = 'SELECT country_name FROM `#__virtuemart_countries` '
		      . 'WHERE `virtuemart_country_id` = ' . $virtuemart_country_id;
		$db->setQuery( $q );
		$country_name = $db->loadResult();

		return $country_name;
	}

	/* get state name from Id */
	function getStateName( $virtuemart_state_id ) {
		$db = JFactory::getDBO();
		$q  = 'SELECT state_name FROM `#__virtuemart_states` '
		      . 'WHERE `virtuemart_state_id` = ' . $virtuemart_state_id;
		$db->setQuery( $q );
		$state_name = $db->loadResult();

		return $state_name;
	}

	/* get current joomla version */
	function getJoomlaVersions() {
		$db = JFactory::getDBO();
		$q  = 'SELECT manifest_cache FROM `#__extensions` '
		      . 'WHERE `element` = "joomla" AND `name` = "files_joomla"';
		$db->setQuery( $q );
		$version = $db->loadResult();
		$data    = json_decode( $version );

		return JVERSION ? JVERSION : $data->version;
	}

	/* get current Virtuemart version */
	function getVirtuemartVersions() {
		$db = JFactory::getDBO();
		$q  = 'SELECT manifest_cache FROM `#__extensions` '
		      . 'WHERE `element` = "com_virtuemart"';
		$db->setQuery( $q );
		$version = $db->loadResult();
		$data    = json_decode( $version );

		return $data->version;
	}

	/* get Payment Method Detail for database */
	function getPaymentDetail( $virtuemart_paymentmethod_id ) {
		$db = JFactory::getDBO();
		$q  = 'SELECT payment_params FROM `#__virtuemart_paymentmethods` '
		      . 'WHERE `virtuemart_paymentmethod_id` = ' . $virtuemart_paymentmethod_id;
		$db->setQuery( $q );
		$data         = $db->loadresult();
		$explodedData = explode( "|", $data );
		$finalData    = [];
		foreach ( $explodedData as $exp ) {
			$val = explode( "=", $exp );
			if ( ! empty( $val[0] ) ) {
				$finalData[ $val[0] ] = str_replace( '"', "", $val[1] );
			}
		}

		return $finalData;
	}

	/* get txnid from database with order number */
	function getOrderDetails( $orderNumber ) {
		$db = JFactory::getDBO();
		$q  = 'SELECT txnid FROM `#__virtuemart_payment_plg_paylike` '
		      . 'WHERE `order_number` = ' . $db->quote( $orderNumber );
		$db->setQuery( $q );
		$txnid = $db->loadresult();

		return $txnid;
	}

	/* get order detail from database with order number */
	function getAllOrderDetails( $orderNumber ) {
		$db = JFactory::getDBO();
		$q  = 'SELECT * FROM `#__virtuemart_payment_plg_paylike` '
		      . 'WHERE `order_number` = ' . $db->quote( $orderNumber );
		$db->setQuery( $q );
		$data = $db->loadobject();

		return $data;
	}

	/* add refund half order status in database */
	function addOptionForOrderStatus() {
		$db = JFactory::getDbo();
		$q  = 'SELECT * FROM `#__virtuemart_orderstates` '
		      . 'WHERE `order_status_code` = "W"';
		$db->setQuery( $q );
		$data = $db->loadobject();
		$q2   = 'SELECT * FROM `#__virtuemart_orderstates` '
		        . 'WHERE `order_status_code` = "E"';
		$db->setQuery( $q2 );
		$sentdata = $db->loadobject();
		$q3       = 'SELECT * FROM `#__virtuemart_orderstates` '
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
			$i   = 0;
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
		$q  = 'SELECT * FROM `#__virtuemart_orderstates` '
		      . 'WHERE `order_status_code` = "' . $state . '"';
		$db->setQuery( $q );
		$data = $db->loadobject();

		return $data->order_status_name;
	}

	/**
	 * Return the number that should be used to compute cents from the total amount
	 *
	 * @param $currency_iso_code
	 *
	 * @return int|number
	 */
	public function getPaylikeCurrencyMultiplier( $currency_iso_code ) {
		$currency = $this->getPaylikeCurrency( $currency_iso_code );
		if ( isset( $currency['exponent'] ) ) {
			return pow( 10, $currency['exponent'] );
		} else {
			return pow( 10, 2 );
		}
	}

	public function getPaylikeCurrency( $currency_iso_code ) {
		$currencies = array(
			'AED' =>
				array(
					'code'     => 'AED',
					'currency' => 'United Arab Emirates dirham',
					'numeric'  => '784',
					'exponent' => 2,
				),
			'AFN' =>
				array(
					'code'     => 'AFN',
					'currency' => 'Afghan afghani',
					'numeric'  => '971',
					'exponent' => 2,
				),
			'ALL' =>
				array(
					'code'     => 'ALL',
					'currency' => 'Albanian lek',
					'numeric'  => '008',
					'exponent' => 2,
				),
			'AMD' =>
				array(
					'code'     => 'AMD',
					'currency' => 'Armenian dram',
					'numeric'  => '051',
					'exponent' => 2,
				),
			'ANG' =>
				array(
					'code'     => 'ANG',
					'currency' => 'Netherlands Antillean guilder',
					'numeric'  => '532',
					'exponent' => 2,
				),
			'AOA' =>
				array(
					'code'     => 'AOA',
					'currency' => 'Angolan kwanza',
					'numeric'  => '973',
					'exponent' => 2,
				),
			'ARS' =>
				array(
					'code'     => 'ARS',
					'currency' => 'Argentine peso',
					'numeric'  => '032',
					'exponent' => 2,
				),
			'AUD' =>
				array(
					'code'     => 'AUD',
					'currency' => 'Australian dollar',
					'numeric'  => '036',
					'exponent' => 2,
				),
			'AWG' =>
				array(
					'code'     => 'AWG',
					'currency' => 'Aruban florin',
					'numeric'  => '533',
					'exponent' => 2,
				),
			'AZN' =>
				array(
					'code'     => 'AZN',
					'currency' => 'Azerbaijani manat',
					'numeric'  => '944',
					'exponent' => 2,
				),
			'BAM' =>
				array(
					'code'     => 'BAM',
					'currency' => 'Bosnia and Herzegovina convertible mark',
					'numeric'  => '977',
					'exponent' => 2,
				),
			'BBD' =>
				array(
					'code'     => 'BBD',
					'currency' => 'Barbados dollar',
					'numeric'  => '052',
					'exponent' => 2,
				),
			'BDT' =>
				array(
					'code'     => 'BDT',
					'currency' => 'Bangladeshi taka',
					'numeric'  => '050',
					'exponent' => 2,
				),
			'BGN' =>
				array(
					'code'     => 'BGN',
					'currency' => 'Bulgarian lev',
					'numeric'  => '975',
					'exponent' => 2,
				),
			'BHD' =>
				array(
					'code'     => 'BHD',
					'currency' => 'Bahraini dinar',
					'numeric'  => '048',
					'exponent' => 3,
				),
			'BIF' =>
				array(
					'code'     => 'BIF',
					'currency' => 'Burundian franc',
					'numeric'  => '108',
					'exponent' => 0,
				),
			'BMD' =>
				array(
					'code'     => 'BMD',
					'currency' => 'Bermudian dollar',
					'numeric'  => '060',
					'exponent' => 2,
				),
			'BND' =>
				array(
					'code'     => 'BND',
					'currency' => 'Brunei dollar',
					'numeric'  => '096',
					'exponent' => 2,
				),
			'BOB' =>
				array(
					'code'     => 'BOB',
					'currency' => 'Boliviano',
					'numeric'  => '068',
					'exponent' => 2,
				),
			'BRL' =>
				array(
					'code'     => 'BRL',
					'currency' => 'Brazilian real',
					'numeric'  => '986',
					'exponent' => 2,
				),
			'BSD' =>
				array(
					'code'     => 'BSD',
					'currency' => 'Bahamian dollar',
					'numeric'  => '044',
					'exponent' => 2,
				),
			'BTN' =>
				array(
					'code'     => 'BTN',
					'currency' => 'Bhutanese ngultrum',
					'numeric'  => '064',
					'exponent' => 2,
				),
			'BWP' =>
				array(
					'code'     => 'BWP',
					'currency' => 'Botswana pula',
					'numeric'  => '072',
					'exponent' => 2,
				),
			'BYR' =>
				array(
					'code'     => 'BYR',
					'currency' => 'Belarusian ruble',
					'numeric'  => '974',
					'exponent' => 0,
				),
			'BZD' =>
				array(
					'code'     => 'BZD',
					'currency' => 'Belize dollar',
					'numeric'  => '084',
					'exponent' => 2,
				),
			'CAD' =>
				array(
					'code'     => 'CAD',
					'currency' => 'Canadian dollar',
					'numeric'  => '124',
					'exponent' => 2,
				),
			'CDF' =>
				array(
					'code'     => 'CDF',
					'currency' => 'Congolese franc',
					'numeric'  => '976',
					'exponent' => 2,
				),
			'CHF' =>
				array(
					'code'     => 'CHF',
					'currency' => 'Swiss franc',
					'numeric'  => '756',
					'funding'  => true,
					'exponent' => 2,
				),
			'CLP' =>
				array(
					'code'     => 'CLP',
					'currency' => 'Chilean peso',
					'numeric'  => '152',
					'exponent' => 0,
				),
			'CNY' =>
				array(
					'code'     => 'CNY',
					'currency' => 'Chinese yuan',
					'numeric'  => '156',
					'exponent' => 2,
				),
			'COP' =>
				array(
					'code'     => 'COP',
					'currency' => 'Colombian peso',
					'numeric'  => '170',
					'exponent' => 2,
				),
			'CRC' =>
				array(
					'code'     => 'CRC',
					'currency' => 'Costa Rican colon',
					'numeric'  => '188',
					'exponent' => 2,
				),
			'CUP' =>
				array(
					'code'     => 'CUP',
					'currency' => 'Cuban peso',
					'numeric'  => '192',
					'exponent' => 2,
				),
			'CVE' =>
				array(
					'code'     => 'CVE',
					'currency' => 'Cape Verde escudo',
					'numeric'  => '132',
					'exponent' => 2,
				),
			'CZK' =>
				array(
					'code'     => 'CZK',
					'currency' => 'Czech koruna',
					'numeric'  => '203',
					'exponent' => 2,
				),
			'DJF' =>
				array(
					'code'     => 'DJF',
					'currency' => 'Djiboutian franc',
					'numeric'  => '262',
					'exponent' => 0,
				),
			'DKK' =>
				array(
					'code'     => 'DKK',
					'currency' => 'Danish krone',
					'numeric'  => '208',
					'funding'  => true,
					'exponent' => 2,
				),
			'DOP' =>
				array(
					'code'     => 'DOP',
					'currency' => 'Dominican peso',
					'numeric'  => '214',
					'exponent' => 2,
				),
			'DZD' =>
				array(
					'code'     => 'DZD',
					'currency' => 'Algerian dinar',
					'numeric'  => '012',
					'exponent' => 2,
				),
			'EGP' =>
				array(
					'code'     => 'EGP',
					'currency' => 'Egyptian pound',
					'numeric'  => '818',
					'exponent' => 2,
				),
			'ERN' =>
				array(
					'code'     => 'ERN',
					'currency' => 'Eritrean nakfa',
					'numeric'  => '232',
					'exponent' => 2,
				),
			'ETB' =>
				array(
					'code'     => 'ETB',
					'currency' => 'Ethiopian birr',
					'numeric'  => '230',
					'exponent' => 2,
				),
			'EUR' =>
				array(
					'code'     => 'EUR',
					'currency' => 'Euro',
					'numeric'  => '978',
					'funding'  => true,
					'exponent' => 2,
				),
			'FJD' =>
				array(
					'code'     => 'FJD',
					'currency' => 'Fiji dollar',
					'numeric'  => '242',
					'exponent' => 2,
				),
			'FKP' =>
				array(
					'code'     => 'FKP',
					'currency' => 'Falkland Islands pound',
					'numeric'  => '238',
					'exponent' => 2,
				),
			'GBP' =>
				array(
					'code'     => 'GBP',
					'currency' => 'Pound sterling',
					'numeric'  => '826',
					'funding'  => true,
					'exponent' => 2,
				),
			'GEL' =>
				array(
					'code'     => 'GEL',
					'currency' => 'Georgian lari',
					'numeric'  => '981',
					'exponent' => 2,
				),
			'GHS' =>
				array(
					'code'     => 'GHS',
					'currency' => 'Ghanaian cedi',
					'numeric'  => '936',
					'exponent' => 2,
				),
			'GIP' =>
				array(
					'code'     => 'GIP',
					'currency' => 'Gibraltar pound',
					'numeric'  => '292',
					'exponent' => 2,
				),
			'GMD' =>
				array(
					'code'     => 'GMD',
					'currency' => 'Gambian dalasi',
					'numeric'  => '270',
					'exponent' => 2,
				),
			'GNF' =>
				array(
					'code'     => 'GNF',
					'currency' => 'Guinean franc',
					'numeric'  => '324',
					'exponent' => 0,
				),
			'GTQ' =>
				array(
					'code'     => 'GTQ',
					'currency' => 'Guatemalan quetzal',
					'numeric'  => '320',
					'exponent' => 2,
				),
			'GYD' =>
				array(
					'code'     => 'GYD',
					'currency' => 'Guyanese dollar',
					'numeric'  => '328',
					'exponent' => 2,
				),
			'HKD' =>
				array(
					'code'     => 'HKD',
					'currency' => 'Hong Kong dollar',
					'numeric'  => '344',
					'exponent' => 2,
				),
			'HNL' =>
				array(
					'code'     => 'HNL',
					'currency' => 'Honduran lempira',
					'numeric'  => '340',
					'exponent' => 2,
				),
			'HRK' =>
				array(
					'code'     => 'HRK',
					'currency' => 'Croatian kuna',
					'numeric'  => '191',
					'exponent' => 2,
				),
			'HTG' =>
				array(
					'code'     => 'HTG',
					'currency' => 'Haitian gourde',
					'numeric'  => '332',
					'exponent' => 2,
				),
			'HUF' =>
				array(
					'code'     => 'HUF',
					'currency' => 'Hungarian forint',
					'numeric'  => '348',
					'funding'  => true,
					'exponent' => 2,
				),
			'IDR' =>
				array(
					'code'     => 'IDR',
					'currency' => 'Indonesian rupiah',
					'numeric'  => '360',
					'exponent' => 2,
				),
			'ILS' =>
				array(
					'code'     => 'ILS',
					'currency' => 'Israeli new shekel',
					'numeric'  => '376',
					'exponent' => 2,
				),
			'INR' =>
				array(
					'code'     => 'INR',
					'currency' => 'Indian rupee',
					'numeric'  => '356',
					'exponent' => 2,
				),
			'IQD' =>
				array(
					'code'     => 'IQD',
					'currency' => 'Iraqi dinar',
					'numeric'  => '368',
					'exponent' => 3,
				),
			'IRR' =>
				array(
					'code'     => 'IRR',
					'currency' => 'Iranian rial',
					'numeric'  => '364',
					'exponent' => 2,
				),
			'ISK' =>
				array(
					'code'     => 'ISK',
					'currency' => 'Icelandic króna',
					'numeric'  => '352',
					'exponent' => 2,
				),
			'JMD' =>
				array(
					'code'     => 'JMD',
					'currency' => 'Jamaican dollar',
					'numeric'  => '388',
					'exponent' => 2,
				),
			'JOD' =>
				array(
					'code'     => 'JOD',
					'currency' => 'Jordanian dinar',
					'numeric'  => '400',
					'exponent' => 3,
				),
			'JPY' =>
				array(
					'code'     => 'JPY',
					'currency' => 'Japanese yen',
					'numeric'  => '392',
					'exponent' => 0,
				),
			'KES' =>
				array(
					'code'     => 'KES',
					'currency' => 'Kenyan shilling',
					'numeric'  => '404',
					'exponent' => 2,
				),
			'KGS' =>
				array(
					'code'     => 'KGS',
					'currency' => 'Kyrgyzstani som',
					'numeric'  => '417',
					'exponent' => 2,
				),
			'KHR' =>
				array(
					'code'     => 'KHR',
					'currency' => 'Cambodian riel',
					'numeric'  => '116',
					'exponent' => 2,
				),
			'KMF' =>
				array(
					'code'     => 'KMF',
					'currency' => 'Comoro franc',
					'numeric'  => '174',
					'exponent' => 0,
				),
			'KPW' =>
				array(
					'code'     => 'KPW',
					'currency' => 'North Korean won',
					'numeric'  => '408',
					'exponent' => 2,
				),
			'KRW' =>
				array(
					'code'     => 'KRW',
					'currency' => 'South Korean won',
					'numeric'  => '410',
					'exponent' => 0,
				),
			'KWD' =>
				array(
					'code'     => 'KWD',
					'currency' => 'Kuwaiti dinar',
					'numeric'  => '414',
					'exponent' => 3,
				),
			'KYD' =>
				array(
					'code'     => 'KYD',
					'currency' => 'Cayman Islands dollar',
					'numeric'  => '136',
					'exponent' => 2,
				),
			'KZT' =>
				array(
					'code'     => 'KZT',
					'currency' => 'Kazakhstani tenge',
					'numeric'  => '398',
					'exponent' => 2,
				),
			'LAK' =>
				array(
					'code'     => 'LAK',
					'currency' => 'Lao kip',
					'numeric'  => '418',
					'exponent' => 2,
				),
			'LBP' =>
				array(
					'code'     => 'LBP',
					'currency' => 'Lebanese pound',
					'numeric'  => '422',
					'exponent' => 2,
				),
			'LKR' =>
				array(
					'code'     => 'LKR',
					'currency' => 'Sri Lankan rupee',
					'numeric'  => '144',
					'exponent' => 2,
				),
			'LRD' =>
				array(
					'code'     => 'LRD',
					'currency' => 'Liberian dollar',
					'numeric'  => '430',
					'exponent' => 2,
				),
			'LSL' =>
				array(
					'code'     => 'LSL',
					'currency' => 'Lesotho loti',
					'numeric'  => '426',
					'exponent' => 2,
				),
			'MAD' =>
				array(
					'code'     => 'MAD',
					'currency' => 'Moroccan dirham',
					'numeric'  => '504',
					'exponent' => 2,
				),
			'MDL' =>
				array(
					'code'     => 'MDL',
					'currency' => 'Moldovan leu',
					'numeric'  => '498',
					'exponent' => 2,
				),
			'MGA' =>
				array(
					'code'     => 'MGA',
					'currency' => 'Malagasy ariary',
					'numeric'  => '969',
					'exponent' => 2,
				),
			'MKD' =>
				array(
					'code'     => 'MKD',
					'currency' => 'Macedonian denar',
					'numeric'  => '807',
					'exponent' => 2,
				),
			'MMK' =>
				array(
					'code'     => 'MMK',
					'currency' => 'Myanmar kyat',
					'numeric'  => '104',
					'exponent' => 2,
				),
			'MNT' =>
				array(
					'code'     => 'MNT',
					'currency' => 'Mongolian tögrög',
					'numeric'  => '496',
					'exponent' => 2,
				),
			'MOP' =>
				array(
					'code'     => 'MOP',
					'currency' => 'Macanese pataca',
					'numeric'  => '446',
					'exponent' => 2,
				),
			'MRO' =>
				array(
					'code'     => 'MRO',
					'currency' => 'Mauritanian ouguiya',
					'numeric'  => '478',
					'exponent' => 2,
				),
			'MUR' =>
				array(
					'code'     => 'MUR',
					'currency' => 'Mauritian rupee',
					'numeric'  => '480',
					'exponent' => 2,
				),
			'MVR' =>
				array(
					'code'     => 'MVR',
					'currency' => 'Maldivian rufiyaa',
					'numeric'  => '462',
					'exponent' => 2,
				),
			'MWK' =>
				array(
					'code'     => 'MWK',
					'currency' => 'Malawian kwacha',
					'numeric'  => '454',
					'exponent' => 2,
				),
			'MXN' =>
				array(
					'code'     => 'MXN',
					'currency' => 'Mexican peso',
					'numeric'  => '484',
					'exponent' => 2,
				),
			'MYR' =>
				array(
					'code'     => 'MYR',
					'currency' => 'Malaysian ringgit',
					'numeric'  => '458',
					'exponent' => 2,
				),
			'MZN' =>
				array(
					'code'     => 'MZN',
					'currency' => 'Mozambican metical',
					'numeric'  => '943',
					'exponent' => 2,
				),
			'NAD' =>
				array(
					'code'     => 'NAD',
					'currency' => 'Namibian dollar',
					'numeric'  => '516',
					'exponent' => 2,
				),
			'NGN' =>
				array(
					'code'     => 'NGN',
					'currency' => 'Nigerian naira',
					'numeric'  => '566',
					'exponent' => 2,
				),
			'NIO' =>
				array(
					'code'     => 'NIO',
					'currency' => 'Nicaraguan córdoba',
					'numeric'  => '558',
					'exponent' => 2,
				),
			'NOK' =>
				array(
					'code'     => 'NOK',
					'currency' => 'Norwegian krone',
					'numeric'  => '578',
					'funding'  => true,
					'exponent' => 2,
				),
			'NPR' =>
				array(
					'code'     => 'NPR',
					'currency' => 'Nepalese rupee',
					'numeric'  => '524',
					'exponent' => 2,
				),
			'NZD' =>
				array(
					'code'     => 'NZD',
					'currency' => 'New Zealand dollar',
					'numeric'  => '554',
					'exponent' => 2,
				),
			'OMR' =>
				array(
					'code'     => 'OMR',
					'currency' => 'Omani rial',
					'numeric'  => '512',
					'exponent' => 3,
				),
			'PAB' =>
				array(
					'code'     => 'PAB',
					'currency' => 'Panamanian balboa',
					'numeric'  => '590',
					'exponent' => 2,
				),
			'PEN' =>
				array(
					'code'     => 'PEN',
					'currency' => 'Peruvian Sol',
					'numeric'  => '604',
					'exponent' => 2,
				),
			'PGK' =>
				array(
					'code'     => 'PGK',
					'currency' => 'Papua New Guinean kina',
					'numeric'  => '598',
					'exponent' => 2,
				),
			'PHP' =>
				array(
					'code'     => 'PHP',
					'currency' => 'Philippine peso',
					'numeric'  => '608',
					'exponent' => 2,
				),
			'PKR' =>
				array(
					'code'     => 'PKR',
					'currency' => 'Pakistani rupee',
					'numeric'  => '586',
					'exponent' => 2,
				),
			'PLN' =>
				array(
					'code'     => 'PLN',
					'currency' => 'Polish złoty',
					'numeric'  => '985',
					'funding'  => true,
					'exponent' => 2,
				),
			'PYG' =>
				array(
					'code'     => 'PYG',
					'currency' => 'Paraguayan guaraní',
					'numeric'  => '600',
					'exponent' => 0,
				),
			'QAR' =>
				array(
					'code'     => 'QAR',
					'currency' => 'Qatari riyal',
					'numeric'  => '634',
					'exponent' => 2,
				),
			'RON' =>
				array(
					'code'     => 'RON',
					'currency' => 'Romanian leu',
					'numeric'  => '946',
					'funding'  => true,
					'exponent' => 2,
				),
			'RSD' =>
				array(
					'code'     => 'RSD',
					'currency' => 'Serbian dinar',
					'numeric'  => '941',
					'exponent' => 2,
				),
			'RUB' =>
				array(
					'code'     => 'RUB',
					'currency' => 'Russian ruble',
					'numeric'  => '643',
					'exponent' => 2,
				),
			'RWF' =>
				array(
					'code'     => 'RWF',
					'currency' => 'Rwandan franc',
					'numeric'  => '646',
					'exponent' => 0,
				),
			'SAR' =>
				array(
					'code'     => 'SAR',
					'currency' => 'Saudi riyal',
					'numeric'  => '682',
					'exponent' => 2,
				),
			'SBD' =>
				array(
					'code'     => 'SBD',
					'currency' => 'Solomon Islands dollar',
					'numeric'  => '090',
					'exponent' => 2,
				),
			'SCR' =>
				array(
					'code'     => 'SCR',
					'currency' => 'Seychelles rupee',
					'numeric'  => '690',
					'exponent' => 2,
				),
			'SDG' =>
				array(
					'code'     => 'SDG',
					'currency' => 'Sudanese pound',
					'numeric'  => '938',
					'exponent' => 2,
				),
			'SEK' =>
				array(
					'code'     => 'SEK',
					'currency' => 'Swedish krona',
					'numeric'  => '752',
					'funding'  => true,
					'exponent' => 2,
				),
			'SGD' =>
				array(
					'code'     => 'SGD',
					'currency' => 'Singapore dollar',
					'numeric'  => '702',
					'exponent' => 2,
				),
			'SHP' =>
				array(
					'code'     => 'SHP',
					'currency' => 'Saint Helena pound',
					'numeric'  => '654',
					'exponent' => 2,
				),
			'SLL' =>
				array(
					'code'     => 'SLL',
					'currency' => 'Sierra Leonean leone',
					'numeric'  => '694',
					'exponent' => 2,
				),
			'SOS' =>
				array(
					'code'     => 'SOS',
					'currency' => 'Somali shilling',
					'numeric'  => '706',
					'exponent' => 2,
				),
			'SRD' =>
				array(
					'code'     => 'SRD',
					'currency' => 'Surinamese dollar',
					'numeric'  => '968',
					'exponent' => 2,
				),
			'STD' =>
				array(
					'code'     => 'STD',
					'currency' => 'São Tomé and Príncipe dobra',
					'numeric'  => '678',
					'exponent' => 2,
				),
			'SYP' =>
				array(
					'code'     => 'SYP',
					'currency' => 'Syrian pound',
					'numeric'  => '760',
					'exponent' => 2,
				),
			'SZL' =>
				array(
					'code'     => 'SZL',
					'currency' => 'Swazi lilangeni',
					'numeric'  => '748',
					'exponent' => 2,
				),
			'THB' =>
				array(
					'code'     => 'THB',
					'currency' => 'Thai baht',
					'numeric'  => '764',
					'exponent' => 2,
				),
			'TJS' =>
				array(
					'code'     => 'TJS',
					'currency' => 'Tajikistani somoni',
					'numeric'  => '972',
					'exponent' => 2,
				),
			'TMT' =>
				array(
					'code'     => 'TMT',
					'currency' => 'Turkmenistani manat',
					'numeric'  => '934',
					'exponent' => 2,
				),
			'TND' =>
				array(
					'code'     => 'TND',
					'currency' => 'Tunisian dinar',
					'numeric'  => '788',
					'exponent' => 3,
				),
			'TOP' =>
				array(
					'code'     => 'TOP',
					'currency' => 'Tongan paʻanga',
					'numeric'  => '776',
					'exponent' => 2,
				),
			'TRY' =>
				array(
					'code'     => 'TRY',
					'currency' => 'Turkish lira',
					'numeric'  => '949',
					'exponent' => 2,
				),
			'TTD' =>
				array(
					'code'     => 'TTD',
					'currency' => 'Trinidad and Tobago dollar',
					'numeric'  => '780',
					'exponent' => 2,
				),
			'TWD' =>
				array(
					'code'     => 'TWD',
					'currency' => 'New Taiwan dollar',
					'numeric'  => '901',
					'exponent' => 2,
				),
			'TZS' =>
				array(
					'code'     => 'TZS',
					'currency' => 'Tanzanian shilling',
					'numeric'  => '834',
					'exponent' => 2,
				),
			'UAH' =>
				array(
					'code'     => 'UAH',
					'currency' => 'Ukrainian hryvnia',
					'numeric'  => '980',
					'exponent' => 2,
				),
			'UGX' =>
				array(
					'code'     => 'UGX',
					'currency' => 'Ugandan shilling',
					'numeric'  => '800',
					'exponent' => 0,
				),
			'USD' =>
				array(
					'code'     => 'USD',
					'currency' => 'United States dollar',
					'numeric'  => '840',
					'funding'  => true,
					'exponent' => 2,
				),
			'UYU' =>
				array(
					'code'     => 'UYU',
					'currency' => 'Uruguayan peso',
					'numeric'  => '858',
					'exponent' => 2,
				),
			'UZS' =>
				array(
					'code'     => 'UZS',
					'currency' => 'Uzbekistan som',
					'numeric'  => '860',
					'exponent' => 2,
				),
			'VEF' =>
				array(
					'code'     => 'VEF',
					'currency' => 'Venezuelan bolívar',
					'numeric'  => '937',
					'exponent' => 2,
				),
			'VND' =>
				array(
					'code'     => 'VND',
					'currency' => 'Vietnamese dong',
					'numeric'  => '704',
					'exponent' => 0,
				),
			'VUV' =>
				array(
					'code'     => 'VUV',
					'currency' => 'Vanuatu vatu',
					'numeric'  => '548',
					'exponent' => 0,
				),
			'WST' =>
				array(
					'code'     => 'WST',
					'currency' => 'Samoan tala',
					'numeric'  => '882',
					'exponent' => 2,
				),
			'XAF' =>
				array(
					'code'     => 'XAF',
					'currency' => 'CFA franc BEAC',
					'numeric'  => '950',
					'exponent' => 0,
				),
			'XCD' =>
				array(
					'code'     => 'XCD',
					'currency' => 'East Caribbean dollar',
					'numeric'  => '951',
					'exponent' => 2,
				),
			'XOF' =>
				array(
					'code'     => 'XOF',
					'currency' => 'CFA franc BCEAO',
					'numeric'  => '952',
					'exponent' => 0,
				),
			'XPF' =>
				array(
					'code'     => 'XPF',
					'currency' => 'CFP franc',
					'numeric'  => '953',
					'exponent' => 0,
				),
			'YER' =>
				array(
					'code'     => 'YER',
					'currency' => 'Yemeni rial',
					'numeric'  => '886',
					'exponent' => 2,
				),
			'ZAR' =>
				array(
					'code'     => 'ZAR',
					'currency' => 'South African rand',
					'numeric'  => '710',
					'exponent' => 2,
				),
			'ZMK' =>
				array(
					'code'     => 'ZMK',
					'currency' => 'Zambian kwacha',
					'numeric'  => '894',
					'exponent' => 2,
				),
			'ZWL' =>
				array(
					'code'     => 'ZWL',
					'currency' => 'Zimbabwean dollar',
					'numeric'  => '716',
					'exponent' => 2,
				),
		);
		if ( isset( $currencies[ $currency_iso_code ] ) ) {
			return $currencies[ $currency_iso_code ];
		} else {
			return null;
		}
	}
}