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

function upgrade_module_6_3_4($module)
{
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."specterdata` CHANGE `article_number` `article_number` VARCHAR(245) NULL;";
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."specterdata` CHANGE `in_stock` `in_stock` VARCHAR(245) NULL;";
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."specterdata` CHANGE `specter_order_id` `specter_order_id` VARCHAR(45) NULL;";
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."specterdata` CHANGE `tracking_number` `tracking_number` VARCHAR(145) NULL;";
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."specterdata` CHANGE `invoice_id` `invoice_id` VARCHAR(145) NULL;";
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."specterdata` CHANGE `product_data` `product_data` TEXT NULL;";

    foreach ($sql as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
