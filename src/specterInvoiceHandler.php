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
use OrderHistory;
use Validate;
use Configuration;
use Carrier;
use Order;

use Prestaworks\Module\Specter\SpecterHandler;

class SpecterInvoiceHandler
{
    public static function setInvoiceNumbersOnOrders()
    {
        $sql = 'SELECT * FROM '._DB_PREFIX_.'specterdata WHERE `type`=2;';
        $orders_to_update = Db::getInstance()->executeS($sql);
        foreach($orders_to_update AS $order_to_update)
        {
            $id_specter_order = $order_to_update["specter_order_id"];
            $id_specter_invoice = $order_to_update["invoice_id"];
            self::setInvoiceNumber($id_specter_order, $id_specter_invoice);
            SpecterHandler::removeData($order_to_update["id_specter_data"]);
            self::changeStatus($id_specter_order);
        }
    }

    public static function setInvoiceNumber($id_specter_order, $id_specter_invoice)
    {
        $result = Db::getInstance()->update('specterorders', array(
            'id_specter_invoice' => pSQL($id_specter_invoice),
        ), 'id_specter_order = '.pSQL($id_specter_order), 1);
    }

    private static function changeStatus($id_specter_order)
    {
        $result = Db::getInstance()->getRow('SELECT id_order FROM '._DB_PREFIX_.'specterorders WHERE id_specter_order='.(int) $id_specter_order);
        $id_order = (int) $result['id_order'];
        //UPDATE ORDER STATUS
        $order = new Order($id_order);
        if(Validate::isLoadedObject($order))
        {
            $history = new OrderHistory();
            $history->id_order = $id_order;
            $id_order_state = intval(Configuration::get('SPECTER_STATEID'));
            $history->id_order_state = $id_order_state;
            $history->changeIdOrderState($id_order_state, $id_order, true);
            $id_carrier = intval($order->id_carrier);
            $id_lang = intval($order->id_lang);
            $carrier = new Carrier($id_carrier, $id_lang);
            $templateVars = array('{followup}' => ($history->id_order_state == _PS_OS_SHIPPING_ AND $order->shipping_number) ? str_replace('@', $order->shipping_number, $carrier->url) : '');
            
            $history->addWithemail(true, $templateVars);
        }
    }
}
