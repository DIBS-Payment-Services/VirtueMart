<?php
class dibs_fw_helpers_cms extends vmPSPlugin {   

	function cms_dibs_get_tax_item($aTaxes) {
        $fTax = 0;
        foreach($aTaxes as $aTax) {
            $fTax += $aTax[1];
        }
        
        return $fTax;
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
