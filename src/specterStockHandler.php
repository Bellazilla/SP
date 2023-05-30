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

use Configuration;
use Logger;
use SimpleXMLElement;
use StockAvailable;
use Db;
use Shop;
use Prestaworks\Module\Specter\SpecterConnector;

class SpecterStockHandler
{
    public static function handleStock()
    {
        $SPECTER_USEMULTISTOCK = utf8_decode(Configuration::get('SPECTER_USEMULTISTOCK'));
        if (1 == (int) $SPECTER_USEMULTISTOCK) {
            self::StockSyncMultiStock();
        } else {
            self::StockSync();
        }
        self::SetStockQuantity();
    }

    private static function SetStockQuantity()
    {
        $sql = 'SELECT * FROM '._DB_PREFIX_.'specterdata WHERE `type`=1;';
        $products_to_update = Db::getInstance()->ExecuteS($sql);
        $shops = Shop::getShops(true,null,true);
        foreach($products_to_update AS $product_to_update)
        {
            set_time_limit(5);
            self::SaveStockData($product_to_update['article_number'], $product_to_update['in_stock'], $shops);
            Db::getInstance()->Execute('DELETE FROM '._DB_PREFIX_.'specterdata WHERE id_specter_data='.$product_to_update['id_specter_data'].' LIMIT 1;');
        }
    }

    private static function SaveStockData($refno, $newamount, $shops)
    {
        //GET SQL USING SPECTERID, IF id_product then get product else if id_product_attribute then get attribute
        $results = Db::getInstance()->executeS('SELECT id_product, id_product_attribute FROM '._DB_PREFIX_.'product_attribute WHERE reference=\''.$refno.'\'');
        foreach($results as $result) {
            foreach($shops as $shop)
            {
                StockAvailable::setQuantity((int)($result['id_product']), (int)($result['id_product_attribute']), (int)($newamount), $shop);
                $total_quantity = (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
                    SELECT SUM(quantity) as quantity
                    FROM '._DB_PREFIX_.'stock_available
                    WHERE id_product = '.(int)($result['id_product']).'
                    AND id_product_attribute <> 0 '.
                    StockAvailable::addSqlShopRestriction(null, $shop)
                );

                StockAvailable::setQuantity((int)($result['id_product']), 0, $total_quantity, $shop);
            }
        }
        $results = Db::getInstance()->executeS('SELECT id_product FROM '._DB_PREFIX_.'product WHERE reference=\''.$refno.'\'');
        foreach($results as $result) {
            foreach($shops as $shop) {
                StockAvailable::setQuantity((int)($result['id_product']), null, (int)($newamount), $shop);
            }
        }
    }

    private static function StockSyncMultiStock()
    {
        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID'));
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN'));
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));

        $batchNo = md5(microtime());
        $sql_insert = '';
        //Request batchcall for update products

        $md5key = md5($SPECTER_API_USERKEY.$sbmId.$batchNo);
        $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
        
        
        $xmlString = SpecterConnector::getDataFromSpecter('getInfo.asp?Action=getArticleExternalNeedUpdate&getMultipleStocksites=1&sbmId='.$sbmId.'&useXML=2&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID);

        // $xml = new SimpleXMLElement($xmlString , 0, true);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if (isset($xml->APIresponse) && isset($xml->APIresponse->code) && $xml->APIresponse->code == '99') {
            $error_message = $xml->APIresponse->message;
            if (strpos($error_message, "'") !== false) {
                $old_batch = explode("'", $error_message);
                $batchNo = $old_batch[1];
                $md5key = MD5($SPECTER_API_USERKEY . $sbmId . $batchNo);
                $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
                $xmlString = SpecterConnector::getDataFromSpecter('getInfo.asp?Action=getArticleExternalNeedUpdate&sbmId='.$sbmId.'&getMultipleStocksites=1&useXML=2&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID);
                // $xml = new SimpleXMLElement($xmlString , 0, true);
                $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
                Logger::addLog('SBM: stock sync reload batch: '. $batchNo, 1, NULL, 'specter', 0, true);
            }
        }
    
        $stocks = array();    
        foreach ($xml->stockChanges as $stock) {
            foreach ($stock->stock as $stockdata) {
            foreach ($stockdata->articles as $articles) {
                foreach ($articles->article as $article) {
                    $articleNo = (string) $article->articleNo;
                    $noItemsAv = (string) $article->noItemsAv;
                    if ('noItemsAvailable'== $noItemsAv) {
                        $noItemsAv = 0;
                    }
                    if (isset($stocks[$articleNo])) {
                        $stocks[$articleNo] += (int) $noItemsAv;
                    } else {
                        $stocks[$articleNo] = (int) $noItemsAv;
                    }
                }
            }
            }
        }

        foreach ($stocks AS $refno => $newAmount) {
            if("" != $refno) {
                $sql_insert .= "(1, '$refno', $newAmount),";
            }
        }

        $sql_insert = trim($sql_insert,',');
        if($sql_insert!='')
        {
            $sql_insert = 'INSERT INTO '._DB_PREFIX_.'specterdata (`type`, `article_number`, `in_stock`) VALUES'. $sql_insert;
            Db::getInstance()->Execute($sql_insert);
            //Tell Specter stock is recieved only if saved
            $url='getInfo.asp?Action=updateArticlesFromBatch&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID;
            $content = SpecterConnector::getDataFromSpecter($url);
        }
    }

    private static function StockSync()
    {
        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID'));
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN'));
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));

        $batchNo = md5(microtime());
        $sql_insert = '';
        //Request batchcall for update products

        $md5key = md5($SPECTER_API_USERKEY.$sbmId.$batchNo);
        $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
        
        $url = 'getInfo.asp?Action=getArticleExternalNeedUpdate&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID;
        $data = SpecterConnector::getDataFromSpecter($url);

        if (strpos($data, "0;99;") !== false) {
            $bad_batch = explode("'", $data);
            $batchNo = $bad_batch[1];
            $md5key = MD5($SPECTER_API_USERKEY . $sbmId . $batchNo);
            $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
            $url = 'getInfo.asp?Action=getArticleExternalNeedUpdate&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID;
            $data = SpecterConnector::getDataFromSpecter($url);
            Logger::addLog('SBM: stock sync reload batch: '. $batchNo, 1, NULL, 'specter', 0, true);
        }
        
        $data = utf8_encode($data);
        $dataarray = explode(chr(13), $data);

        foreach($dataarray AS $datarow)
        {
            if (strpos($datarow,';') !== false)
            {
                @list($specterid, $refno, $newAmount, $batchid) = explode(';', $datarow);
                if($refno!='' AND $newAmount!='noItemsAvailable'){
                    $newAmount = (int)$newAmount;
                    $sql_insert .= "(1, '$refno', $newAmount),";
                }
            }
        }
        $sql_insert = trim($sql_insert,',');
        if($sql_insert!='')
        {
            $sql_insert = 'INSERT INTO '._DB_PREFIX_.'specterdata (`type`, `article_number`, `in_stock`) VALUES'. $sql_insert;
            Db::getInstance()->Execute($sql_insert);
            //Tell Specter stock is recieved only if saved
            $url = 'getInfo.asp?Action=updateArticlesFromBatch&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&apiUserId='.$SPECTER_API_USERID;
            $content = SpecterConnector::getDataFromSpecter($url);
        }
    }
}
