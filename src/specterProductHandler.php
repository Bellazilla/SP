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
use Tax;
use Db;
use Tools;
use Language;
use Currency;
use Product;
use Logger;
use Shop;
use StockAvailable;
use Context;
use Country;
use Prestaworks\Module\Specter\SpecterConnector;

class SpecterProductHandler
{
    public static function saveArticleData($data)
    {
        $data = pSQL($data, true);
        $sql_insert = "INSERT INTO "._DB_PREFIX_."specterdata (`type`, `product_data`) VALUES(5, '$data');";
        Db::getInstance()->execute($sql_insert);		   
    }

    public static function saveArticleToUpdateQue($id_product)
    {
        $id_product = (int) $id_product;
        $sql_insert = "INSERT INTO "._DB_PREFIX_."specterproductstoupdate (`id_product`) VALUES($id_product);";
        Db::getInstance()->execute($sql_insert);		   
    }

    public static function ProcessProductChanges()
    {
        $sql = 'SELECT * FROM '._DB_PREFIX_.'specterdata WHERE `type`=5 ORDER BY id_specter_data ASC LIMIT 500';
        $products_to_update = Db::getInstance()->executeS($sql);
        $shops = Shop::getShops(true,null,true);
        $id_lang = Language::getIdByIso('sv');
        foreach($products_to_update AS $product_to_update) {
            set_time_limit(10);
            $product_data = str_replace(PHP_EOL, '',$product_to_update['product_data']);
            $dataArray = json_decode($product_data);
            
            $articleNo = $dataArray->articleNo;
            $buyPrice = $dataArray->{'@attributes'}->buyPrice;
            $priceExclVat = $dataArray->{'@attributes'}->priceExclVat;
            $ean13 = $dataArray->{'@attributes'}->barCode;
            $productWeight = $dataArray->{'@attributes'}->productWeight;
            $name = $dataArray->name;
        
            self::SetProductData($articleNo, $shops, $priceExclVat, $buyPrice, $ean13, $productWeight, $name, $id_lang);
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'specterdata WHERE id_specter_data='.$product_to_update['id_specter_data'].' LIMIT 1;');
        }
    }

    private static function SetProductData($refno, $shops, $newprice, $buyPrice, $ean13, $productWeight, $name, $id_lang)
    {
        $sql = "SELECT `id_product` FROM "._DB_PREFIX_."product WHERE `reference`='$refno'";
        $newprice = str_replace(',','.', $newprice);
        $buyPrice = str_replace(',','.', $buyPrice);
        $productWeight = str_replace(',','.', $productWeight);
        $product_to_update = Db::getInstance()->getValue($sql);
        if($product_to_update > 0) {
            $sqlupdate = "UPDATE "._DB_PREFIX_."product SET `price`=$newprice, `wholesale_price`=$buyPrice, `ean13`='$ean13', `weight`=$productWeight WHERE `id_product`='$product_to_update'";
            Db::getInstance()->execute($sqlupdate);
            foreach($shops as $id_shop => $shop)
            {
                $sqlupdate = "UPDATE "._DB_PREFIX_."product_shop SET `price`=$newprice, `wholesale_price`=$buyPrice WHERE `id_product`='$product_to_update' AND id_shop=$id_shop";
                Db::getInstance()->execute($sqlupdate);
            }
            $sqlupdate = "UPDATE "._DB_PREFIX_."ps_product_supplier SET `product_supplier_price_te`=$buyPrice WHERE `id_product_attribute`= 0";
            Db::getInstance()->execute($sqlupdate);
            if ($id_lang > 0) {
                $sqlupdate = "UPDATE "._DB_PREFIX_."product_lang SET `name`='$name' WHERE `id_product`='$product_to_update' AND id_lang=$id_lang";
                Db::getInstance()->execute($sqlupdate);
            }
        } else {
            //Not a base product, check combinations.
            
            $sql = "SELECT `id_product_attribute`, `id_product` FROM "._DB_PREFIX_."product_attribute WHERE `reference`='$refno'";
            $product_attribute_data = Db::getInstance()->getRow($sql);
            $product_attribute_to_update = (isset($product_attribute_data['id_product_attribute']) ? $product_attribute_data['id_product_attribute']:0);
            $id_product = (isset($product_attribute_data['id_product']) ? $product_attribute_data['id_product']:0);
            
            if($product_attribute_to_update > 0) {
                //Base product always have price set to 0.
                $sql_reset_price = "UPDATE "._DB_PREFIX_."product SET `price` = 0 `weight` = 0 WHERE id_product=$id_product";
                Db::getInstance()->execute($sql_reset_price);
                
                $sqlupdate = "UPDATE "._DB_PREFIX_."product_attribute SET `price`=$newprice, `ean13`='$ean13', `weight`=$productWeight, `wholesale_price`=$buyPrice WHERE `id_product_attribute`='$product_attribute_to_update'";
                Db::getInstance()->execute($sqlupdate);
                foreach($shops as $id_shop => $shop)
                {
                    $sqlupdate = "UPDATE "._DB_PREFIX_."product_shop SET `price`=0 WHERE `id_product`='$id_product' AND id_shop=$id_shop";
                    Db::getInstance()->execute($sqlupdate);
                    
                    $sqlupdate = "UPDATE "._DB_PREFIX_."product_attribute_shop SET `price`=$newprice, `wholesale_price`=$buyPrice WHERE `id_product_attribute`='$product_attribute_to_update' AND id_shop=$id_shop";
                    Db::getInstance()->execute($sqlupdate);
                    
                    $sqlupdate = "UPDATE "._DB_PREFIX_."ps_product_supplier SET `product_supplier_price_te`=$buyPrice WHERE `id_product_attribute`='$product_attribute_to_update'";
                    Db::getInstance()->execute($sqlupdate);
                }
            } else {
                //No product found
            }
        }
    }

    public static function UpdateProductData() {
        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID'));
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN'));
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));

        $batchNo = md5(microtime());
        $md5key = MD5($SPECTER_API_USERKEY . $sbmId . $batchNo);
        $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
        $url = 'getInfo.asp?Action=getArticleInfoExternalNeedUpdate&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&useXML=2'.'&apiUserId='.$SPECTER_API_USERID;
        $xmlString = SpecterConnector::getDataFromSpecter($url);
        
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (isset($xml->APIresponse) && isset($xml->APIresponse->code) && $xml->APIresponse->code == '99') {
            $error_message = $xml->APIresponse->message;
            if (strpos($error_message, "'") !== false) {
                $bad_batch = explode("'", $error_message);
                $batchNo = $bad_batch[1];
                $md5key = MD5($SPECTER_API_USERKEY . $sbmId . $batchNo);
                $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
                //close the old batch
                $url = 'getInfo.asp?Action=updateArticleInfosFromBatch&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&useXML=2&apiUserId='.$SPECTER_API_USERID;
                Logger::addLog('SBM: product changes closed batch:'. $batchNo, 1, NULL, 'specter', 0, true);
                return;
            }
        }

        $nbrOfArticles = 0;
        $responseMaxLimit = 0;
        $hasMoreArticlesToUpdate = false;
        
        foreach($xml->articles->article as $article)
        {
            $nbrOfArticles++;
            $data = json_encode($article, JSON_UNESCAPED_UNICODE);
            self::saveArticleData($data);
        }

        if (isset($xml->responseMaxLimit)) {
            $responseMaxLimit = (int) $xml->responseMaxLimit;
            if ($responseMaxLimit <= $nbrOfArticles) {
                //max limit reached, more products in que
                $hasMoreArticlesToUpdate = true;
            }
        }

        unset($xml);
        $url = 'getInfo.asp?Action=updateArticleInfosFromBatch&sbmId='.$sbmId.'&batchNo='.$batchNo.'&key='.$md5key.'&useXML=2&apiUserId='.$SPECTER_API_USERID;
        SpecterConnector::getDataFromSpecter($url);
        return $hasMoreArticlesToUpdate; 
    }

    public static function SendProductAttributesToSpecter($limitStart, $id_product = null)
    {
        $lang = (int)Tools::getValue('lang');
        $url = '';
    
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID'));
        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY'));
        
        //send all combinations
        $sql = 'SELECT p.wholesale_price as buyprice, pl.name as productname, s.name as supplier_name,p.id_product, p.id_supplier, pa.id_product_attribute, pa.reference, pa.ean13, pa.supplier_reference, pa.price, pa.quantity,pa.weight, pa.wholesale_price, p.price as baseprice, t.rate, p.weight AS baseweight, p.depth, p.height, p.width 
        FROM '._DB_PREFIX_.'product_attribute pa 
        LEFT JOIN '._DB_PREFIX_.'product p USING(id_product) 
        LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (p.`id_tax_rules_group` = tr.`id_tax_rules_group`AND tr.`id_country` = '.(int)Context::getContext()->country->id.' AND tr.`id_state` = 0) 
        LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.`id_tax` = tr.`id_tax`) 
        LEFT JOIN '._DB_PREFIX_.'product_lang pl USING(id_product) 
        LEFT JOIN '._DB_PREFIX_.'supplier s USING(id_supplier) 
        WHERE 1=1 AND pl.id_lang='.$lang.((int)$id_product>0? ' AND id_product='.$id_product:'').' ORDER BY p.id_product ASC LIMIT '.$limitStart.',100';
        $attribrows = Db::getInstance()->ExecuteS($sql);
        $productNumber=0;
        foreach($attribrows AS $row)
        {
            $productNumber++;
            $attributename = '';
            $result = Db::getInstance()->ExecuteS('SELECT id_attribute FROM '._DB_PREFIX_.'product_attribute_combination WHERE id_product_attribute='.$row['id_product_attribute']);
            
             $result = Db::getInstance()->executeS('
                SELECT pac.`id_product_attribute`, agl.`public_name` AS public_group_name, al.`name` AS attribute_name
                FROM `'._DB_PREFIX_.'product_attribute_combination` pac
                LEFT JOIN `'._DB_PREFIX_.'attribute` a ON a.`id_attribute` = pac.`id_attribute`
                LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
                LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (
                    a.`id_attribute` = al.`id_attribute`
                    AND al.`id_lang` = '.(int)$lang.'
                )
                LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (
                    ag.`id_attribute_group` = agl.`id_attribute_group`
                    AND agl.`id_lang` = '.(int)$lang.'
                )
                WHERE pac.`id_product_attribute` = '.$row['id_product_attribute'].'
                ORDER BY ag.`position` ASC, a.`position` ASC'
            );
            
            foreach($result AS $attributerow)
            {
                // $attributenamerow = Db::getInstance()->getRow('SELECT name FROM '._DB_PREFIX_.'attribute_lang WHERE id_lang='.$lang.' AND id_attribute='.$attributerow['id_attribute']);
                // $attributename .= $attributenamerow['name'] . '-';
                $attributename .= $attributerow['public_group_name'].' : '.$attributerow['attribute_name'].'- ';
            }
            if ("" != $attributename) {
                $attributename = "- ".$attributename;
            }
            $attributename = $row['productname'].' '.rtrim(rtrim($attributename),'-');
            $addcost = $row['price'];
            $attribprice = $row['baseprice'] + $addcost;
            $buyprice = $row['buyprice'];
            if($row['wholesale_price'] > 0)
                $buyprice = $row['wholesale_price'];
            
            if($productNumber==1)
            {
                // $md5key = MD5($md5key2 . MD5($md5key1 . $sbmId . utf8_decode($row['reference'])));
                $md5key = MD5($SPECTER_API_USERKEY . $sbmId . utf8_decode($row['reference']));
                $md5key = SpecterConnector::licenceCheck($sbmId, $md5key,$PRESTAWORKS_USERTOKEN);
                $url = 'putInfo.asp';
                $url .= '?action=newArticleSubmit';
                $url .= '&sbmId='.$sbmId;
                $url .= '&apiUserId='.$SPECTER_API_USERID;
                $url .= '&useXML=1';
                
                $url .= '&key='.$md5key;
                $urldata = 'forceArticleUpdate=1';
                $urldata .= '&addSupplier='. (int) Configuration::get('SPECTER_ADD_SUPPLIER');
            }
            $product_supplier_reference = $row['supplier_reference'];
            if((int)$row['id_supplier']>0)
            {
                $supplierdata = self::getSupplierInformation($row['id_supplier'], $row['id_product'], $row['id_product_attribute']);
                $buyprice = $supplierdata['product_supplier_price_te'];
                $product_supplier_reference = $supplierdata['product_supplier_reference'];
    
                $urldata .= '&supplierNo_'.$productNumber.'='. urlencode(utf8_decode($row['id_supplier']));
                $urldata .= '&supplierName_'.$productNumber.'='. urlencode(utf8_decode($row['supplier_name']));
                $urldata .= '&supplierBuyPrice_'.$productNumber.'='. urlencode(utf8_decode($buyprice));
                $urldata .= '&supplierArticleNo_'.$productNumber.'='. urlencode(utf8_decode($product_supplier_reference));

                if ((int) $supplierdata['id_country'] > 0) {
                    $country_of_origin = new Country((int) $supplierdata['id_country']);
                    $country_of_origin = $country_of_origin->iso_code;
                } else {
                    $country_of_origin = "";
                }
                if ("" != $country_of_origin) {
                    $urldata .= '&manufacturingCountryCode_'.$productNumber.'='. urlencode(utf8_decode($country_of_origin));
                }
            }
            $wholesale_price = (float)($buyprice);
            
            $urldata .= '&barCode_'.$productNumber.'='. urlencode(utf8_decode($row['ean13']));
            $urldata .= '&buyPrice_'.$productNumber.'='. urlencode(utf8_decode($wholesale_price));
            $urldata .= '&stockPrice_'.$productNumber.'='. urlencode(utf8_decode($wholesale_price));
            $urldata .= '&vatPercent_'.$productNumber.'='. urlencode(utf8_decode($row['rate']));
            $urldata .= '&productWeight_'.$productNumber.'='. urlencode(utf8_decode(number_format($row['baseweight']+$row['weight'], 3, '.', '')));
            $volume = ((float)$row['width'] /100) *  ((float)$row['height'] /100) *  ((float)$row['depth'] / 100);
            $urldata .= '&productVolume_'.$productNumber.'='. urlencode(utf8_decode(number_format($volume, 2, '.', '')));
            
            $urldata .= '&priceExclVAT_'.$productNumber.'='. urlencode(utf8_decode(number_format($attribprice, 2, '.', '')));
            $urldata .= '&createArticleStock_'.$productNumber.'=1';
            $quantity = StockAvailable::getQuantityAvailableByProduct($row['id_product'], $row['id_product_attribute']);
            $urldata .= '&noItemsInStock_'.$productNumber.'='. urlencode(utf8_decode($quantity));
            $urldata .= '&articleNo_'.$productNumber.'='. urlencode(utf8_decode($row['reference']));
            $urldata .= '&name_'.$productNumber.'='. urlencode(utf8_decode(strip_tags($attributename)));
        }
        //Send attributeproducts
        //$totalSent = $totalSent+$productNumber;
        $contents = '0;0;0';
        if ($urldata != '') {
            $contents = SpecterConnector::postDataToSpecter($url, $urldata);
            list($result, $id, $other) = explode(';', $contents);
            if($result == '1') {
                return [
                    'success' => true,
                    'result' => $result,
                    'id' => $id,
                    'other' => utf8_encode($other)
                ];
            } else {
                return [
                    'success' => false,
                    'result' => $result,
                    'id' => $id,
                    'other' => utf8_encode($other)
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'noproductstosend'
            ];
        }
    }

    public static function SendProducts($limitStart, $id_product=null)
    {
        $lang = (int)Tools::getValue('lang');
        //Get all products
        if ((int) $id_product > 0) {	
            $sql = 'SELECT p.*, pl.* , t.`rate` AS tax_rate, m.`name` AS manufacturer_name, s.`name` AS supplier_name
            FROM `'._DB_PREFIX_.'product` p
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (p.`id_product` = pl.`id_product`)
            LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (p.`id_tax_rules_group` = tr.`id_tax_rules_group`
                AND tr.`id_country` = '.(int)Context::getContext()->country->id.'
                AND tr.`id_state` = 0)
            LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.`id_tax` = tr.`id_tax`)
            LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
            LEFT JOIN `'._DB_PREFIX_.'supplier` s ON (s.`id_supplier` = p.`id_supplier`)
            WHERE pl.`id_lang` = '.(int)($lang).' AND p.id_product='.(int)$id_product.' LIMIT 1';
            $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql);
        } else {
            $products = Product::getProducts($lang, $limitStart, 100, 'name', 'ASC', false);
        }
            
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID'));
        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY'));
        $SPECTER_SEND_ALL = (int)Configuration::get('SPECTER_SEND_ALL');
        $productNumber=0;
        $urldata = '';
        //$totalSent=0;
        //Send all base products
        foreach ($products as $product)
        {
            if ($SPECTER_SEND_ALL==1 || 
                ($SPECTER_SEND_ALL==0 && self::hasAttributes($product['id_product']) < 1)
             ) {
                $productNumber++;
                if($productNumber==1) {
                    // $md5key = MD5($md5key2 . MD5($md5key1 . $sbmId . utf8_decode($product['reference'])));
                    $md5key = MD5($SPECTER_API_USERKEY . $sbmId . utf8_decode($product['reference']));
                    $md5key = SpecterConnector::licenceCheck($sbmId, $md5key,$PRESTAWORKS_USERTOKEN);
                    $url = 'putInfo.asp';
                    $url .= '?action=newArticleSubmit';
                    $url .= '&sbmId='.$sbmId;
                    $url .= '&apiUserId='.$SPECTER_API_USERID;
                    $url .= '&useXML=1';
                    $url .= '&key='.$md5key;
                    $urldata = 'forceArticleUpdate=1';
                    $urldata .= '&addSupplier='. (int) Configuration::get('SPECTER_ADD_SUPPLIER');
                }
                
                $buyPrice = $product['wholesale_price'];
                $product_supplier_reference = $product['supplier_reference'];
                if((int)$product['id_supplier']>0)
                {
                    $supplierdata = self::getSupplierInformation($product['id_supplier'], $product['id_product'], 0);
                    $buyPrice = $supplierdata['product_supplier_price_te'];
                    $product_supplier_reference = $supplierdata['product_supplier_reference'];
                    
                    $urldata .= '&supplierBuyPrice_'.$productNumber.'='. urlencode(utf8_decode($buyPrice));
                    $urldata .= '&supplierNo_'.$productNumber.'='. urlencode(utf8_decode($product['id_supplier']));
                    $urldata .= '&supplierName_'.$productNumber.'='. urlencode(utf8_decode($product['supplier_name']));

                    if ((int) $supplierdata['id_country'] > 0) {
                        $country_of_origin = new Country((int) $supplierdata['id_country']);
                        $country_of_origin = $country_of_origin->iso_code;
                    } else {
                        $country_of_origin = "";
                    }
                    if ("" != $country_of_origin) {
                        $urldata .= '&manufacturingCountryCode_'.$productNumber.'='. urlencode(utf8_decode($country_of_origin));
                    }
                }
                $urldata .= '&barCode_'.$productNumber.'='. urlencode(utf8_decode($product['ean13']));
                $urldata .= '&buyPrice_'.$productNumber.'='. urlencode(utf8_decode($buyPrice));
                $urldata .= '&stockPrice_'.$productNumber.'='. urlencode(utf8_decode($buyPrice));
                $urldata .= '&vatPercent_'.$productNumber.'='. urlencode(utf8_decode($product['tax_rate']));
                $urldata .= '&priceExclVAT_'.$productNumber.'='. urlencode(utf8_decode(number_format($product['price'], 2, '.', '')));
                $urldata .= '&productWeight_'.$productNumber.'='. urlencode(utf8_decode(number_format($product['weight'], 3, '.', '')));
                $volume = ((float)$product['width'] /100) *  ((float)$product['height'] /100) *  ((float)$product['depth'] /100);
                $urldata .= '&productVolume_'.$productNumber.'='. urlencode(utf8_decode(number_format($volume, 2, '.', '')));
                $urldata .= '&createArticleStock_'.$productNumber.'=1';
                $quantity = StockAvailable::getQuantityAvailableByProduct($product['id_product']);
                $urldata .= '&noItemsInStock_'.$productNumber.'='. urlencode(utf8_decode($quantity));
                $urldata .= '&articleNo_'.$productNumber.'='. urlencode(utf8_decode($product['reference']));
                $urldata .= '&name_'.$productNumber.'='. urlencode(utf8_decode($product['name']));
                $urldata .= '&supplierArticleNo_'.$productNumber.'='. urlencode(utf8_decode($product_supplier_reference));
            }
        }
        
        //Send reamaining products
        //$totalSent = $totalSent+$productNumber;
        if($urldata!='') {
            $contents = SpecterConnector::postDataToSpecter($url, $urldata);
            list($result, $id, $other) = explode(';', $contents);
            if($result == '1') {
                return [
                    'success' => true,
                    'result' => $result,
                    'id' => $id,
                    'other' => utf8_encode($other)
                ];
            } else {
                return [
                    'success' => false,
                    'result' => $result,
                    'id' => $id,
                    'other' => utf8_encode($other)
                ];
            }
        } else {
            return [
                'success' => false,
                'result' => 'noproductstosend'
            ];
        }
    }

    private static function getSupplierInformation($id_supplier, $id_product, $id_product_attribute)
    {
        $sql = 'SELECT ps.product_supplier_price_te, ps.product_supplier_reference, a.id_country FROM `'._DB_PREFIX_.'product_supplier` ps LEFT JOIN `'._DB_PREFIX_.'address` a ON ps.id_supplier=a.id_supplier WHERE ps.id_supplier='.$id_supplier.' AND ps.id_product='.$id_product.' AND ps.id_product_attribute='.$id_product_attribute;
        $supplierInfo = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql, false);
        return $supplierInfo;
    }

    private static function hasAttributes($id_product)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
        SELECT COUNT(`id_product_attribute`)
        FROM `'._DB_PREFIX_.'product_attribute`
        WHERE `id_product` = '.(int)($id_product));
    }
}
