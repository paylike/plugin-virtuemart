<?php
defined ('_JEXEC') or die();

/**
 * @author Valérie Isaksen
 * @version $Id$
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004 - 2014 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
 
$method = $viewData["method"];
include_once(JPATH_PLUGINS.'/'.$this->_type . '/' . $this->_name.'/tmpl/pay_before_js.php');
 ?>
<script>
	vmPaylike.method["ID<?php echo $method->virtuemart_paymentmethod_id ?>"] = <?php echo $method->virtuemart_paymentmethod_id; ?>;
</script>
