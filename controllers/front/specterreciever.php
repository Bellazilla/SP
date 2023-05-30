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

use Prestaworks\Module\Specter\SpecterConnector;
use Prestaworks\Module\Specter\SpecterOrderHandler;
use Prestaworks\Module\Specter\SpecterHandler;

class specterSpecterRecieverModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();

        $md5key1 = utf8_decode(Configuration::get('SPECTER_MD5_1'));
        $md5key2 = utf8_decode(Configuration::get('SPECTER_MD5_2'));
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));
        $PRESTAWORKS_USERTOKEN = Configuration::get('PRESTAWORKS_USERTOKEN');

        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN'));
        $pskey = Tools::hash($PRESTAWORKS_USERTOKEN);

        $external_pskey = Tools::getValue('pskey');
        $call_type = (int) Tools::getValue('calltype');

        if ($external_pskey != $pskey) {
            echo "1;Done";
            exit;
        }

        $accept_call_type = SpecterHandler::checkCallType($call_type);
        if ((int) $accept_call_type==0) {
            echo "1;Done";
            exit;
        }
        
        Db::getInstance()->insert('spectercalls', array(
            'type' => $call_type,
        ));
        
        if (3 == (int)Tools::getValue('calltype')) {
            //Delete order, ordernumber is sent with callback so we need a bit of special code here.
            $id_specter_order = Tools::getValue('orderNo');
            $result = Db::getInstance()->insert('specterdata', array(
                'type' => 3,
                'specter_order_id' => pSQL($id_specter_order),
            ));
            
        } elseif (2 == (int) Tools::getValue('calltype')) {
            //New invoice created, ordernumber is sent with callback so we need a bit of special code here.
            $id_specter_order = Tools::getValue('orderId');
            $id_specter_invoice = Tools::getValue('invoiceId');
            $result = Db::getInstance()->insert('specterdata', array(
                'type' => 2,
                'specter_order_id' => pSQL($id_specter_order),
                'invoice_id' => pSQL($id_specter_invoice),
            ));
        }
        echo "1;Done";
        exit;
    }

}