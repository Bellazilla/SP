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

function upgrade_module_6_3_3($module)
{
    $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='AdminSpecterAjax'";
    $id_tab = (int) Db::getInstance()->getValue($sql);
    if ($id_tab == 0) {
        $tab = new Tab();
        $tab->class_name = 'AdminSpecterAjax';
        $tab->active = 1;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'AdminSpecterAjax';
        }
        $tab->id_parent = -1;
        $tab->module = 'specter';
        $tab->add();
    }

    $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='Prestaworks'";
    $id_parent = (int) Db::getInstance()->getValue($sql);

    if ($id_parent == 0) {
        $tab = new Tab();
        $tab->class_name = 'settings';
        $tab->active = 1;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Prestaworks';
        }
        $tab->id_parent = -1;
        $tab->module = '';
        $tab->add();
        $id_parent = $tab->id;
    }
    
    $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='AdminSpecterProductManager'";
    $id_tab = (int) Db::getInstance()->getValue($sql);
    if ($id_tab == 0) {
        $tab = new Tab();
        $tab->class_name = 'AdminSpecterProductManager';
        $tab->active = 1;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'AdminSpecterProductManager';
        }
        $tab->id_parent = $id_parent;
        $tab->module = 'specter';
        $tab->add();
    }

    $sql = "SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='AdminSpecterOrderManager'";
    $id_tab = (int) Db::getInstance()->getValue($sql);
    if ($id_tab == 0) {
        $tab = new Tab();
        $tab->class_name = 'AdminSpecterOrderManager';
        $tab->active = 1;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'AdminSpecterOrderManager';
        }
        $tab->id_parent = $id_parent;
        $tab->module = 'specter';
        $tab->add();
    }

    return true;
}
