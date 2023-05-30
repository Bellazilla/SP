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
use State;
use Currency;
use Product;
use Carrier;
use Logger;
use OrderHistory;
use Validate;
use Prestaworks\Module\Specter\SpecterConnector;

class SpecterOrderHelper
{
    public static function buildOrderData($order, $customerNumber, $wrappingtranslation, $discounttranslation)
    {
        $id_shop = $order->id_shop;
        $orderid = $order->id;
        
        $currency = new Currency((int)$order->id_currency);
        
        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY', null, null, $id_shop));
        
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN', null, null, $id_shop));
        $SPECTER_STOCKSITE_ID = (int) Configuration::get('SPECTER_STOCKSITE_ID', null, null, $id_shop);
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID', null, null, $id_shop));
        $prefix = utf8_decode(Configuration::get('SPECTER_PREFIX', null, null, $id_shop));
        
        $carrier = new Carrier((int)$order->id_carrier);
        $externalDeliveryMethod = $carrier->id_reference;
        
        $iso_code = Language::getIsoById((int)$order->id_lang);		
        $iso_code = Tools::strtolower($iso_code);
        if($iso_code=='sv')
            $iso_code = 'SE';
        elseif($iso_code=='se')
            $iso_code = 'SE';
        elseif($iso_code=='en')
            $iso_code = 'EN';
        elseif($iso_code=='fi')
            $iso_code = 'FI';
        elseif($iso_code=='fi')
            $iso_code = 'FI';
        elseif($iso_code=='da')
            $iso_code = 'DA';
        elseif($iso_code=='es')
            $iso_code = 'ES';
        elseif($iso_code=='de')
            $iso_code = 'DE';
        else
            $iso_code = 'SE';
        
        //Customer ID set, continue with order
        
        $url = '';
        $url .= 'customerId='.$customerNumber;
        
        $url .= '&externalOrderNo='.urlencode(utf8_decode($prefix.$orderid));
        if(Configuration::get('SPECTER_IGNORE', null, null, $id_shop)=='1') {
            $url .= '&ignoreExternalOrderNoControl=1';
        }
        
        $ordertransactionaldata = self::getOrderTransactionInformation($order);
        $extra = $ordertransactionaldata['extra'];
        $payTypeIdentifier = $ordertransactionaldata['payTypeIdentifier'];
        $payTypeReference = $ordertransactionaldata['payTypeReference'];
        $payTypeIdentifierPaySystemUser = $ordertransactionaldata['payTypeIdentifierPaySystemUser'];
        
        //Dont set delivery date
        //$url .= 'deliveryDate=';
        $url .= '&currency='.$currency->iso_code;
        if ($SPECTER_STOCKSITE_ID > 0) {
            $url .= '&stocksiteid='.$SPECTER_STOCKSITE_ID;
        }
        $url .= '&language='.$iso_code;
        $url .= '&payTypeIdentifier='.$payTypeIdentifier;
        $url .= '&payTypeReference='.urlencode(utf8_decode($payTypeReference));
        
        if($payTypeIdentifierPaySystemUser!='') {
            $url .= '&payTypeIdentifierPaySystemUser='.$payTypeIdentifierPaySystemUser;
        }
        
        $publishedComment = "";
        if($order->gift) {
            $publishedComment = urlencode(utf8_decode(self::getOrderMessage($order->id).' ').urlencode(utf8_decode($order->gift_message)));
        } else {
            $publishedComment = urlencode(utf8_decode(self::getOrderMessage($order->id)));
        }

        $internalComment = "";
        if ((int)$order->current_state == (int)Configuration::get('PS_OS_ERROR')) {
            $internalComment = urlencode(utf8_decode('PAYMENT ERROR'));
        }

        $internalComment .= ' '. $publishedComment;

        $internalComment = trim($internalComment);
        $publishedComment = trim($publishedComment);
        
        if ("" != $internalComment) {
            $url .= '&internalComment='. $internalComment;
        }

        if ("" != $publishedComment) {
            $url .= '&publishedComment='. $publishedComment;
        }
        
        $url .= '&customerOrderReference='. urlencode(utf8_decode($order->reference));
        $url .= $extra;

        $invoicerow = 0;
        $totalProductsExcltax = 0;
        foreach($order->getProductsDetail() AS $productdetail)
        {
            $customtext = "";
            if ((int) $productdetail['id_customization'] > 0) {
                $customized_datas = Product::getAllCustomizedDatas($order->id_cart, null, true, null, (int) $productdetail['id_customization']);
                self::setProductCustomizedDatas($productdetail, $customized_datas);
                if (is_array($productdetail['customizedDatas'])) {
                    foreach ($productdetail['customizedDatas'] as $customizationPerAddress) {
                        foreach ($customizationPerAddress as $customizationId => $customization) {
                            foreach ($customization["datas"] as $datas) {
                                foreach ($datas as $data) {
                                    $customtext .= $data['name']. " " . $data['value']. " ";
                                }
                            }
                        }
                    }
                }
            }
            $product_price = $productdetail['product_price'];
            $invoicerow++;
            $vatrate = (int)round(((((float)$productdetail['unit_price_tax_incl'] / (float) $productdetail['unit_price_tax_excl'])-1)*100),0);
            if ($vatrate < 0) {
                $vatrate = 0;
            }
            
            $product_price_excl_tax = number_format( $productdetail['unit_price_tax_incl'] / (($vatrate / 100)+1), 3, '.','');
            $productreference = urlencode(utf8_decode($productdetail['product_reference']));
            $url .= '&articleNo_'.$invoicerow.'='.$productreference;
            $url .= '&articleName_'.$invoicerow.'='.urlencode(utf8_decode($productdetail['product_name']));
            
            if ("" != $customtext) {
                $url .= '&articleComment_'.$invoicerow.'='.urlencode(utf8_decode($customtext));
            }
            
            $url .= '&priceExclVAT_'.$invoicerow.'='.urlencode(utf8_decode($product_price_excl_tax));
            $url .= '&noOfItems_'.$invoicerow.'='.urlencode(utf8_decode($productdetail['product_quantity']));
            //calculate vat rate
            
            $url .= '&vatPercent_'.$invoicerow.'='.urlencode(utf8_decode(number_format($vatrate,0)));
            //$url .= '&discount_'.$invoicerow.'='.urlencode(utf8_decode($productdetail['reduction_percent']));
            $totalProductsExcltax += ($productdetail['unit_price_tax_excl'] * $productdetail['product_quantity']);
        }
        
        //ADD SHIPPING COST
        $carrierTax = Tax::getCarrierTaxRate((int)$carrier->id, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE', null, null, $id_shop)});
        if (isset($carrierTax))
            $total_shipping_wt = $order->total_shipping / (1 + ($carrierTax / 100));
        else
            $total_shipping_wt = $order->total_shipping;
        
        $tax_fees = urlencode(utf8_decode(number_format($carrierTax,0)));
        
        $isFreeShipping = self::checkFreeShippingOnOrder($order->id);
        
        $SPECTER_DISCOUNTS = (int) Configuration::get('SPECTER_DISCOUNTS', null, null, $id_shop);
        
        if ($isFreeShipping && 0 == $SPECTER_DISCOUNTS) {
            $discount_free_shipping = $total_shipping_wt;
            $total_shipping_wt = 0;
        }
            
        $invoicerow++;
        $url .= '&articleNo_'.$invoicerow.'='.urlencode(utf8_decode(Configuration::get('SPECTER_SHIPPING_REF', null, null, $id_shop)));
        $url .= '&articleName_'.$invoicerow.'='.urlencode(utf8_decode($carrier->name));
        //$url .= '&articleComment_'.$invoicerow;
        $url .= '&priceExclVAT_'.$invoicerow.'='.urlencode(utf8_decode($total_shipping_wt));
        $url .= '&noOfItems_'.$invoicerow.'=1';
        $url .= '&vatPercent_'.$invoicerow.'='.urlencode(utf8_decode($tax_fees));
        $totalProductsExcltax += $total_shipping_wt;
        
        if($order->gift)
        {
            if($order->total_wrapping>0)
            {
                //ADD WRAPPING COST
                $total_wrapping_tax_incl = $order->total_wrapping_tax_incl;
                $total_wrapping_tax_excl = $order->total_wrapping_tax_excl;
                if($total_wrapping_tax_incl==$total_wrapping_tax_excl)
                    $use_wrapping_fees_tax = false;
                else
                    $use_wrapping_fees_tax = true;
                
                $wrapping_fees = $total_wrapping_tax_excl;
                
                if ($use_wrapping_fees_tax) {
                    // $tax_fees = (($total_wrapping_tax_incl / $total_wrapping_tax_excl)-1)*100;
                    $tax_fees = (int)round(((((float)$total_wrapping_tax_incl / (float)$total_wrapping_tax_excl)-1)*100),0);
                } else {
                    $tax_fees = 0;
                }

                $invoicerow++;
                $url .= '&articleNo_'.$invoicerow.'=giftwrapp';
                $url .= '&articleName_'.$invoicerow.'='.urlencode(utf8_decode($wrappingtranslation));
                //$url .= '&articleComment_'.$invoicerow;
                $url .= '&priceExclVAT_'.$invoicerow.'='.urlencode(utf8_decode($wrapping_fees));
                $url .= '&noOfItems_'.$invoicerow.'=1';
                $url .= '&vatPercent_'.$invoicerow.'='.$tax_fees;
                $totalProductsExcltax += $wrapping_fees;
            }
        }
        
        //ADD DISCOUNT
        
        if (1 == $SPECTER_DISCOUNTS) {
            $cartRules = $order->getCartRules();
            if (!empty($cartRules)) {
                foreach ($cartRules as $cartRule) {
                    $id_cart_rule = $cartRule['id_cart_rule'];
                    $description = $cartRule['name'];
                    $articleNumber = 'discount_'.$id_cart_rule;
                    
                    $price = $cartRule['value_tax_excl'] * -1;
                    
                    $tax_amount = $cartRule['value'] - $cartRule['value_tax_excl'];
                    if ($tax_amount > 0) {
                        $tax_percent = ($tax_amount / $cartRule['value_tax_excl']) * 100;
                        $tax_percent = round($tax_percent, 0);
                    } else {
                        $tax_percent = 0;
                    }
                    
                    if ($tax_percent > 0) {
                        $price = ($cartRule['value']/(1+($tax_percent/100))) * -1;
                        $price = round($price, 2);
                    }

                    $invoicerow++;
                    $url .= '&articleNo_'.$invoicerow.'='.$articleNumber;
                    $url .= '&articleName_'.$invoicerow.'='.urlencode(utf8_decode($description));
                    $url .= '&priceExclVAT_'.$invoicerow.'='.urlencode(utf8_decode($price));
                    $url .= '&noOfItems_'.$invoicerow.'=1';
                    $url .= '&vatPercent_'.$invoicerow.'='.urlencode(utf8_decode(number_format($tax_percent,0)));
                }
            }
        } else {
            if ($carrierTax && $carrierTax != '0.00')
                $total_discount = $order->total_discounts / (1 + ($carrierTax / 100));
            else
                $total_discount = $order->total_discounts;
                
            if($isFreeShipping)
            {
                $total_discount = $total_discount - $discount_free_shipping;
            }
            if($total_discount>0.0)
            {	
                if($total_discount>$totalProductsExcltax)
                    $total_discount = $totalProductsExcltax;
                if($order->total_paid_real==0 && $totalProductsExcltax > 0 && $total_discount == $totalProductsExcltax)
                    $total_discount = $totalProductsExcltax;
                $invoicerow++;
                $url .= '&articleNo_'.$invoicerow.'=discount';
                $url .= '&articleName_'.$invoicerow.'='.urlencode(utf8_decode($discounttranslation));
                $url .= '&priceExclVAT_'.$invoicerow.'=-'.urlencode(utf8_decode($total_discount));
                $url .= '&noOfItems_'.$invoicerow.'=1';
                $url .= '&vatPercent_'.$invoicerow.'='.urlencode(utf8_decode(number_format($carrierTax,0)));
            }
        }
        
        
        $url .= '&sendOrderMail='.(int) Configuration::get('SPECTER_SENDORDERMAIL', null, null, $id_shop);
        $url .= '&sendOrderMailToUser=0';
        $url .= '&userSource=webuser';
        
        // if ($deliveryaddress->phone != "" || $deliveryaddress->phone_mobile != "") {
            // $smsnr = $deliveryaddress->phone_mobile;
            // if ($smsnr != "") {
                // $smsnr = $deliveryaddress->phone;
            // }
            
            // $url .= '&notifyDeliveryByMobile=1';
            // $url .= '&notifyDeliveryMobileNo='.urlencode(utf8_decode($smsnr));
            
        // }


        $preparedShipmentId = Db::getInstance()->getValue('SELECT shipping_reference FROM '._DB_PREFIX_.'klarnacarrier WHERE id_cart='.(int) $order->id_cart);
        if ("" != $preparedShipmentId) {
            $url .= '&preparedShipmentId='.urlencode(utf8_decode($preparedShipmentId));
        }
        
        $url .= '&externalDeliveryMethod='.$externalDeliveryMethod;
        
        $md5key = MD5($SPECTER_API_USERKEY.$sbmId.$customerNumber);
        $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);
        $url .= '&key='.$md5key;
        
        return $url;
    }
    
    private static function setProductCustomizedDatas(&$product, $customized_datas)
    {
        $product['customizedDatas'] = null;
        if (isset($customized_datas[$product['product_id']][$product['product_attribute_id']])) {
            $product['customizedDatas'] = $customized_datas[$product['product_id']][$product['product_attribute_id']];
        } else {
            $product['customizationQuantityTotal'] = 0;
        }
    }

    private static function checkFreeShippingOnOrder($id_order)
    {
        $sql = "SELECT count(ocr.id_cart_rule) num FROM "._DB_PREFIX_."order_cart_rule ocr LEFT JOIN "._DB_PREFIX_."cart_rule cr ON ocr.id_cart_rule=cr.id_cart_rule WHERE ocr.id_order=$id_order AND cr.free_shipping=1";
        $freeshipping = Db::getInstance()->getValue($sql);
        if($freeshipping > 0) {
            return true;
        } else {
            return false;
        }
    }

    private static function getOrderMessage($orderid)
    {
        $sql = 'SELECT `message` FROM `'._DB_PREFIX_.'message` WHERE `id_order` = '.(int)$orderid.' AND private=0 ORDER BY `id_message` asc';
        $result = Db::getInstance()->getRow($sql);
        return $result['message'];
    }

    private static function getOrderTransactionInformation($order)
    {
        $extra = $payTypeIdentifier = $payTypeReference = $payTypeIdentifierPaySystemUser = $payTypeIdentifierPaySystemUser = "";
        $payTypeReference = self::getOrderTransactionNumber($order->id, $order->reference);

        switch($order->module) {
            case 'invoice':
                $payTypeIdentifier = 'I';
                break;
            case 'auriga':
                $payTypeIdentifier = 'AE';
                break;
            case 'bankwire':
                $payTypeIdentifier = 'PP';
                $extra .= '&isPrepayment=1';
                break;
            case 'securepay':
                $payTypeIdentifier = 'PE'; //payer old name
                break;
            case 'payer':
                $payTypeIdentifier = 'PE'; //payer new name
                break;
            case 'dibs':
                $payTypeIdentifier = 'DP'; //Dibs
                break;
            case 'dibsdx':
                $payTypeIdentifier = 'DP'; //Dibs
                break;
            case 'dibsd2':
                $payTypeIdentifier = 'DP'; //Dibs
                break;
            case 'cheque':
                $payTypeIdentifier = 'PP'; //check
                break;
            case 'sveawebpayinvoice':
                $payTypeIdentifier = 'SP'; //svea faktura
                break;
            case 'sveawebpaypaymentplan':
                $payTypeIdentifier = 'SS'; //svea delbetalning
                break;
            case 'sveawebpay':
                if (strlen(strstr($order->payment,'(delbet)'))>0) {
                    $payTypeIdentifier = 'SS'; //svea delbetalning
                } else {
                    $payTypeIdentifier = 'SP'; //svea faktura
                }
                break;
            case 'sveawebpayno':
                if (strlen(strstr($order->payment,'(delbet)'))>0) {
                    $payTypeIdentifier = 'SS'; //svea delbetalning
                } else {
                    $payTypeIdentifier = 'SP'; //svea faktura
                }
                break;
            case 'cashondelivery':
                $payTypeIdentifier = 'P'; //PF utan avgift
                $payTypeReference = '';
                break;
            case 'cashondeliverywithfee':
                $payTypeIdentifier = 'P'; //PF utan avgift
                break;
            case 'codwithfee':
                $payTypeIdentifier = 'P'; //PF utan avgift
                break;
            case 'payson':
            case 'paysondirect':
                $payTypeIdentifier = 'PY'; //payson
                break;
            case 'paypal':
                $payTypeIdentifier = 'PL'; //payson
                break;
            case 'certitradenet':
                $payTypeIdentifier = 'CN'; //certitradenet
                break;
            case 'payinstore':
                $payTypeIdentifier = 'D'; //Betala i butik
                break;
            case 'kreditor':
                $payTypeIdentifier = 'K'; //Klarna
                break;
            case 'svea':
                $payTypeIdentifier = 'SK'; //Svea kort preliminar
                break;
            case 'sveakortbetalning':
                $payTypeIdentifier = 'SK'; //Svea kort preliminar
                break;
            case 'klarnaofficial' :
            case 'klarnacheckout' :
                $result = Db::getInstance()->getRow('SELECT reservation, eid FROM '._DB_PREFIX_.'klarna_orders WHERE id_cart='.$order->id_cart);
                if ($result["eid"] == Configuration::get('KCOV3_MID', null, null, $order->id_shop)) {
                    $payTypeIdentifier = 'KG';
                } else {
                    $payTypeIdentifier = 'K';
                }
                $payTypeIdentifierPaySystemUser = $result["eid"];
                $payTypeReference = $result["reservation"];
                break;
            case 'handelsbankenpp':
                $payTypeIdentifier = 'HF'; //Handelsbanken Faktura
                break;
            case 'handelsbankeninvoice':
                $payTypeIdentifier = 'HF'; //Handelsbanken Faktura
                break;
            case 'handelsbankenpopup':
                $payTypeIdentifier = 'HF'; //Handelsbanken Faktura
                break;
            case 'handelsbanken':
                $payTypeIdentifier = 'HF'; //Handelsbanken Faktura
                break;
            case 'collectorcw':
                $payTypeIdentifier = 'CL'; //Collector
                break;
            case 'collectorcw_collectordirect':
                $payTypeIdentifier = 'CL'; //Collector
                break; 
            case 'swish':
            case 'swish_handel':
                $payTypeIdentifier = 'SW';
                break;
            case 'sveacheckout':
                $payTypeIdentifier = 'SCH'; //Svea checkout
                if (false !== strpos($order->payment, 'SWISH')) {
                    $payTypeIdentifier = 'SW'; //Svea checkout swish payment
                }
                $sql = 'SHOW TABLES LIKE "'._DB_PREFIX_.'sveacheckout_keys"';
                $tables = Db::getInstance()->executeS($sql);
                if(is_array($tables) AND count($tables) > 0) {
                    $sql = 'SELECT `merchant` FROM `'._DB_PREFIX_.'sveacheckout_keys` WHERE id_order='.$order->id;
                    $merchant = Db::getInstance()->getValue($sql);
                    $checkoutMerchantId = Configuration::get('SVEACHECKOUT_MERCHANT_'.$merchant, null, null, $order->id_shop);
                } else {
                    $checkoutMerchantId = "";
                }
                $payTypeIdentifierPaySystemUser = $checkoutMerchantId;
                break;
            case 'billmatebankpay':
            case 'billmatecardpay':
            case 'billmatecheckout':
            case 'billmategateway':
            case 'billmateinvoice':
            case 'billmatepartpay':
                $payTypeIdentifier = 'BC';
                break;
        }
        return ['payTypeIdentifier' => $payTypeIdentifier,
            'payTypeReference' => $payTypeReference,
            'payTypeIdentifierPaySystemUser' => $payTypeIdentifierPaySystemUser,
            'extra' => $extra
        ];
    }


    private static function getOrderTransactionNumber($orderid, $orderreference = false)
    {
        if ($orderreference !== false) {
            $result = Db::getInstance()->getRow("SELECT transaction_id FROM "._DB_PREFIX_."order_payment WHERE order_reference='".$orderreference."'");
            $payTypeReference = $result["transaction_id"];
            if (strlen($payTypeReference) > 0) {
                return $payTypeReference;
            }
        }
        $result = Db::getInstance()->getRow('SELECT message FROM '._DB_PREFIX_.'message WHERE id_order='.$orderid.' AND private=1 ORDER BY id_message ASC');
        $paymentref = $result['message'];
        //Needs some cleaning up
        $paymentreftmp = nl2br($paymentref);
        $paymentreftmp = preg_replace('/\<br(\s*)?\/?\>/i',';',$paymentreftmp);
        $paymentreftmp = preg_replace('/\<br>/i',';',$paymentreftmp);
        $paymentreftmp = preg_replace('/\|/i',';',$paymentreftmp);
        $dibsfix = explode(';',preg_replace('/\<br(\s*)?\/?\>/i',';',$paymentreftmp));
        foreach($dibsfix as $dibsparam){
            if(preg_match('/transact/', $dibsparam))
                $paymentref = $dibsparam;
            if(preg_match('/Klarna:/', $dibsparam))
                $paymentref = $dibsparam;
        }
            
        $paymentref = str_replace('lagd av:','',$paymentref);
        $paymentref = str_replace('Payer_paymentid: ','',$paymentref);
        $paymentref = str_replace('Sveaid:','',$paymentref);
        $paymentref = str_replace('sveaid:','',$paymentref);
        $paymentref = str_replace('sveanr:','',$paymentref);
        $paymentref = str_replace('Sveanr:','',$paymentref);
        $paymentref = str_replace('Kontraktnr:','',$paymentref);
        $paymentref = str_replace('Paypal transaktions ID:','',$paymentref);
        $paymentref = str_replace('Paypal Transaction ID:','',$paymentref);
        $paymentref = str_replace('TransaktionsID:','',$paymentref);
        $paymentref = str_replace('Klarna fakturanummer','',$paymentref);
        $paymentref = str_replace('Klarna:','',$paymentref);
        $paymentref = str_replace('pclass:','',$paymentref);
        $paymentref = str_replace('pno:','',$paymentref);
        $paymentref = str_replace('mobile:','',$paymentref);
        $paymentref = str_replace('ip:','',$paymentref);
        $paymentref = str_replace('ysalary:','',$paymentref);
        $paymentref = str_replace('houseno:','',$paymentref);
        $paymentref = str_replace('houseext:','',$paymentref);
        $paymentref = str_replace('Transaction:','',$paymentref);
        $paymentref = str_replace('transaction:','',$paymentref);
        $paymentref = str_replace('TransaktionsID::','',$paymentref);
        $paymentref = str_replace('transact:','',$paymentref);
        $paymentref = str_replace('Id hos Handelsbanken : ','',$paymentref);
        
        return trim($paymentref);
    }
}
