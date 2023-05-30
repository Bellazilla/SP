<?php
/**
 * Prestaworks AB
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the End User License Agreement(EULA)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://license.prestaworks.se/license.html
 *
 * @author    Prestaworks AB <info@prestaworks.se>
 * @copyright Copyright Prestaworks AB (https://www.prestaworks.se/)
 * @license   http://license.prestaworks.se/license.html
 */

 use Prestaworks\Module\Specter\SpecterOrderHelper;
 use Prestaworks\Module\Specter\SpecterCustomerHelper;
 use Prestaworks\Module\Specter\SpecterProductHandler;

class AdminSpecterAjaxController extends ModuleAdminController
{
    public function displayAjaxSendProducts() {
        $limitStart = ((int) Tools::getValue('Call')-1) * 100;
		if (Tools::getIsset("attributes")) {
            $result = SpecterProductHandler::SendProductAttributesToSpecter($limitStart);
        } else {
            $result = SpecterProductHandler::SendProducts($limitStart);
        }
        $this->ajaxDie(Tools::JsonEncode($result));
    }

    public function displayAjaxSendProduct() {
        $id_product = (int) Tools::getValue('id_product');
		$results[] = SpecterProductHandler::SendProductAttributesToSpecter(0, $id_product);
		$results[] = SpecterProductHandler::SendProducts(0, $id_product);
        $this->context->smarty->assign('results', $results);
        $tpl = $this->createTemplate('../../../../modules/specter/views/templates/admin/specter_product_result.tpl');
        $this->ajaxDie($tpl->fetch());
    }

    public function displayAjaxGetOrderInfo() {
        $id_order = (int) Tools::getValue("id_order");
        $tpl = false;

        if ($id_order > 0) {
            $order = new Order($id_order);
            $customer = new Customer($order->id_customer);
            $currency = new Currency($order->id_currency);
            $invoiceaddress = new Address($order->id_address_invoice);
            $deliveryaddress = new Address($order->id_address_delivery);
            
            $customerNumber = "-1";
            $wrappingtranslation = "trans";
            $discounttranslation = "trans";
            $orderdata = SpecterOrderHelper::buildOrderData($order, $customerNumber, $wrappingtranslation, $discounttranslation);
            $customerdata = SpecterCustomerHelper::buildCustomerData($customer, $currency, $invoiceaddress, $deliveryaddress, $order->id_lang, $order->id_shop);
            $this->context->smarty->assign(array(
                'orderdata' => $orderdata,
                'customerdata' => $customerdata,
            ));
            $tpl = $this->createTemplate('../../../../modules/specter/views/templates/admin/specter_orderdatamodal.tpl');
        }
        

        $this->ajaxDie($tpl->fetch());
    }
    
}