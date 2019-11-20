<?php

include_once( 'Paylike/Client.php' );
include_once( 'utils/PaylikeCurrency.php' );
if ( ! class_exists( 'vmPSPlugin' ) ) {
	require( JPATH_VM_PLUGINS . DS . 'vmpsplugin.php' );
}

class plgVmPaymentPaylike extends vmPSPlugin {

	// instance of class
	public static $_this = false;

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
				/* load scripts and stylesheets*/
				$document = JFactory::getDocument();
				$document->addScript( 'https://sdk.paylike.io/3.js' );
				JHtml::_( 'jquery.framework' );
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
		$return = "<div class='paylike-wrapper' style='display: inline-block'><div class='paylike_title' >" . $plugin->title . "</div>";
		$return .= "<div class='payment_logo' >";
		$card = implode( "~", $plugin->card );
		$version = explode( ".", $this->getJoomlaVersions() );
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

		$method = $this->getPaymentDetail( $order['details']['BT']->virtuemart_paymentmethod_id ); // get method detail

		if ( ! count( $method ) ) {
			return;
		}

		$currency_model = VmModel::getModel( 'currency' );
		$currency_id = $order["details"]["BT"]->order_currency;
		$displayCurrency = $currency_model->getCurrency( $currency_id );
		$currency = $displayCurrency->currency_symbol;
		$session = JFactory::getSession();
		if ( $this->getPaymentDetail( $order["details"]["BT"]->virtuemart_paymentmethod_id )['checkout_mode'] != "after" ) { ?>
			<script type="text/javascript">
						jQuery(window).load(function() {
							setTimeout(function() {
								jQuery("body").append('<input type="hidden" name="virtuemart_order_id" value="<?php echo $order["details"]["BT"]->virtuemart_order_id; ?>" /><input type="hidden" name="order_number" value="<?php echo $order["details"]["BT"]->order_number; ?>" />');
								postData('<?php echo $session->get( 'transactionId' ); ?>');
							}, 2000);
						});
			</script>
			<?php
		} else { ?>
			<script type="text/javascript">
						jQuery(window).load(function() {
							setTimeout(function() {
								jQuery("body").append('<button type="button" class="orderDetail" class="btn btn-info btn-lg" data-toggle="modal" data-target="#myModal" style="display:none;">Open Modal</button><div class="modal fade" id="myModal" role="dialog"> <div class="modal-dialog" style="text-align: left;"> <div class="modal-content">     <div class="modal-header"> <button type="button" class="close" data-dismiss="modal">&times;</button> <h4 class="modal-title">Pay for Order</h4> </div> <div class="modal-body" style="padding: 10px 30px;"> <p><div class="orderDetail"><ul class="order_details"><li class="order">Order number:<strong><?php echo $order["details"]["BT"]->order_number; ?></strong></li><li class="date">Date:<strong><?php echo date( "M d,Y", strtotime( $order["details"]["BT"]->created_on ) ); ?></strong></li><li class="total">Total:<strong><?php echo $currency . number_format( $order["details"]["BT"]->order_total, 2 ); ?></strong></li><li class="method">Payment method:<strong><?php echo $this->getPaymentDetail( $order["details"]["BT"]->virtuemart_paymentmethod_id )['title']; ?></strong></li><li><a href="javascript:void(0);" id="afterOrderPaylike" >Pay</a></li></ul></div></p> </div> <div class="modal-footer"> <button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div> </div> </div><style>.modal-backdrop.fade.in{ opacity: .4;}#myModal { background: none;margin-left: auto;width: 100%;overflow: hidden;}</style><input type="hidden" name="virtuemart_order_id" value="<?php echo $order["details"]["BT"]->virtuemart_order_id; ?>" /><input type="hidden" name="order_number" value="<?php echo $order["details"]["BT"]->order_number; ?>" /><script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"><script src="<?php echo JURI::base(); ?>plugins/vmpayment/paylike/bootstrap.min.js"><\\/script>');
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

	/*
		Predefined function of Virtualmart to save selected setting as plugin parameters
	*/
	function plgVmSetOnTablePluginParamsPayment( $name, $id, &$table ) {
		return $this->setOnTablePluginParams( $name, $id, $table );
	}

	function plgVmOnUpdateOrderPayment( $orders, $old_order_status ) {

		$method = $this->getPaymentDetail( $orders->virtuemart_paymentmethod_id ); // get method detail

		if ( ! count( $method ) ) {
			return;
		}

		/* if testing mode */
		if ( $method['test_mode'] == 1 ) {
			$privateKey = $method['test_api_key'];
			$publicKey = $method['test_public_key'];
		} else /* if live mode */ {
			$privateKey = $method['live_api_key'];
			$publicKey = $method['live_public_key'];
		}
		\Paylike\Client::setKey( $privateKey );
		if ( strtolower( $this->getOrderStateName( $orders->order_status ) ) == "completed" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_COMPLETED" || strtolower( $this->getOrderStateName( $orders->order_status ) ) == "shipped" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_SHIPPED" ) {
			$orderDetail = $this->getAllOrderDetails( $orders->order_number );
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
		} else if ( strtolower( $this->getOrderStateName( $orders->order_status ) ) == "refunded" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_REFUNDED" || strtolower( $this->getOrderStateName( $orders->order_status ) ) == "cancelled" || $this->getOrderStateName( $orders->order_status ) == "COM_VIRTUEMART_ORDER_STATUS_CANCELLED" ) {

			$orderDetail = $this->getAllOrderDetails( $orders->order_number );
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
		} else if ( strtolower( $this->getOrderStateName( $orders->order_status ) ) == "refund half" ) {
			$orderDetail = $this->getAllOrderDetails( $orders->order_number );
			$txtid = $orderDetail->txnid;
			$response = \Paylike\Transaction::fetch( $txtid ); // fetch order detail from paylike
			/* refund payment if already captured */
			if ( $response['transaction']['capturedAmount'] > 0 && $response['transaction']['refundedAmount'] <= 0 ) {
				$amount = $response['transaction']['capturedAmount'];
				$data = array(
					'amount'     => round( $amount / 2 ),
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
			return null; // Another method was selected, do nothing
		}
		if ( ! $this->selectedThisElement( $method->payment_element ) ) {
			return false;
		}
		$this->getPaymentCurrency( $method );
		$paymentCurrencyId = $method->payment_currency;
		$currency_model = VmModel::getModel( 'currency' );
		$displayCurrency = $currency_model->getCurrency( $paymentCurrencyId );
		if ( $type == "code" ) {
			return $currency = $displayCurrency->currency_code_3;
		} else {
			return $currency = $displayCurrency->currency_symbol;
		}
	}


	/* load hidden field to send data on paylike */
	function loadParamsForPaylike( $publicKey, $method ) {
		/* get billing detail from cart */
		$billingDetail = VirtueMartCart::getCart( false )->BT;
		$price = number_format( VirtueMartCart::getCart( false )->cartPrices['withTax'], 2 );
		$currency = $this->plgVmgetPaymentCurrency( $method->virtuemart_paymentmethod_id, "code" ); //Currency code from currency id

		$priceInCents = ceil( round( $price, 3 ) * $this->paylikeCurrency->getPaylikeCurrencyMultiplier( $currency ) );
		$address = $this->loadAddress();
		foreach ( VirtueMartCart::getCart( false )->products as $product ) {
			echo "<input type='hidden' value='" . $product->product_name . "' name='paylikeProductName[]' />";
			echo "<input type='hidden' value='" . $product->virtuemart_product_id . "' name='paylikeProductId[]' />";
			echo "<input type='hidden' value='" . $product->quantity . "' name='paylikeQuantity[]' />";
			if ( $priceInCents == 0 ) {
				$price += $product->prices['salesPrice'] * $product->quantity;
			}
		}
		if ( VirtueMartCart::getCart( false )->cartPrices['salesPriceShipment'] ) {
			$price += VirtueMartCart::getCart( false )->cartPrices['salesPriceShipment'];

		}
		// remove thousand seperators(,)
		$price = floatval( str_replace( ",", "", $price ) );

		$priceInCents = ceil( round( $price, 3 ) * $this->paylikeCurrency->getPaylikeCurrencyMultiplier( $currency ) );
		/* load hidden field having user and product data */
		echo "<input type='hidden' value='" . round( $priceInCents ) . "' name='paylikePrice' />";
		echo "<input type='hidden' value='" . $currency . "' name='paylikeCurrency' />";
		echo "<input type='hidden' value='" . $method->checkout_mode . "' name='paylikeMode' />";
		echo "<input type='hidden' value='" . $method->capture_mode . "' name='paylikeCaptureMode' />";
		echo "<input type='hidden' value='" . dirname( __FILE__ ) . "' name='paylikePath' />";
		echo "<input type='hidden' value='" . $method->virtuemart_paymentmethod_id . "' name='paylikePaymentMethod' />";
		echo "<input type='hidden' value='" . JURI::base() . "' name='paylikeBase' />";
		if ( isset( $billingDetail['first_name'] ) ) {
			$lang = JFactory::getLanguage();
			$languages = JLanguageHelper::getLanguages( 'lang_code' );
			$languageCode = $languages[ $lang->getTag() ]->sef;
			echo "<input type='hidden' value='" . $billingDetail['first_name'] . " " . $billingDetail['last_name'] . "' name='paylikeName' />";
			echo "<input type='hidden' value='" . $billingDetail['email'] . "' name='paylikeEmail' />";
			echo "<input type='hidden' value='" . $billingDetail['phone_1'] . "' name='paylikePhone' />";
			echo "<input type='hidden' value='" . $address . "' name='paylikeAddress' />";
			echo "<input type='hidden' value='" . $_SERVER["REMOTE_ADDR"] . "' name='paylikeIp' />";
			echo "<input type='hidden' value='Joomla' name='paylikePlatformName' />";
			echo "<input type='hidden' value='" . $this->getJoomlaVersions() . "' name='paylikePlatformVersion' />";
			echo "<input type='hidden' value='VirtueMart' name='paylikeEcommerce' />";
			echo "<input type='hidden' value='" . $this->getVirtuemartVersions() . "' name='paylikeEcommerceVersion' />";
			echo "<input type='hidden' value='" . $languageCode . "' name='paylikeLocale' />";
			echo "<input type='hidden' value='" . $method->popup_title . "' name='paylikePopupTitle' />";
			echo "<input type='hidden' value='" . $method->title . "' name='paylikeTitle' />";
			echo "<form action='' id='capturePayment' method='post' ><input type='hidden' value='" . round( $priceInCents ) . "' name='paymentprice' /><input name='paymentType' type= 'hidden' value='captureTransactionFull'/><input type='hidden' value='" . $currency . "' name='paymentcurrency' /></form>";
		}

		return true;
	}

	/* load address of user */
	function loadAddress() {
		$billingDetail = VirtueMartCart::getCart( false )->BT;
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

		$session = JFactory::getSession();
		$method = $this->getPaymentDetail( $_REQUEST['virtuemart_paymentmethod_id'] ); // get method detail

		/* if testing mode */
		if ( $method['test_mode'] == 1 ) {
			$privateKey = $method['test_api_key'];
			$publicKey = $method['test_public_key'];
		} else /* if live mode */ {
			$privateKey = $method['live_api_key'];
			$publicKey = $method['live_public_key'];
		}
		\Paylike\Client::setKey( $privateKey ); // set private key for further paylike functions

		if ( isset( $_REQUEST['paymentType'] ) && $_REQUEST['paymentType'] == "captureTransactionFull" ) {
			/* capture payment when order created (instant payment) */
			$session->set( 'transactionId', $_REQUEST['transactionId'] );
			if ( $_REQUEST['paylikeCaptureMode'] == 'instant' ) {
				$amount = $_REQUEST['paymentprice'];
				$currency = $_REQUEST['paymentcurrency'];
				$data = array(
					'amount'   => $amount,
					'currency' => $currency
				);
				$transactionId = $_REQUEST['transactionId'];
				$response = \Paylike\Transaction::capture( $transactionId, $data );
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

	/* get Payment Method Detail for database */
	function getPaymentDetail( $virtuemart_paymentmethod_id ) {
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `#__virtuemart_paymentmethods` '
			. 'WHERE `virtuemart_paymentmethod_id` = ' . $virtuemart_paymentmethod_id;
		$db->setQuery( $q );
		$row = $db->loadObject();

		if ( $row->payment_element != "paylike" ) {
			return array();
		}

		$data = $row->payment_params;

		$explodedData = explode( "|", $data );
		$finalData = [];
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
		$q = 'SELECT txnid FROM `#__virtuemart_payment_plg_paylike` '
			. 'WHERE `order_number` = ' . $db->quote( $orderNumber );
		$db->setQuery( $q );
		$txnid = $db->loadresult();

		return $txnid;
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