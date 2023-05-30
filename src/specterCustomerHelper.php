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
use Db;
use Language;
use State;
use Currency;
use Country;
use Logger;
use Prestaworks\Module\Specter\SpecterConnector;
use Prestaworks\Module\Specter\SpecterHandler;

class SpecterCustomerHelper
{
    public static function buildCustomerData($customer, $currency, $invoiceaddress, $deliveryaddress, $id_lang, $id_shop, $orgno = '')
    {
        $SPECTER_API_USERKEY = utf8_decode(Configuration::get('SPECTER_API_USERKEY', null, null, $id_shop));
        
        $PRESTAWORKS_USERTOKEN = utf8_decode(Configuration::get('PRESTAWORKS_USERTOKEN', null, null, $id_shop));
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID', null, null, $id_shop));
        $cg = (int)utf8_decode(Configuration::get('SPECTER_CG', null, null, $id_shop));
        $iso_code = Language::getIsoById((int)$id_lang);
        
        $countrydelivery = new Country($deliveryaddress->id_country);
        $countryinvoice = new Country($invoiceaddress->id_country);

        if($iso_code=='sv')
            $iso_code = 'SE';
        elseif($iso_code=='en')
            $iso_code = 'EN';
        elseif($iso_code=='no')
            $iso_code = 'NO';
        else
            $iso_code = 'SE';
            
        if (strlen($invoiceaddress->company)>0) {
            $customername = trim($invoiceaddress->company);
            $customerType = 'C';
        } else {
            $customername = trim($customer->firstname).' '.trim($customer->lastname);
            $customerType = 'P';
        }

        $customername2 = urldecode(SpecterHandler::encodeString($customername));
        $md5key = MD5($SPECTER_API_USERKEY.$sbmId. $customername2);
        $md5key = SpecterConnector::licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN);

        //submit customer if no specterID
        $url = '';
        $url .='&currency='.$currency->iso_code;
        $url .='&language='.$iso_code;

        if($customerType == 'P')
        {
            $url .='&firstName='.SpecterHandler::encodeString($customer->firstname);
            $url .='&surName='.SpecterHandler::encodeString($customer->lastname);
            $url .='&contactPerson='.SpecterHandler::encodeString($customername);
        }
        else
        {
            $url .='&name='.SpecterHandler::encodeString($customername);
            $url .='&attention='.SpecterHandler::encodeString($invoiceaddress->firstname.' '.$invoiceaddress->lastname);
            $url .='&deliveryAttention='.SpecterHandler::encodeString($deliveryaddress->firstname.' '.$deliveryaddress->lastname);
            $url .='&contactPerson='.SpecterHandler::encodeString($invoiceaddress->firstname.' '.$invoiceaddress->lastname);
        }
        
        if ($cg>0) {
            $url .='&customerGroupId='.$cg;
        }
            
        $url .='&customerOrganizationalForm='.$customerType;
        
        if (strlen($orgno)>0) {
            $url .='&orgNo='.SpecterHandler::encodeString($orgno);
        }
            
        $url .='&homepage='.SpecterHandler::encodeString($customer->website);
        
        //Invoice Information
        if(strlen($invoiceaddress->company)>0)
            $url .='&invoiceCompanyName='.SpecterHandler::encodeString($invoiceaddress->company);
        $url .='&address='. SpecterHandler::encodeString($invoiceaddress->address1);
            if(strlen($invoiceaddress->address2)>0)
        $url .='&addressRow2='.SpecterHandler::encodeString($invoiceaddress->address2);
        $url .='&zipCodeEng='.SpecterHandler::encodeString($invoiceaddress->postcode);
        $url .='&city='.SpecterHandler::encodeString($invoiceaddress->city);
        $url .='&countryCode='.$countryinvoice->iso_code;
        if ($invoiceaddress->id_state > 0) {
            $state = new State($invoiceaddress->id_state);
            $url .='&region='.$state->iso_code;
        }
        
        //delivery Information
        if(strlen($deliveryaddress->company)>0)
        {
            $deliverycustomername = $deliveryaddress->company;
            $url .='&deliveryAttention='. SpecterHandler::encodeString($deliveryaddress->firstname.' '.$deliveryaddress->lastname);
        }
        else
        {
            $deliverycustomername = $deliveryaddress->firstname.' '.$deliveryaddress->lastname;
        }
        $url .='&deliveryCompanyName='. SpecterHandler::encodeString($deliverycustomername);
        $url .='&deliveryAddress='.SpecterHandler::encodeString($deliveryaddress->address1);
        if(strlen($deliveryaddress->address2)>0) {
            $url .='&deliveryAddressRow2='.SpecterHandler::encodeString($deliveryaddress->address2);
        }
            
        $url .='&deliveryZipCodeEng='.SpecterHandler::encodeString($deliveryaddress->postcode);
        $url .='&deliveryCity='.SpecterHandler::encodeString($deliveryaddress->city);
        $url .='&deliveryCountryCode='.$countrydelivery->iso_code;
        if ($deliveryaddress->id_state > 0) {
            $state = new State($deliveryaddress->id_state);
            $url .='&deliveryregion='.$state->iso_code;
        }
        
        $url .='&emailAddress='.SpecterHandler::encodeString($customer->email);
        $url .='&phoneNo='.SpecterHandler::encodeString($deliveryaddress->phone);
        $url .='&vatNo='.SpecterHandler::encodeString($deliveryaddress->vat_number);
        $url .='&mobileNo='.SpecterHandler::encodeString($deliveryaddress->phone_mobile);
        
        $url .='&key='.$md5key; //MD5 key
        return $url;
    }
}
