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
     $aItems = array();
        //$aDiscounts = array();
        foreach($mOrderInfo->order->items as $mItem) {
            /*$this->cms_dibs_get_discounts(
                $mOrderInfo->cart[$mItem->virtuemart_product_id]['DATax'],
                $mItem->product_quantity,
                $aDiscounts
            );*/
            
            $aItems[] = (object)array(
                'id'    => $mItem->virtuemart_product_id,
                'name'  => $mItem->order_item_name,
                'sku'   => $mItem->order_item_sku,
                'price' => $mItem->product_item_price,
                'qty'   => $mItem->product_quantity,
                'tax'   => $this->cms_dibs_get_tax_item(
                               $mOrderInfo->cart[$mItem->virtuemart_product_id]['Tax']
                           )
            );
        }
        unset($mItem);
/*        foreach($aDiscounts as $iKey => $mItem) {
            $aItems[] = (object)array(
                'id'    => 'd' . $iKey,
                'name'  => $mItem->name,
                'sku'   => $mItem->name,
                'price' => -$mItem->price,
                'qty'   => $mItem->qty,
                'tax'   => 0
            );
        }*/
        $aItems[] = (object)array(
            'id'    => 'discount0',
            'name'  => 'Total Discount',
            'sku'   => 'TotalDiscount0',
            'price' => $mOrderInfo->cart['discountAmount'],
            'qty'   => 1,
            'tax'   => 0
        );
        
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
            'callbackurl'     =>  "http://izotov.net/max.php",   //"index.php/component/virtuemart/pluginresponse/pluginnotification/pluginnotification/",
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
        
        //var_dump($mOrderInfo);
        //exit;    
        return (object)array(
            'sysmod'      => 'j25vm2_3_0_2',
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
