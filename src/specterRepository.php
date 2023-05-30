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
use Tools;
use Tab;
use OrderState;
use Language;

class SpecterRepository
{
    public static function dropConfigurationSettings()
    {
        Configuration::deleteByName('SPECTER_STATEID');
        Configuration::deleteByName('SPECTER_SBMID');
        Configuration::deleteByName('SPECTER_TAX_25');
        Configuration::deleteByName('SPECTER_TAX_12');
        Configuration::deleteByName('SPECTER_TAX_6');
        Configuration::deleteByName('SPECTER_ISPLUSUSER');
        Configuration::deleteByName('SPECTER_API_USERKEY');
        Configuration::deleteByName('SPECTER_API_USERID');
        Configuration::deleteByName('PRESTAWORKS_USERTOKEN');
        Configuration::deleteByName('SPECTER_IGNORE');
        Configuration::deleteByName('SPECTER_SHIPPING_REF');
        Configuration::deleteByName('SPECTER_CG');
        Configuration::deleteByName('SPECTER_FILTER_DATE');
        Configuration::deleteByName('SPECTER_PREFIX');
        Configuration::deleteByName('SPECTER_PROD_PREFIX');
        Configuration::deleteByName('SPECTER_HTTP_HOST');
        Configuration::deleteByName('SPECTER_INVOICE');
        Configuration::deleteByName('SPECTER_INVENTORY');
        Configuration::deleteByName('SPECTER_REMOVE');
        Configuration::deleteByName('SPECTER_ORDER');
        Configuration::deleteByName('SPECTER_ARTICLE');
        Configuration::deleteByName('SPECTER_ADD_SUPPLIER');
        Configuration::deleteByName('SPECTER_DELIVERY_STATE');
        Configuration::deleteByName('SPECTER_INVOICE_STATE');
        Configuration::deleteByName('SPECTER_DISCOUNTS');
        Configuration::deleteByName('SPECTER_USEMULTISTOCK');
        Configuration::deleteByName('SPECTER_SEND_ALL');
        return true;
    }
    
    public static function createDatabaseTables()
    {
        $sql = [];
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'specterdata` (
            `id_specter_data` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` INTEGER UNSIGNED NOT NULL,
            `article_number` VARCHAR(245) NULL,
            `in_stock` INTEGER NULL,
            `specter_order_id` VARCHAR(45) NULL,
            `tracking_number` VARCHAR(145) NULL,
            `invoice_id` VARCHAR(145) NULL,
            `product_data` TEXT NULL,
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
        
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'specterorders` (
            `id_order` INTEGER UNSIGNED NOT NULL,
            `id_specter_order` INTEGER UNSIGNED NOT NULL,
            `id_specter_invoice` INTEGER UNSIGNED NOT NULL,
            PRIMARY KEY (`id_order`)
        )
        ENGINE = InnoDB;';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'spectercustomers` (
            `id_customer` INTEGER UNSIGNED NOT NULL,
            `id_specter_customer` INTEGER UNSIGNED NOT NULL,
            PRIMARY KEY (`id_customer`)
        )
        ENGINE = InnoDB;';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'specterproductstoupdate` (
            `id_product` INTEGER UNSIGNED NOT NULL
        )
        ENGINE = InnoDB;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }
    
    public static function dropDatabaseTables()
    {
        $sql = [];
        $sql[] = 'DROP TABLE IF EXISTS`'._DB_PREFIX_.'specterdata`;';
        $sql[] = 'DROP TABLE IF EXISTS`'._DB_PREFIX_.'spectercalls`;';
        $sql[] = 'DROP TABLE IF EXISTS`'._DB_PREFIX_.'specterorderstosend`;';
        $sql[] = 'DROP TABLE IF EXISTS`'._DB_PREFIX_.'spectercustomers`;';
        $sql[] = 'DROP TABLE IF EXISTS`'._DB_PREFIX_.'specterorders`;';
        $sql[] = 'DROP TABLE IF EXISTS`'._DB_PREFIX_.'specterproductstoupdate`;';
        foreach ($sql as $query)
        {
            if (Db::getInstance()->execute($query) == false) {
                return false;
            }
        }
        return true;
    }

    public static function removeTabs()
    {
        $tab = new Tab((int)Tab::getIdFromClassName('Prestaworks'));
        $tab2 = new Tab((int)Tab::getIdFromClassName('AdminSpecterOrderManager'));
        $tab3 = new Tab((int)Tab::getIdFromClassName('AdminSpecterProductManager'));
        $tab4 = new Tab((int)Tab::getIdFromClassName('AdminSpecterAjax'));

        if (!$tab->delete() ||
            !$tab2->delete() ||
            !$tab3->delete() ||
            !$tab4->delete()
        ) {
            return false;
        }
        return true;
    }

    public static function createTabs($modulename)
    {
        $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='Prestaworks'";
        $id_parent = (int) Db::getInstance()->getValue($sql);

        if ($id_parent == 0) {
            foreach(Language::getLanguages(false) AS $language)
            {
                $names[$language['id_lang']] = 'Prestaworks';
            }

            $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='IMPROVE'";
            $id_tab = (int) Db::getInstance()->getValue($sql);

            $id_parent = self::createTab($id_tab, $names, 'Prestaworks', 'settings', $modulename);
        }
        foreach(Language::getLanguages(false) AS $language)
        {
            $names1[$language['id_lang']] = 'Specter Orders';
        }
        foreach(Language::getLanguages(false) AS $language)
        {
            $names2[$language['id_lang']] = 'Specter Products';
        }
        foreach(Language::getLanguages(false) AS $language)
        {
            $names3[$language['id_lang']] = 'Specter Ajax';
        }

        if (!self::createTab($id_parent, $names2, 'AdminSpecterProductManager', '', $modulename) ||
            !self::createTab($id_parent, $names1, 'AdminSpecterOrderManager', '', $modulename) ||
            !self::createTab(-1, $names3, 'AdminSpecterAjax', '', $modulename)
            ) {
                return false;
            }
        return true;
    }

    public static function createOrderStatus()
    {
        //ADD SPECIAL ORDER STATUS
        $states = OrderState::getOrderStates(Configuration::get('PS_LANG_DEFAULT'));
        $exists = false;
        foreach($states AS $state)
        {
            if($state['name']=='Specter')
            {
                Configuration::updateValue('SPECTER_STATEID', $state['id_order_state']);
                return true;
            }
        }

        $orderstate = new OrderState();
        foreach(Language::getLanguages(false) AS $language)
        {
            $names[$language['id_lang']] = 'Specter';
            $templates[$language['id_lang']] = '';
        }
        $orderstate->name = $names;
        $orderstate->send_email = false;
        $orderstate->invoice = true;
        $orderstate->color = '#CCFFCD';
        $orderstate->unremovable = true;
        $orderstate->hidden = true;
        $orderstate->logable = true;
        if ($orderstate->save()) {
            Configuration::updateValue('SPECTER_STATEID', $orderstate->id);
            if (!copy(
                _PS_MODULE_DIR_.'/specter/assets/img/logo.gif',
                _PS_IMG_DIR_.'os/'.$orderstate->id.'.gif'
            )) {
                return false;
            }
            return true;
        }
        return false;
    }

    private static function createTab($id_parent, $names, $class_name, $class = '', $modulename)
    {
        $tab = new Tab();  
        $tab->name = $names;
        $tab->class_name = $class_name;
        $tab->icon = $class;
        
        $tab->id_parent = $id_parent;
        $tab->module = $modulename;
        $tab->add();
        return $tab->id;
    }
}
