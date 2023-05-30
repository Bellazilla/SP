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

function upgrade_module_6_3_5($module)
{
    $sql = [];
    $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'specterproductstoupdate` (
        `id_product` INTEGER UNSIGNED NOT NULL
    )
    ENGINE = InnoDB;';

    foreach ($sql as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    $module->registerHook("actionProductSave");

    return true;
}
