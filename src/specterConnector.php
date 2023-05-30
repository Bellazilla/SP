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
use Tools;

class SpecterConnector
{
    private static $specter_base_url = "https://api.specter.se/";

    public static function callInternalProcessSpecter($url) {
    }

    public static function licenceCheck($sbmId, $md5key, $PRESTAWORKS_USERTOKEN) {
        $url = 'https://license.prestaworks.se/specterMD5calculator.php?sbmid='.$sbmId .'&md5key='.$md5key.'&token='.$PRESTAWORKS_USERTOKEN;
        if (1 == (int) Configuration::get('SPECTER_ISPLUSUSER')) {
            $url = $url."&is_plususer";
        }
        return self::getDataByCurl($url);
    }

    public static function postDataToSpecter($endpoint, $data=null)
    {
        $url = self::$specter_base_url.$endpoint;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST      ,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_POSTFIELDS    ,$data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION  ,0);
        curl_setopt($ch, CURLOPT_HEADER      ,0);  // DO NOT RETURN HTTP HEADERS
        curl_setopt($ch, CURLOPT_RETURNTRANSFER  ,1);  // RETURN THE CONTENTS OF THE CALL
        $contents = curl_exec($ch);
        curl_close ($ch);
        return $contents;
    }

    public static function getDataFromSpecter($endpoint) {
        $url = self::$specter_base_url.$endpoint;
        return self::getDataByCurl($url);
    }

    private static function getDataByCurl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
                        
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $contents = curl_exec ($ch);
        curl_close ($ch);
        return $contents;
    }
}
