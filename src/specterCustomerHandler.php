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
use Logger;
use Prestaworks\Module\Specter\SpecterConnector;
use Prestaworks\Module\Specter\SpecterHandler;
use Prestaworks\Module\Specter\SpecterCustomerHelper;

class SpecterCustomerHandler
{
    public static function CreateCustomer($customer, $currency, $invoiceaddress, $deliveryaddress, $id_lang, $id_shop, $orgno = '')
    {
        $data = SpecterCustomerHelper::buildCustomerData($customer, $currency, $invoiceaddress, $deliveryaddress, $id_lang, $id_shop, $orgno);
        //SEND DATA TO SPECTER
        $sbmId = utf8_decode(Configuration::get('SPECTER_SBMID'));
        $SPECTER_API_USERID = utf8_decode(Configuration::get('SPECTER_API_USERID', null, null, $id_shop));
        
        $endpoint = 'putInfo.asp?action=newCustomerSubmit';
        $endpoint .= '&sbmId='.$sbmId;
        $endpoint .= '&apiUserId='.$SPECTER_API_USERID;
        $endpoint .='&useXML=1';
        //'&customerId='.$customer->id.  //Här ska det vara interna ID
        $endpoint .='&forceCustomerUpdate=1';

        $contents = SpecterConnector::postDataToSpecter($endpoint, $data);

        //Save specterID for customer				
        list($result, $id, $other) = explode(';', $contents);

        if($result=='1')
        {
            //New customer, success
            Db::getInstance()->insert('spectercustomers', array(
                'id_customer'	=> (int) $customer->id,
                'id_specter_customer'	=> (int) $id,
            ));
            return $id;
        }
        else
        {
            //New customer, failed
            Logger::addLog("SBM customer:".$contents, 3, null, 'specter', null, true);
            return false;
        }
    }
}
