<?php

 class common {

	function getOrderPaymentDetail($virtuemart_paymentmethod_id) {
	   $db = JFactory::getDBO();
	   $q = 'SELECT payment_params FROM `#__virtuemart_paymentmethods` '
	      . 'WHERE `virtuemart_paymentmethod_id` = '.$virtuemart_paymentmethod_id;     
	   $db->setQuery($q);
	   $data = $db->loadresult();
	  	$explodedData= explode("|", $data);
	  	$finalData = [];
	  	foreach($explodedData as $exp)
	  	{
	  		$val = explode("=",$exp);
	  		if(!empty($val[0]))
	  		{
	  			$finalData[$val[0]] = str_replace('"', "", $val[1]);
	  		}
	  	}
	    return $finalData;
    }
    function getOrderDetails($orderNumber) {
	   $db = JFactory::getDBO();
	   $q = 'SELECT txnid FROM `#__virtuemart_payment_plg_paylike` '
	      . 'WHERE `order_number` = '.$db->quote($orderNumber);     
	   $db->setQuery($q);
	   $txnid = $db->loadresult();
	    return $txnid;
    }
} 

?>