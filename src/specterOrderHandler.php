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

namespace Prestaworks\Module\Specter;

use Db;
use Validate;
use Order;
use Configuration;
use OrderHistory;
use Context;
use Logger;
use Customer;
use Address;
use Hook;
use Currency;
use Carrier;
use OrderCarrier;
use SimpleXMLElement;

use Prestaworks\Module\Specter\SpecterHandler;
use Prestaworks\Module\Specter\SpecterConnector;
use Prestaworks\Module\Specter\SpecterOrderHelper;

class SpecterOrderHandler
{
    public static function cancelOrders()
    {
        $sql = 'SELECT * FROM '._DB_PREFIX_.'specterdata WHERE `type`=3;';
        $orders_to_update = Db::getInstance()->executeS($sql);
        foreach($orders_to_update AS $order_to_update)
        {
            $id_specter = $order_to_update["specter_order_id"];
            self::cancelOrder($id_specter);
            SpecterHandler::removeData($order_to_update["id_specter_data"]);
        }
    }

    public static function shipOrders()
    {
        $sql = 'SELECT * FROM '._DB_PREFIX_.'specterdata WHERE `type`=4;';
        $orders_to_update = Db::getInstance()->executeS($sql);
        foreach($orders_to_update AS $order_to_update)
        {
            $id_specter = $order_to_update["specter_order_id"];
            $tracking_number = $order_to_update["tracking_number"];
            self::shipOrder($id_specter, $tracking_number);
            SpecterHandler::removeData($order_to_update["id_specter_data"]);
        }
    }

    //SHIP ORDER
    public static function shipOrder($id_specter, $shipping_number)
    {
        $result = Db::getInstance()->getRow('SELECT id_order FROM '._DB_PREFIX_.'specterorders WHERE id_specter_order='.(int) $id_specter);
        if (false == $result) {
            return false;
        }
        
        $id_order = (int) $result['id_order'];

        if(!defined(_PS_OS_SHIPPING_)) {
            $shipping_state =Configuration::get('PS_OS_SHIPPING');
        } else {
            $shipping_state = _PS_OS_SHIPPING_;
        }
        
        $shipping_number = preg_replace('/[^~:#,%&_=\(\)\[\]\.\? \+\-@\/a-zA-Z0-9]/', '', $shipping_number);
        $order = new Order($id_order);
        $shipping_set = false;
        if ($order->hasBeenShipped()>0)
            $shipping_set = true;
            
        if($shipping_number!='')
        {
            if ($order->hasBeenShipped()==0)
            {
                $history = new OrderHistory();
                $history->id_order = $id_order;
                $history->id_order_state = $shipping_state;
                $history->changeIdOrderState($shipping_state, $id_order, true);
                $carrier = new Carrier((int)($order->id_carrier), (int)($order->id_lang));
                $templateVars = array();
                $templateVars = array('{followup}' => str_replace('@', $order->shipping_number, $carrier->url));
                if ($history->addWithemail(true, $templateVars)) {
                    $shipping_set = true;
                }
            }
            if($order->shipping_number=='')
            {
                //SET SHIPPING NUMBER
                $shipping_number = pSQL($shipping_number);
                $order = new Order((int)$order->id);
                $order->shipping_number = $shipping_number;
                $order->update();
                if ($shipping_number != "") {
                    $customer = new Customer((int)($order->id_customer));
                    $carrier = new Carrier((int) $order->id_carrier, $order->id_lang);
                    
                    $order_carrier_ids = Db::getInstance()->executeS('
                        SELECT `id_order_carrier`
                        FROM `'._DB_PREFIX_.'order_carrier`
                        WHERE `id_order` = '.$id_order);
                    if($order_carrier_ids)
                    {
                        foreach($order_carrier_ids as $order_carrier_id)
                        {
                            $id_order_carrier = (int)($order_carrier_id['id_order_carrier']);
                            $order_carrier = new OrderCarrier($id_order_carrier);
                            if (Validate::isLoadedObject($order_carrier))
                            {
                                $order_carrier->tracking_number = $shipping_number;
                                $order_carrier->update();
                                Hook::exec('actionAdminOrdersTrackingNumberUpdate', array('order' => $order));
                            }
                        }
                    }
                    
                    if (!Validate::isLoadedObject($customer) OR !Validate::isLoadedObject($carrier))
                        return false;
                    
                    $address = new Address((int) $order->id_address_delivery);
                    $orderLanguage = new Language($order->id_lang);
                    $translator = Context::getContext()->getTranslator();
                    
                    $metadata = '';
                    $products = $order->getCartProducts();
                    $link = Context::getContext()->link;
                    foreach ($products as $product) {
                        $prod_obj = new Product((int) $product['product_id']);
    
                        //try to get the first image for the purchased combination
                        $img = $prod_obj->getCombinationImages($order->id_lang);
                        $link_rewrite = $prod_obj->link_rewrite[$order->id_lang];
                        $combination_img = $img[$product['product_attribute_id']][0]['id_image'];
                        if ($combination_img != null) {
                            $img_url = $link->getImageLink($link_rewrite, $combination_img, 'large_default');
                        } else {
                            //if there is no combination image, then get the product cover instead
                            $img = $prod_obj->getCover($prod_obj->id);
                            $img_url = $link->getImageLink($link_rewrite, $img['id_image']);
                        }
                        $prod_url = $prod_obj->getLink();
    
                        $metadata .= "\n" . '<div itemprop="itemShipped" itemscope itemtype="http://schema.org/Product">';
                        $metadata .= "\n" . '   <meta itemprop="name" content="' . htmlspecialchars($product['product_name']) . '"/>';
                        $metadata .= "\n" . '   <link itemprop="image" href="' . $img_url . '"/>';
                        $metadata .= "\n" . '   <link itemprop="url" href="' . $prod_url . '"/>';
                        $metadata .= "\n" . '</div>';
                    }
            
                    
                    $templateVars = array(
                        '{followup}' => str_replace('@', $shipping_number, $carrier->url),
                        '{firstname}' => $customer->firstname,
                        '{lastname}' => $customer->lastname,
                        '{id_order}' => $order->id,
                        '{shipping_number}' => $shipping_number,
                        '{order_name}' => $order->getUniqReference(),
                        '{carrier}' => $carrier->name,
                        '{address1}' => $address->address1,
                        '{country}' => $address->country,
                        '{postcode}' => $address->postcode,
                        '{city}' => $address->city,
                        '{meta_products}' => $metadata,
                    );
                    Mail::Send(
                        (int) $order->id_lang,
                        'in_transit',
                        $translator->trans(
                            'Package in transit',
                            array(),
                            'Emails.Subject',
                            $orderLanguage->locale
                        ),
                        $templateVars,
                        $customer->email,
                        $customer->firstname . ' ' . $customer->lastname,
                        null,
                        null,
                        null,
                        null,
                        _PS_MAIL_DIR_,
                        true,
                        (int) $order->id_shop
                    );
                }
            }
        }
        
        if(!$shipping_set)
        {
            $history = new OrderHistory();
            $history->id_order = (int)$id_order;
            $history->shipping_state = (int)$shipping_state;
            $history->changeIdOrderState((int)($shipping_state), $id_order, true);
            $carrier = new Carrier((int)($order->id_carrier), (int)($order->id_lang));
            $templateVars = array();
            if ($history->id_order_state == _PS_OS_SHIPPING_ AND $order->shipping_number)
                $templateVars = array('{followup}' => str_replace('@', $order->shipping_number, $carrier->url));
            $history->addWithemail(true, $templateVars);
        }
    }

    //CANCEL ORDER AND REMOVE SPECTER ID
    public static function cancelOrder($id_specter)
    {
        $result = Db::getInstance()->getRow('SELECT id_order FROM '._DB_PREFIX_.'specterorders WHERE id_specter_order='.(int) $id_specter);
        $id_order = (int) $result['id_order'];
        if ($id_order > 0) {
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'specterorders WHERE id_order='.(int) $id_order);
            //UPDATE ORDER STATUS
            $order = new Order($id_order);
            $id_shop = $order->id_shop;
            if(Validate::isLoadedObject($order)) {
                $history = new OrderHistory();
                $history->id_order = $id_order;
                $cancel_status = (int) Configuration::get('PS_OS_CANCELED', null, null, $id_shop);
                if (0 == $cancel_status) {
                    $cancel_status = (int) _PS_OS_CANCELED_;
                }
                $history->changeIdOrderState((int) $cancel_status, $id_order, true);
                $history->addWithemail(true, null);
            }
        }
    }

    public static function queOrderForSpecter($id_order)
    {
        $id_order = (int) $id_order;
        if (0 == $id_order) {
            return false;
        }
        $sql_insert = "INSERT INTO `"._DB_PREFIX_."specterorderstosend` (id_order) VALUES ($id_order);";
        return Db::getInstance()->execute($sql_insert);
    }

    public static function sendUnsentOrdersToSBM()
    {
        $sql_orders = "SELECT id_order FROM `"._DB_PREFIX_."specterorderstosend`;";
        $orders_to_send = Db::getInstance()->executeS($sql_orders);
        foreach ($orders_to_send as $order_to_send) {
            self::sendOrderToSBM((int) $order_to_send["id_order"]);
        }
    }

    public static function sendOrderToSBM($id_order)
    {
        $order = new Order($id_order);
        $customer = new Customer($order->id_customer);
        $currency = new Currency($order->id_currency);
        $invoiceaddress = new Address($order->id_address_invoice);
        $deliveryaddress = new Address($order->id_address_delivery);
        $id_lang = $order->id_lang;
        $id_shop = $order->id_shop;
        $specter_Customer_Id = SpecterCustomerHandler::CreateCustomer($customer, $currency, $invoiceaddress, $deliveryaddress, $id_lang, $id_shop);
        if (false == $specter_Customer_Id) {
            return false;
        }
        
        $wrappingtranslation = Context::getContext()->getTranslator()->trans('Wrapping', [], 'Prestaworks.Module.Specter');
        $discounttranslation = Context::getContext()->getTranslator()->trans('Discount', [], 'Prestaworks.Module.Specter');
        $orderdata = SpecterOrderHelper::buildOrderData($order, $specter_Customer_Id, $wrappingtranslation, $discounttranslation);

        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID', null, null, $id_shop));
        $shopid = utf8_decode(Configuration::get('SPECTER_SHOP_ID_'.$order->id_shop, null, null, $id_shop));

        $endpoint = 'putInfo.asp?action=newOrderSubmit';
        $endpoint .= '&sbmId='.$sbmId;
        $endpoint .= '&apiUserId='.$SPECTER_API_USERID;
        $endpoint .= '&useXML=1';
        if ($shopid!='') {
            $endpoint .= '&webshopId='.$shopid;
        }
            
        $contents = SpecterConnector::postDataToSpecter($endpoint, $orderdata);
        list($result, $id, $other) = explode(';', $contents);
        if(1 == (int) $result) {
            Db::getInstance()->execute('INSERT INTO  '._DB_PREFIX_.'specterorders (id_order, id_specter_order)
             VALUES('.$order->id.', '.$id.')
             ON DUPLICATE KEY UPDATE id_specter_order='.$id);
            $sql_delete = "DELETE FROM `"._DB_PREFIX_."specterorderstosend` WHERE id_order=".(int) $order->id;
            Db::getInstance()->execute($sql_delete);
            return true;
        } else {
            Logger::addLog('SBM Response:'.$contents, 3, null, null, null, true);
            return $contents;
        }
    }

    
}
