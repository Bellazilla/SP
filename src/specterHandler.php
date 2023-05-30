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
use Prestaworks\Module\Specter\SpecterConnector;

class SpecterHandler
{
    public const inventory_calltype = 1;
    public const invoice_calltype = 2;
    public const cancelorder_calltype = 3;
    public const orderupdate_calltype = 4;
    public const articleupdate_calltype = 5;

    public static function getCallsToMake()
    {
        $calls_to_run = [];
        $sql = "SELECT DISTINCT `type` FROM `"._DB_PREFIX_."spectercalls`";
        $calls = Db::getInstance()->executeS($sql);
        foreach($calls as $call) {
            $calls_to_run[] = $call["type"];
        }
        return $calls_to_run;
    }

    public static function removeCalls($type)
    {
        $sql_remove = "DELETE FROM `"._DB_PREFIX_."spectercalls` WHERE `type`=$type";
        Db::getInstance()->execute($sql_remove);
    }

    public static function removeData($id_specter_data)
    {
        $sql_remove = "DELETE FROM `"._DB_PREFIX_."specterdata` WHERE `id_specter_data`=$id_specter_data";
        Db::getInstance()->execute($sql_remove);
    }
    
    public static function encodeString($string)
    {
        $string = str_replace('  ',' ', $string);
        return urlencode(utf8_decode(trim($string)));
    }

    public static function checkCallType($call_type)
    {
        if ($call_type == self::inventory_calltype) {
            return Configuration::get('SPECTER_INVENTORY');
        } elseif ($call_type == self::invoice_calltype) {
            return Configuration::get('SPECTER_INVOICE');
        } elseif ($call_type == self::cancelorder_calltype) {
            return Configuration::get('SPECTER_REMOVE');
        } elseif ($call_type == self::orderupdate_calltype) {
            return Configuration::get('SPECTER_ORDER');
        } elseif ($call_type == self::articleupdate_calltype) {
            return Configuration::get('SPECTER_ARTICLE');
        }
    }

}
