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

class AdminSpecterProductManagerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'product';
        $this->className = 'Product';
        $this->lang = false;
        $this->explicitSelect = true;
        $this->list_no_link = true;
        
        parent::__construct();
    }
    
    public function renderList()
    {
        if (isset($_POST['btnSubmit_setreference'])) {
			if(Configuration::get('SPECTER_PROD_PREFIX')!='') {
				$sql1 = 'UPDATE `'._DB_PREFIX_.'product` SET reference=CONCAT(\''.Configuration::get('SPECTER_PROD_PREFIX').'\',id_product) WHERE reference IS NULL OR reference=\'\';';				
				$sql2 = 'UPDATE `'._DB_PREFIX_.'product_attribute` SET reference = CONCAT(\''.Configuration::get('SPECTER_PROD_PREFIX').'\',id_product,\'-\',id_product_attribute) WHERE reference IS NULL OR reference=\'\';';
			} else {
				$sql1 = 'UPDATE `'._DB_PREFIX_.'product` SET reference=id_product WHERE reference IS NULL OR reference=\'\';';
				$sql2 = 'UPDATE `'._DB_PREFIX_.'product_attribute` SET reference = CONCAT(id_product,\'-\',id_product_attribute) WHERE reference IS NULL OR reference=\'\';';
			}
			if (Db::getInstance()->Execute($sql1)) {
                $this->confirmations[] = $this->l('Reference set');
            }
			if (Db::getInstance()->Execute($sql2)) {
                $this->confirmations[] = $this->l('Reference set');
            }
			if (Db::getInstance()->Execute('UPDATE `'._DB_PREFIX_.'product_attribute` pa SET wholesale_price = (SELECT wholesale_price FROM '._DB_PREFIX_.'product p WHERE p.id_product=pa.id_product) WHERE pa.wholesale_price=0;')) {
                $this->confirmations[] = $this->l('Wholesale price set');
            }
		}
        
        $sql = 'SELECT count(*)
		FROM `'._DB_PREFIX_.'product` p
		LEFT JOIN '._DB_PREFIX_.'product_shop ps USING(id_product) 
		LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (p.`id_product` = pl.`id_product`)
		LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (ps.`id_tax_rules_group` = tr.`id_tax_rules_group`
			AND tr.`id_country` = '.(int)Configuration::get('PS_COUNTRY_DEFAULT').'
			AND tr.`id_state` = 0)
		LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.`id_tax` = tr.`id_tax`)
		LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
		LEFT JOIN `'._DB_PREFIX_.'supplier` s ON (s.`id_supplier` = p.`id_supplier`)
		WHERE pl.`id_lang` = '.(int)Configuration::get('PS_LANG_DEFAULT');
		$num_of_products = Db::getInstance()->getValue($sql);
        
        $sql = 'SELECT count(*)
        FROM '._DB_PREFIX_.'product_attribute pa 
        LEFT JOIN '._DB_PREFIX_.'product p USING(id_product) 
        LEFT JOIN '._DB_PREFIX_.'product_shop ps USING(id_product) 
        LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (ps.`id_tax_rules_group` = tr.`id_tax_rules_group`AND tr.`id_country` = '.(int)Configuration::get('PS_COUNTRY_DEFAULT').' AND tr.`id_state` = 0) 
        LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.`id_tax` = tr.`id_tax`) 
        LEFT JOIN '._DB_PREFIX_.'product_lang pl USING(id_product) 
        LEFT JOIN '._DB_PREFIX_.'supplier s USING(id_supplier) 
        WHERE 1=1 AND pl.id_lang='.(int)Configuration::get('PS_LANG_DEFAULT');
		$num_of_products_attributes = Db::getInstance()->getValue($sql);

        $totalCalls = ceil( ($num_of_products / 100) );
        $totalCalls2 = ceil( ($num_of_products_attributes / 100) );
        $totalCalls_total = $totalCalls + $totalCalls2;
        
        $tab = 'AdminModules';
        $spectertoken = Tools::getAdminToken($tab.intval(Tab::getIdFromClassName($tab)).intval($this->context->employee->id));

        $ajaxtoken = Tools::getAdminTokenLite('AdminSpecterAjax');
        $specterurl = $this->context->link->getAdminLink('AdminSpecterAjax', true, [], [
            'employeeId' => (int) $this->context->cookie->id_employee,
            'lang' => (int) Configuration::get('PS_LANG_DEFAULT'),
            'action' => 'sendproducts',
            'token' => $ajaxtoken,
            'ajax' => true,
        ]);

    
        $this->context->smarty->assign(array(
            'num_of_products_attributes' => $num_of_products_attributes,
            'num_of_products' => $num_of_products,
            'totalCalls' => $totalCalls,
            'totalCalls2' => $totalCalls2,
            'specterurl' => $specterurl,
            'totalCalls_total' => $totalCalls_total,
            'toSendEachIteration' => 100,
            'postURL' => $_SERVER['REQUEST_URI'],
            'lang' => (int)Configuration::get('PS_LANG_DEFAULT'),
            'token' => $spectertoken,
            'id_employee' => (int)$this->context->cookie->id_employee,
        ));
        $tpl = $this->createTemplate('../../../../modules/specter/views/templates/admin/specter_productexport.tpl');
       
        return $tpl->fetch().parent::renderList();
    }
}