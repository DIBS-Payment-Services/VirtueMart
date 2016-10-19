<?php
defined('_JEXEC') or die();
?>
<?php if($viewData['responseParams']['status'] == "ACCEPTED" || 
         $viewData['responseParams']['status'] == "PENDING") { ?>
<table>
    <tr>
        <td  valign="top" style="width: 181px;"><?php echo vmText::_('VMPAYMENT_DIBSPW_METHODNAME');  ?>
            <img alt="" src="http://tech.dibspayment.com/sites/tech/files/pictures/LOGO/DIBS/DIBS_By_NETS_logo_Blue_PREWIEW.png" 
                 style="width: 140px; height: 70px; float: left;">
        </td>
        <td  valign="top"><?php echo $viewData['payment_name'];  ?></td>
    </tr>
	<tr>
            <td><?php echo vmText::_('VMPAYMENT_DIBSPW_ORDERNUMBER'); ?></td>
        <td><b><?php echo $viewData["order"]['details']['BT']->order_number; ?></b></td>
    </tr>
	<?php if (true) { ?>
	<tr>
        <td><?php echo vmText::_('VMPAYMENT_DIBSPW_ORDER_AMOUNT'); ?></td>
        <td><b><?php echo $viewData['totalInPaymentCurrency']; ?></b></td>
    </tr>
	<tr>
        <td><?php echo vmText::_('VMPAYMENT_DIBSPW_TRANSACTION'); ?></td>
        <td><b><?php echo $viewData['responseParams']['transaction']; ?></b></td>
    </tr>
    <?php }  ?>
</table>
<br />
<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$viewData["order"]['details']['BT']->order_number.'&order_pass='.$viewData["order"]['details']['BT']->order_pass, false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>
<br />
<?php }