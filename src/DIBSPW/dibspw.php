<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * @author DIBS A/S
 * @copyright Copyright (C) 2012 DIBS A/S
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
if(!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
require_once dirname(__FILE__) . DS . 'dibs_api' . DS . 'pw' . DS . 'dibs_pw_api.php';

class plgVmPaymentDibspw extends dibs_pw_api {

    // instance of class 
    public static $_this = false;
    private static $aSqlFields = array(
	   'id' => ' INT(11) unsigned NOT NULL AUTO_INCREMENT ',
	    'virtuemart_order_id' => ' int(1) UNSIGNED DEFAULT NULL',
	    'order_number' => ' char(32) DEFAULT NULL',
	    'virtuemart_paymentmethod_id' => ' mediumint(1) UNSIGNED DEFAULT NULL',
	    'payment_name' => 'varchar(5000)',
	    'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
	    'payment_currency' => 'char(3) ',
	    'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
	    'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
	    'tax_id' => ' smallint(1) DEFAULT NULL',
    );

    function __construct(& $subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $aVarsToPush = array(
            'dibspw_mid' => array('', 'char'),
            'dibspw_method' => array('2', 'int'),
            'dibspw_hmac' => array('', 'char'),
            'dibspw_testmode' => array('yes', 'char'),
            'dibspw_fee' => array('no', 'char'),
            'dibspw_capturenow' => array('no', 'char'),
            'dibspw_voucher' => array('no', 'char'),
            'dibspw_uniq' => array('no', 'char'),
            'dibspw_paytype' => array('', 'char'),
            'dibspw_lang' => array('en_UK', 'char'),
            'dibspw_account' => array('', 'char'),
            'dibspw_distr' => array('empty', 'char'),
            'payment_logos' => array('', 'char'),
            'status_pending' => array('', 'char'),
            'status_success' => array('', 'char'),
            'status_canceled' => array('', 'char'),
            'countries' => array(0, 'char'),
            'min_amount' => array(0, 'int'),
            'max_amount' => array(0, 'int'),
            'cost_per_transaction' => array(0, 'int'),
            'cost_percent_total' => array(0, 'int'),
            'tax_id' => array(0, 'int')
        );

        $this->setConfigParameterable($this->_configTableFieldName, $aVarsToPush);
    }

    public function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('DIBS PW Results Table');
    }

    function getTableSQLFields() {
        return self::$aSqlFields;
    }

    function plgVmConfirmedOrder($oCart, $oOrder) {
        if(!($method = $this->getVmPluginMethod($oOrder['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        
        if(!$this->selectedThisElement($method->payment_element)) return false;

        $session = JFactory::getSession();
        $return_context = $session->getId();
        $this->method_obj = $method;
        $this->logInfo('plgVmConfirmedOrder order number: ' . $oOrder['details']['BT']->order_number, 'message');

        if(!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        if(!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        if(!class_exists('TableVendors'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
        
        $new_status = '';
        
        $iShipTaxId = isset($oCart->pricesUnformatted['shipment_tax_id']) ? 
                      (int)$oCart->pricesUnformatted['shipment_tax_id'] : 0;
        $mShippingTax = "";
        if(!empty($iShipTaxId)) {
            $mShippingTax = $this->helper_dibs_db_read_single("SELECT `calc_value` 
                                               FROM `" . $this->helper_dibs_tools_prefix() . "calcs` 
                                               WHERE `virtuemart_calc_id` = " . 
                                               $iShipTaxId . " 
                                               LIMIT 1", "calc_value");
        }

        $mShippingTax = ($mShippingTax !== null && !empty($mShippingTax)) ? $mShippingTax : "0";
        $vendorModel = VmModel::getModel('Vendor');
        $vendorModel->setId(1);
        $vendor = $vendorModel->getVendor();
        $vendorModel->addImages($vendor, 1);
        $this->getPaymentCurrency($method);

        $oOrderInfo = (object) array(
                    'order' => (object) array(
                        'orderid' => $oOrder['details']['BT']->virtuemart_order_id,
                        'currency' => $method->payment_currency,
                        'items' => $oOrder['items']
                    ),
                    'shipping_tax' => $mShippingTax,
                    'cart' => $oCart->pricesUnformatted,
                    'shipping' => isset($oOrder['details']['ST']) ? $oOrder['details']['ST'] :
                            $oOrder['details']['BT'],
                    'billing' => $oOrder['details']['BT'],
                    'cart_addr' => (object) array(
                        'billing' => $oCart->BT,
                        'shipping' => $oCart->ST == 0 ? $oCart->BT : $oCart->ST
                    )
        );
        
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo(
                                          $method->payment_currency, 
                                          $oOrder['details']['BT']->order_total, 
                                          false), 2);
        $aDbValues = array(
        'virtuemart_order_id' => $oOrder['details']['BT']->virtuemart_order_id,
		'order_number' => $oOrder['details']['BT']->order_number,
		'payment_name' => $this->renderPluginName($method, $oOrder),
		'virtuemart_paymentmethod_id' => $oCart->virtuemart_paymentmethod_id,
		'cost_per_transaction' => $method->cost_per_transaction,
		'cost_percent_total' => $method->cost_percent_total,
		'payment_currency' => $method->payment_currency,
		'payment_order_total' => $totalInPaymentCurrency,
		'tax_id' => $method->tax_id
        );

        $this->storePSPluginInternalData($aDbValues);

        $aData = $this->api_dibs_get_requestFields($oOrderInfo);
        
        if(empty($aData['merchant'])) {
            JError::raiseWarning(100,JText::_('VMPAYMENT_DIBSPW_MID_NOT_SET'));
            return false;
        }
        
        $sForm = JText::_('VMPAYMENT_DIBSPW_REDIRECT'); 
        $sForm.= '<form action="' . $this->api_dibs_get_formAction() .
                '" method="post" name="vm_dibspw_form" >';
        foreach($aData as $sName => $sValue) {
            $sForm.= '<input type="hidden" name="' . $sName . 
                     '" value="' . htmlspecialchars($sValue) . '" />';
        }
        
        $sForm.= ' </form>';
        $sForm.= ' <script type="text/javascript">';
        $sForm.= ' document.vm_dibspw_form.submit();';
        $sForm.= ' </script>';

        return $this->processConfirmedOrderPaymentResponse(2, $oCart, $oOrder, $sForm, 
                                                           $this->renderPluginName($method, $oOrder), 
                                                           $new_status);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
        if(!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) return null; // Another method was selected, do nothing
        if(!$this->selectedThisElement($method->payment_element)) return false;
        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnPaymentResponseReceived(&$html) {
        $virtuemart_paymentmethod_id = JRequest::getInt('s_pm', 0);
        if(!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
            return null; // Another method was selected, do nothing
        if(!$this->selectedThisElement($method->payment_element))
            return false;
        if(!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        if(!class_exists('shopFunctionsF'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        if(!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $this->method_obj = $method;
        $aPaymentData = JRequest::get('post');
        $sPaymentName = $this->renderPluginName($method);
        vmdebug('plgVmOnPaymentResponseReceived', $aPaymentData);
         
        $oModelOrder = VmModel::getModel('orders');
        $virtuemart_order_id = $aPaymentData['orderid'];
        $oOrder = $oModelOrder->getOrder($virtuemart_order_id);
        $this->api_dibs_action_success($oOrder);
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }

    function plgVmOnUserPaymentCancel() {
        if(!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        $virtuemart_order_id = JRequest::getString('orderid');
        if(!$virtuemart_order_id) return null;
        $this->api_dibs_action_cancel();
        $this->handlePaymentUserCancel($virtuemart_order_id);

        return true;
    }

    /*
     *   plgVmOnPaymentNotification() - This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
     * Return:
     * Parameters:
     *  None
     *  @author Valerie Isaksen
     */

    function plgVmOnPaymentNotification() {
        $aPaymentData = $_POST;
        if(!isset($aPaymentData['orderid']) || !isset($aPaymentData['s_sysmod'])) return;
        if(!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        $oModelOrder = VmModel::getModel('orders');
        $virtuemart_order_id = $aPaymentData['orderid'];
        if(!$virtuemart_order_id) return;
        $payment = $this->getDataByOrderId($virtuemart_order_id);
        $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
        if(!$this->selectedThisElement($method->payment_element)) return false;
        if(!$payment) {
            $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
            return null;
        } 
        // Save specific data to db table payment_plg_dibspw
        $db = JFactory::getDBO();
        $query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
        $db->setQuery($query);
        $columns = $db->loadResultArray(0);
        $post_msg = '';
        foreach ($aPaymentData as $key => $value) {
            $table_key = 'dibspw_' . $key;
            if (in_array($table_key, $columns)) {
            $response_fields[$table_key] = $value;
        }
        } 
         
        $response_fields['payment_name'] = $this->renderPluginName($method);
        $response_fields['order_number'] = $order_number;
        $response_fields['virtuemart_order_id'] = $virtuemart_order_id;
        $response_fields['virtuemart_paymentmethod_id'] = $payment->virtuemart_paymentmethod_id;
        
       
        $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', $virtuemart_order_id, false);
        
        // Set Confirmed status to order
        $oModelOrder = VmModel::getModel('orders');
        $virtuemart_order_id = $aPaymentData['orderid'];
        $oOrder = $oModelOrder->getOrder($virtuemart_order_id);
        $this->api_dibs_action_success($oOrder);

        if($virtuemart_order_id) {
            $order['customer_notified'] = 0;
            $order['order_status'] = $this->helper_dibs_tools_conf('status_success', '');
            
            // send the email ONLY if payment has been accepted
            if($oOrder['history'][count($oOrder['history']) - 1]->order_status_code != $order['order_status']) {
                $this->logInfo('plgVmOnPaymentResponseReceived, sentOrderConfirmedEmail ' . $virtuemart_order_id, 'message');
                $order['virtuemart_order_id'] = $virtuemart_order_id;
                $order['comments'] = JText::sprintf('VMPAYMENT_DIBSPW_EMAIL_SENT');
                $oModelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
            }
        }
        else {
            vmError('DIBS data received, but no order id');
            return;
        }
       
       
        $this->method_obj = $method;
        $this->api_dibs_action_callback($oModelOrder->getOrder($virtuemart_order_id)); 
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if(preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        }
        else $cost_percent_total = $method->cost_percent_total;

        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {
        $this->convert($method);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount && $amount <= $method->max_amount ||
                       ($method->min_amount <= $amount && ($method->max_amount == 0) ));

        $countries = array();
        if(!empty($method->countries)) {
            if(!is_array($method->countries)) $countries[0] = $method->countries;
            else $countries = $method->countries;
        }
        // probably did not gave his BT:ST address
        if(!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if(!isset($address['virtuemart_country_id'])) $address['virtuemart_country_id'] = 0;
        if(in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if($amount_cond) return true;
        }

        return false;
    }

    function convert($method) {
	$method->min_amount = (float) $method->min_amount;
	$method->max_amount = (float) $method->max_amount;
    }
    
    /**
     * We must reimplement this triggers for joomla 1.7
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
    
     /**
     * Display stored payment data for an order
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {
    if (!$this->selectedThisByMethodId($payment_method_id)) {
        return null; // Another method was selected, do nothing
    }
    /*
    $db = JFactory::getDBO();
    $q = 'SELECT * FROM `' . $this->_tablename . '` '
        . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
    $db->setQuery($q);
    if (!($paymentTable = $db->loadObject())) {
        return '';
    }
    $html = '<table class="adminlist">' . "\n";
    $html .= $this->getHtmlHeaderBE();
    $html .= $this->getHtmlRowBE('dibspw_methodname', $paymentTable->payment_name);
    $code = "dibspw_";
    $paymentFields = array('transaction', 'acquirer', 'status', 'test');
    foreach ($paymentTable as $key => $value) {
        if (substr($key, 0, strlen($code)) == $code) {
            if( in_array(substr($key, strlen($code)), $paymentFields)) {    
                $html .= $this->getHtmlRowBE($key, $value);
            }
        }
    }
    $html .= '</table>' . "\n";
    return $html;
    */
    
    $db = JFactory::getDBO();
    $q = 'SELECT * FROM `' . $this->helper_dibs_tools_prefix(). dibs_pw_api::api_dibs_get_tableName() . '` '
        . 'WHERE `orderid` = ' . $virtuemart_order_id;
    $db->setQuery($q);
    if (!($paymentTable = $db->loadObject())) {
        return '';
    }
    $method = $this->getVmPluginMethod($payment_method_id);
    $html = '<table class="adminlist">' . "\n";
    $html .= $this->getHtmlHeaderBE();
    $html .= $this->getHtmlRowBE('Payment method name', $method->payment_name);
    $paymentFields = array('transaction', 'paytype', 'status', 'testmode');
    foreach ($paymentTable as $key => $value) {
            if( in_array($key, $paymentFields)) {    
                $html .= $this->getHtmlRowBE($key, $value);
        }
    }
    $html .= '</table>' . "\n";
    return $html;
    
    
    }

}

// No closing tag