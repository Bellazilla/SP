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
use Prestaworks\Module\Specter\SpecterInvoiceHandler;
use Prestaworks\Module\Specter\SpecterHandler;
use Prestaworks\Module\Specter\SpecterStockHandler;
use Prestaworks\Module\Specter\SpecterProductHandler;

class specterSpecterCronModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();

        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID'));
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN'));
        $SPECTER_USEMULTISTOCK = utf8_decode(Configuration::get('SPECTER_USEMULTISTOCK'));

        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));

        $secret_key = Tools::hash('SPECTER_SBMID'.Configuration::get('SPECTER_SBMID'));
        $cron_key = Tools::getValue('key');

        if ($secret_key != $cron_key) {
            exit;
        }

        $calls_to_run = SpecterHandler::getCallsToMake();

        if (in_array(SpecterHandler::cancelorder_calltype, $calls_to_run)) {
            SpecterOrderHandler::cancelOrders();
            SpecterHandler::removeCalls(SpecterHandler::cancelorder_calltype);
        }
        if (in_array(SpecterHandler::orderupdate_calltype, $calls_to_run)) {
            $this->UpdateOrderData($sbmId, $SPECTER_API_USERKEY, $PRESTAWORKS_USERTOKEN, $SPECTER_API_USERID);
            SpecterOrderHandler::shipOrders();
            SpecterOrderHandler::cancelOrders();
            SpecterHandler::removeCalls(SpecterHandler::orderupdate_calltype);
        }
        if (in_array(SpecterHandler::invoice_calltype, $calls_to_run)) {
            SpecterInvoiceHandler::setInvoiceNumbersOnOrders();
            SpecterHandler::removeCalls(SpecterHandler::invoice_calltype);
        }
        if (in_array(SpecterHandler::articleupdate_calltype, $calls_to_run)) {   
            if (false == SpecterProductHandler::UpdateProductData()) {
                SpecterHandler::removeCalls(SpecterHandler::articleupdate_calltype);
            }
            SpecterHandler::removeCalls(SpecterHandler::articleupdate_calltype);
            SpecterProductHandler::ProcessProductChanges();
        }
        if (in_array(SpecterHandler::inventory_calltype, $calls_to_run)) {
            SpecterStockHandler::handleStock();
            SpecterHandler::removeCalls(SpecterHandler::inventory_calltype);
        }
        
        SpecterOrderHandler::sendUnsentOrdersToSBM();
        SpecterProductHandler::ProcessProductChanges();
        echo "Done";
        exit;
    }

    private function UpdateOrderData($sbmId, $SPECTER_API_USERKEY, $PRESTAWORKS_USERTOKEN, $SPECTER_API_USERID) {
        $batchNo = md5(microtime());
        $md5key = MD5($SPECTER_API_USERKEY . $sbmId . $batchNo);
        $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
                    
        $xml_string = SpecterConnector::getDataFromSpecter('getInfo.asp?Action=getOrdersExternalNeedUpdate&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&useXML=2'.'&apiUserId='.$SPECTER_API_USERID);
        $xml = new SimpleXMLElement($xml_string , 0);
        
        if (isset($xml->APIresponse) && isset($xml->APIresponse->code) && $xml->APIresponse->code == '99') {
            $error_message = $xml->APIresponse->message;
            if (strpos($error_message, "'") !== false) {
                $bad_batch = explode("'", $error_message);
                $batchNo = $bad_batch[1];
                $md5key = MD5($SPECTER_API_USERKEY . $sbmId . $batchNo);
                $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
                //Close the old batch
                $url='getInfo.asp?Action=updateOrdersFromBatch&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID;
                SpecterConnector::getDataFromSpecter($url);
                Logger::addLog('SBM: Batch problem ordrar', 1, NULL, NULL, NULL, true);
                exit;
            }
        }
        $sql_values = '';
        $has_delete_orders = false;
        foreach($xml->orders->order as $order)
        {
            $id_specter = 0;
            $tracking_number = '';
            
            foreach($order->attributes() AS $attribute=>$value)
                if($attribute=='orderNo')
                    $id_specter = (int)$value;

            $deliver = false;
            $delete = false;
            foreach($order->orderstatus->attributes() AS $attribute=>$value)
            {
                if($attribute=='deliveryStatus')
                    if($value=='D')
                        $deliver = true;
                if($attribute=='statusId')
                    if($value=='5')
                        $delete = true;
            }
            
            foreach($order->deliveries as $deliveries) {
                foreach($deliveries->attributes() AS $attribute=>$value) {
                    if($attribute=='updated') {
                        if($value=='1') {
                            $deliver = true;
                        }
                    }
                }
            }
            
            foreach($order->deliveries->delivery AS $delivery) {
                foreach($delivery->attributes() AS $attribute=>$value) {
                    if($attribute=='trackingNo') {
                        if($value!='') {
                            $tracking_number = $value;
                        }
                    }
                }
            }
            if($delete) {
                $sql_values .= "(3, '$id_specter', ''),";
                $has_delete_orders = true;
            } elseif($deliver) {
                $sql_values .= "(4, '$id_specter', '$tracking_number'),";
            }
        }

        $sql_values = trim($sql_values, ',');
        if($sql_values=='') {
            Logger::addLog('SBM: Batch ordrar nothing todo.', 1, NULL, NULL, NULL, true);
            $url='getInfo.asp?Action=updateOrdersFromBatch&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID;
            SpecterConnector::getDataFromSpecter($url);
            return;
        }
        $sql_insert = 'INSERT INTO '._DB_PREFIX_.'specterdata (`type`, `specter_order_id`, `tracking_number`) VALUES'.$sql_values;

        Db::getInstance()->execute($sql_insert);
        $url='getInfo.asp?Action=updateOrdersFromBatch&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID;
        SpecterConnector::getDataFromSpecter($url);
        
    }
}