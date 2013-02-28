<?php
class dibs_pw_helpers_cms extends vmPSPlugin {
    protected $method_obj = null;
    
    function cms_dibs_get_tax_item($aTaxes) {
        $fTax = 0;
        foreach($aTaxes as $aTax) {
            $fTax += $aTax[1];
        }
        
        return $fTax;
    }
    
    function cms_dibs_get_tax_shipping($aCart) {
        if(isset($aCart['shipmentTax']) && isset($aCart['shipmentValue']) && 
                $aCart['shipmentTax']>0 && $aCart['shipmentValue'] > 0) {
            return ($aCart['shipmentTax'] / $aCart['shipmentValue']) * 100;
        }
        else return (int)0;
    }
    
    function cms_dibs_get_discounts($aDATax, $fQty, &$aDiscounts) {
        foreach($aDATax as $aDiscount) {
            $aDiscounts[] = (object)array(
                'name'  => $aDiscount[0],
                'qty'   => $fQty,
                'price' => $aDiscount[1],
            );
        }
    }
    
    function cms_dibs_get_currency($iInnerId) {
        return $this->helper_dibs_db_read_single("SELECT `currency_code_3` 
                                    FROM `" . $this->helper_dibs_tools_prefix() . 
                                          "currencies` 
                                    WHERE `virtuemart_currency_id`='" . $iInnerId . "'
                                    LIMIT 1;", 'currency_code_3');
    }
}
?>
