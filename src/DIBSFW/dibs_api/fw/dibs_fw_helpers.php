<?php
class dibs_fw_helpers extends dibs_fw_helpers_cms implements dibs_fw_helpers_interface {

    public static $bTaxAmount    = false;
    public static $sButtonsClass = "";
    
    /**
     * Process write SQL query (insert, update, delete) with build-in CMS ADO engine.
     * 
     * @param string $sQuery 
     */
    public function helper_dibs_db_write($sQuery) {
        $oDB_dibs =& JFactory::getDBO();
        $oDB_dibs->setQuery($sQuery);
        $oDB_dibs->query();
        return true;
    }
    
    /**
     * Read single value ($sName) from SQL select result.
     * If result with name $sName not found null returned.
     * 
     * @param string $sQuery
     * @param string $sName
     * @return mixed 
     */
    public function helper_dibs_db_read_single($sQuery, $sName) {
        $oDB_dibs =& JFactory::getDBO();
        $oDB_dibs->setQuery($sQuery);
        $mResult = $oDB_dibs->loadObjectList();
        unset($oDB_dibs);
        if(isset($mResult[0]->$sName)) return $mResult[0]->$sName;
        else return null;
    }
    
    /**
     * Return settings with CMS method.
     * 
     * @param string $sVar
     * @param string $sPrefix
     * @return string 
     */
    public function helper_dibs_tools_conf($sVar, $sPrefix = 'dibsfw_') {
       $sConfName = $sPrefix . $sVar;
        return $this->method_obj->$sConfName;
    }
    
    /**
     * Return CMS DB table prefix.
     * 
     * @return string 
     */
    public function helper_dibs_tools_prefix() {
        return "#__virtuemart_";
		//$dVar=new JConfig();
        //return $dVar->dbprefix;
    }
    
    /**
     * Returns text by key using CMS engine.
     * 
     * @param type $sKey
     * @return type 
     */
    public function helper_dibs_tools_lang($sKey, $sType = 'msg') {
        return $sKey;
    }

    /**
     * Get full CMS url for page.
     * 
     * @param string $sLink
     * @return string 
     */
    public function helper_dibs_tools_url($sLink) {
        //return $sLink;
		return JROUTE::_(JURI::root() . $sLink);
    }
    
    /**
     * Redirect with CMS method (used in CGI API methods)
     * 
     * @param string $sLink 
     */
    public function helper_dibs_tools_redirect($sLink) {
        zen_redirect($sLink);
    }
    
    /**
     * Build CMS order information to API object.
     * 
     * @param mixed $mOrderInfo
     * @param bool $bResponse
     * @return object 
     */
    public function helper_dibs_obj_order($mOrderInfo, $bResponse = FALSE) {
                
   if($bResponse === FALSE) {
            return (object)array(
                'orderid'  => $mOrderInfo->order->orderid,
                'amount'   => $mOrderInfo->cart['billTotal'],
                'currency' => $this->api_dibs_get_currencyValue(
                                  $this->cms_dibs_get_currency($mOrderInfo->order->currency)
                              )
                );
        }
        else {
            return (object)array(
                'orderid'  => $mOrderInfo['details']['BT']->virtuemart_order_id,
                'amount'   => $mOrderInfo['details']['BT']->order_total,
                'currency' => $this->api_dibs_get_currencyValue(
                                  $this->cms_dibs_get_currency(
                                      $mOrderInfo['details']['BT']->order_currency
                                  )
                              )
            );
        }
    }
    
    /**
     * Build CMS each ordered item information to API object.
     * 
     * @param mixed $mOrderInfo
     * @return array 
     */
    public function helper_dibs_obj_items($mOrderInfo) {
         if (!class_exists ('calculationHelper')) require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'calculationh.php');
	$calculator = calculationHelper::getInstance();
        $this->_cartData['VatTax'] = array();
        $this->_cartPrices['basePrice'] = 0;
        $this->_cartPrices['basePriceWithTax'] = 0;
        $this->_cartPrices['discountedPriceWithoutTax'] = 0;
        $this->_cartPrices['salesPrice'] = 0;
        $this->_cartPrices['taxAmount'] = 0;
        $this->_cartPrices['salesPriceWithDiscount'] = 0;
        $this->_cartPrices['discountAmount'] = 0;
        $this->_cartPrices['priceWithoutTax'] = 0;
        $this->_cartPrices['subTotalProducts'] = 0;
        $this->_product = null;
        $this->_cartData['DBTaxRulesBill'] = $calculator->gatherEffectingRulesForBill('DBTaxBill');
        $this->_cartData['taxRulesBill'] = $calculator->gatherEffectingRulesForBill('TaxBill');
        $this->_cartData['DATaxRulesBill'] = $calculator->gatherEffectingRulesForBill('DATaxBill');
        $this->_cartPrices['salesPriceDBT'] = array();
        $this->_cartPrices['taxRulesBill'] = array();
        $this->_cartPrices['DATaxRulesBill'] = array();
        $oCart = VirtueMartCart::getCart();
        $arr = $calculator->getCheckoutPrices($oCart, true);
        $prices = $calculator->getCartPrices();
        $aItems = array();
        foreach($mOrderInfo->order->items as $mItem) {
              $aItems[] = (object)array(
                    'id'    => $mItem->virtuemart_product_id,
                    'name'  => $mItem->order_item_name,
                    'sku'   => $mItem->order_item_sku,
                    'price' => $mItem->product_final_price,
                    'qty'   => $mItem->product_quantity,
                    'tax'   => 0//$mItem->product_tax
                );
            }
                
        // Calculate the discount from all rules before tax to calculate billTotal
	$cartdiscountBeforeTax = $calculator->roundInternal($calculator->cartRuleCalculation($this->_cartData['DBTaxRulesBill'], $prices['salesPrice']));
        
        // calculate the new subTotal with discounts before tax, necessary for billTotal
	$toTax = $prices['salesPrice'] + $cartdiscountBeforeTax;
        // now each taxRule subTotal is reduced with DBTax and we can calculate the cartTax 
	$cartTax = $calculator->roundInternal($calculator->cartRuleCalculation($this->_cartData['taxRulesBill'], $toTax));
         // toDisc is new subTotal after tax, now it comes discount afterTax and we can calculate the final cart price with tax.
	$toDisc = $toTax + $cartTax;
        
        $arr = $calculator->calculateShipmentPrice($oCart, true);
        $this->_cartPrices['salesPriceShipment'] = $arr['salesPriceShipment'];
        $arr = $calculator->calculatePaymentPrice($oCart, true);
        $this->_cartPrices['salesPricePayment'] = $arr['salesPricePayment'];
        $cartdiscountAfterTax = $calculator->roundInternal($calculator->cartRuleCalculation($this->_cartData['DATaxRulesBill'], $toDisc));
        $this->_cartPrices['withTax'] = $toDisc + $cartdiscountAfterTax;
        $this->_cartPrices['billTotal'] = $this->_cartPrices['salesPriceShipment'] + $this->_cartPrices['salesPricePayment'] + $this->_cartPrices['withTax'] + $this->_cartPrices['salesPriceCoupon'];

        if( $this->_cartPrices['salesPriceShipment']  ) {
            
            $aItems[] = (object)array(
                'id'    => '1',
                'name'  => 'Shipping',
                'sku'   => '',
                'price' => $this->_cartPrices['salesPriceShipment'],
                'qty'   => 1,
                'tax'   => 0//$mItem->product_tax
            );
            
        }
        
        if( abs($cartdiscountBeforeTax) ) {
             $aItems[] = (object)array(
                'id'    => 'discount',
                'name'  => 'Discount',
                'sku'   => '',
                'price' => $cartdiscountBeforeTax,
                'qty'   => 1,
                'tax'   => 0//$mItem->product_tax
            );
        }
        
        if( $cartTax ) {
            
             $aItems[] = (object)array(
                'id'    => 'cart_tax',
                'name'  => 'Tax',
                'sku'   => '',
                'price' => $cartTax,
                'qty'   => 1,
                'tax'   => 0//$mItem->product_tax
            );
        }
        
       if( $this->_cartPrices['salesPricePayment'] ) {
             $aItems[] = (object)array(
                'id'    => 'cart_tax1',
                'name'  => 'Tax',
                'sku'   => '',
                'price' => $this->_cartPrices['salesPricePayment'],
                'qty'   => 1,
                'tax'   => 0//$mItem->product_tax
            );
        }
        
        if( $cartdiscountAfterTax ) {
             $aItems[] = (object)array(
                'id'    => 'cart_tax2',
                'name'  => 'Discount',
                'sku'   => '',
                'price' => $cartdiscountAfterTax,
                'qty'   => 1,
                'tax'   => 0//$mItem->product_tax
            );
        }
       
            
         if( abs($mOrderInfo->cart['salesPriceCoupon']) ){
             $aItems[] = (object)array(
                'id'    => 'cart_coupone',
                'name'  => 'Coupone',
                'sku'   => '',
                'price' => $mOrderInfo->cart['salesPriceCoupon'],
                'qty'   => 1,
                'tax'   => 0//$mItem->product_tax
            );
        }
        return $aItems;
    }
    
    /**
     * Build CMS shipping information to API object.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    public function helper_dibs_obj_ship($mOrderInfo) {
 
        return (object)array(
            'rate'       => $mOrderInfo->cart['shipmentValue'],
            'tax'        => $mOrderInfo->shipping_tax
        );
    }
    
    /**
     * Build CMS customer addresses to API object.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    public function helper_dibs_obj_addr($mOrderInfo) {
       return (object)array(
           'delivery11.Delivery' => 'Shipping Address',
           'delivery12.Firstname' => $mOrderInfo->shipping->first_name,
           'delivery13.Lastname' => $mOrderInfo->shipping->last_name,
           //'delivery14.Street' => $oOrderInfo->customer->delivery->street,
           'delivery15.Postcode' => $mOrderInfo->shipping->zip,
           'delivery16.City' =>$mOrderInfo->shipping->city,
           'delivery17.Address' => ShopFunctions::getCountryByID(
                                        $mOrderInfo->cart_addr->shipping['virtuemart_country_id'],
                                        'country_3_code'
                                    ) . " " . 
                                    isset($mOrderInfo->cart_addr->shipping['virtuemart_state_id']) ?
                                    ShopFunctions::getStateByID(
                                        $mOrderInfo->cart_addr->shipping['virtuemart_state_id']
                                    ) : '',
           
           'delivery18.Address2' => $mOrderInfo->shipping->address_1 . " " . 
                                    $mOrderInfo->shipping->address_2,
           //'delivery19.Telephone' => $oOrderInfo->customer->delivery->phone,
          'delivery01.Billing' => 'Billing Address',
          'delivery02.Firstname' => $mOrderInfo->billing->first_name,
          'delivery03.Lastname' => $mOrderInfo->billing->last_name,
          //'delivery04.Street' => $oOrderInfo->customer->billing->street,
          'delivery05.Postcode' => $mOrderInfo->billing->zip,
          'delivery06.City' => $mOrderInfo->billing->city,
          //'delivery07.Region' => $oOrderInfo->customer->billing->region,
          'delivery08.Address' => ShopFunctions::getCountryByID(
                                        $mOrderInfo->cart_addr->billing['virtuemart_country_id'],
                                        'country_3_code'
                                    ) . " " . 
                                   isset($mOrderInfo->cart_addr->billing['virtuemart_state_id']) ?
                                    ShopFunctions::getStateByID(
                                        $mOrderInfo->cart_addr->billing['virtuemart_state_id']
                                    ) : '',
          'delivery09.Telephone' => $mOrderInfo->billing->phone_1,
          'delivery10.E-mail' => $mOrderInfo->billing->email
          
        ); 
    }
    
    /**
     * Returns object with URLs needed for API, 
     * e.g.: callbackurl, acceptreturnurl, etc.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    public function helper_dibs_obj_urls($mOrderInfo = null) {
        return (object)array(
            'acceptreturnurl' => 'index.php/component/virtuemart/pluginresponse/pluginresponsereceived/',
            'callbackurl'     => 'index.php/component/virtuemart/pluginresponse/pluginnotification/',
            'cancelreturnurl' => 'index.php/component/virtuemart/pluginresponse/pluginuserpaymentCancel/',
            'carturl'         => "index.php/cart/"
        );
  
    } 
    
    /**
     * Returns object with additional information to send with payment.
     * 
     * @param mixed $mOrderInfo
     * @return object 
     */
    public function helper_dibs_obj_etc($mOrderInfo) {
        return (object)array(
            'sysmod'      => 'j25vm2_3_0_3',
            'pm'          => $mOrderInfo->billing->virtuemart_paymentmethod_id,
        );
    }
    
    /**
     *
     * @param mixed $mOrderInfo
     */
    public function helper_dibs_hook_callback($mOrderInfo) {
           if(!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        $oModelOrder = VmModel::getModel('orders');
        $virtuemart_order_id = $oModelOrder->getOrderIdByOrderNumber($mOrderInfo['details']['BT']->order_number);
        $order = array();
        $order['order_status'] = $this->helper_dibs_tools_conf('status_success','');
        $order['customer_notified'] = 1;
        $order['comments'] = JText::sprintf('VMPAYMENT_DIBSFW_PAYMENT_STATUS_CONFIRMED', $virtuemart_order_id);
        $oModelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);    
    }
}
?>
