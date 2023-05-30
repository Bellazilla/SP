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

function upgrade_module_6_3_1($module)
{
    try {
        rrmdir6_3_1_specter(dirname(__FILE__). '/../library');
    } catch (Exception $e) {
        /*REMOVAL IS NOT ESSENTIAL*/
    }
    try {
        rrmdir6_3_1_specter(dirname(__FILE__). '/../mails');
    } catch (Exception $e) {
        /*REMOVAL IS NOT ESSENTIAL*/
    }
    try {
        rrmdir6_3_1_specter(dirname(__FILE__). '/../sql');
    } catch (Exception $e) {
        /*REMOVAL IS NOT ESSENTIAL*/
    }
    try {
        rrmdir6_3_1_specter(dirname(__FILE__). '/../classes');
    } catch (Exception $e) {
        /*REMOVAL IS NOT ESSENTIAL*/
    }
    return true;
}

function rrmdir6_3_1_specter($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object)) {
                    rrmdir6_3_1_specter($dir. DIRECTORY_SEPARATOR .$object);
                } else {
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
                }
            }
        }
        rmdir($dir);
    }
}