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

function upgrade_module_6_3_0($module)
{
    /*create new tables */
    $sql[] = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."specterorders` (
        `id_order` INTEGER UNSIGNED NOT NULL,
        `id_specter_order` INTEGER UNSIGNED NOT NULL,
        `id_specter_invoice` INTEGER UNSIGNED NOT NULL,
        PRIMARY KEY (`id_order`)
    )
    ENGINE = InnoDB;";

    $sql[] = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."spectercustomers` (
        `id_customer` INTEGER UNSIGNED NOT NULL,
        `id_specter_customer` INTEGER UNSIGNED NOT NULL,
        PRIMARY KEY (`id_customer`)
    )
    ENGINE = InnoDB;";

    /*move data to new tables */
    $sql[] = "INSERT INTO `"._DB_PREFIX_."spectercustomers` SELECT id_customer, id_specter FROM `"._DB_PREFIX_."customer` WHERE id_specter IS NOT NULL;";
    $sql[] = "INSERT INTO `"._DB_PREFIX_."specterorders` SELECT id_order, id_specter, specterInvoice FROM `"._DB_PREFIX_."orders` WHERE id_specter IS NOT NULL;";
    
    /*remove old columns */
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."orders` DROP `id_specter`";
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."orders` DROP `specterInvoice`";
    $sql[] = "ALTER TABLE `"._DB_PREFIX_."customer` DROP `id_specter`";

    foreach ($sql as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
