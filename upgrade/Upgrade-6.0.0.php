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

function upgrade_module_6_0_0($module)
{
    // Process Module upgrade to 6.0.0
    $sql = array();

    $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'specterdata` (
              `id_specter_data` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
              `type` INTEGER UNSIGNED NOT NULL,
              `article_number` VARCHAR(245) NOT NULL,
              `in_stock` INTEGER NOT NULL,
              `specter_order_id` VARCHAR(45) NOT NULL,
              `tracking_number` VARCHAR(145) NOT NULL,
              `invoice_id` VARCHAR(145) NOT NULL,
              `product_data` TEXT NOT NULL,
              PRIMARY KEY (`id_specter_data`)
            )
            ENGINE = InnoDB;';

    $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'spectercalls` (
              `id_specter_call` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
              `type` INTEGER UNSIGNED NOT NULL,
              PRIMARY KEY (`id_specter_call`)
            )
            ENGINE = InnoDB;';

    $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'specterorderstosend` (
              `id_specter_ordertosend` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
              `id_order` INTEGER UNSIGNED NOT NULL,
              PRIMARY KEY (`id_specter_ordertosend`)
            )
            ENGINE = InnoDB;';  

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }
    
    $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='IMPROVE'";
    $id_tab = (int)Db::getInstance()->getValue($sql);
    
    $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='Prestaworks'";
    $id_parent = (int)Db::getInstance()->getValue($sql);
    if ($id_parent == 0) {
        foreach(Language::getLanguages(false) AS $language)
        {
            $names[$language['id_lang']] = 'Prestaworks';
        }
        $id_parent = createTab($id_tab, $names, 'Prestaworks', 'settings');
    }
    
    foreach(Language::getLanguages(false) AS $language)
    {
        $names[$language['id_lang']] = 'Specter Orders';
    }
    
    createTab($id_parent, $names, 'AdminSpecterOrderManager');
    
    foreach(Language::getLanguages(false) AS $language)
    {
        $names[$language['id_lang']] = 'Specter Products';
    }
    createTab($id_parent, $names, 'AdminSpecterProductManager');
    
    return true;
}

function createTab($id_parent, $names, $class_name, $class = '') {
    $tab = new Tab();  
    $tab->name = $names;
    $tab->class_name = $class_name;
    $tab->icon = $class;
    
    $tab->id_parent = $id_parent;
    $tab->module = 'specter';
    $tab->add();
    return $tab->id;
}
