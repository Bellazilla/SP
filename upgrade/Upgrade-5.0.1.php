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

function upgrade_module_5_0_1($module)
{
    // Process Module upgrade to 5.0.1
    $update_sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'specterorderstosend` (
		  `id_specter_ordertosend` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
		  `id_order` INTEGER UNSIGNED NOT NULL,
		  PRIMARY KEY (`id_specter_ordertosend`)
		)
		ENGINE = InnoDB;'; 
    return Db::getInstance()->execute($update_sql);
}
