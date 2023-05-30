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

 use Prestaworks\Module\Specter\SpecterOrderHandler;

class AdminSpecterOrderManagerController extends ModuleAdminController
{
    public $_errors;
    public $_success;
    
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'order';
        $this->className = 'Order';
        $this->lang = false;
        $this->explicitSelect = true;
        $this->list_no_link = true;
        
        
        parent::__construct();
        
        $this->_select = '
        a.id_currency,
		a.id_order AS order_info,
		so.id_specter_order,
		so.id_specter_invoice,
		CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`,
		osl.`name` AS `osname`,
		os.`color`,
		IF((SELECT so.id_order FROM `'._DB_PREFIX_.'orders` so WHERE so.id_customer = a.id_customer AND so.id_order < a.id_order LIMIT 1) > 0, 0, 1) as new,
		country_lang.name as cname,
		IF(a.valid, 1, 0) badge_success';
        
        $this->_join = '
		LEFT JOIN `'._DB_PREFIX_.'specterorders` so ON (a.`id_order` = so.`id_order`)
		LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = a.`id_customer`)
		INNER JOIN `'._DB_PREFIX_.'address` address ON address.id_address = a.id_address_delivery
		INNER JOIN `'._DB_PREFIX_.'country` country ON address.id_country = country.id_country
		INNER JOIN `'._DB_PREFIX_.'country_lang` country_lang ON (country.`id_country` = country_lang.`id_country` AND country_lang.`id_lang` = '.(int)$this->context->language->id.')
		LEFT JOIN `'._DB_PREFIX_.'order_state` os ON (os.`id_order_state` = a.`current_state`)
		LEFT JOIN `'._DB_PREFIX_.'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = '.(int)$this->context->language->id.')';
        $this->_orderBy = 'id_order';
        $this->_orderWay = 'DESC';
        $this->_use_found_rows = true;
        
        $statuses = OrderState::getOrderStates((int)$this->context->language->id);
        foreach ($statuses as $status) {
            $this->statuses_array[$status['id_order_state']] = $status['name'];
        }
        
        $this->fields_list = array(
            'id_specter_order' => array(
                'title' => $this->l('Specter'),
                'align' => 'text-center',
                'filter_key' => 'so!id_specter_order',
                'class' => 'fixed-width-xs'
            ),
            'id_specter_invoice' => array(
                'title' => $this->l('Specter Invoice'),
                'align' => 'text-center',
                'filter_key' => 'so!id_specter_invoice',
                'class' => 'fixed-width-xs'
            ),
            'id_order' => array(
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs'
            ),
            'reference' => array(
                'title' => $this->trans('Reference', array(), 'Admin.Global')
            ),
            'customer' => array(
                'title' => $this->l('Customer'),
                'havingFilter' => true,
            ),
        );
        
        if (Configuration::get('PS_B2B_ENABLE')) {
            $this->fields_list = array_merge($this->fields_list, array(
                'company' => array(
                    'title' => $this->trans('Company', array(), 'Admin.Global'),
                    'filter_key' => 'c!company'
                ),
            ));
        }
        
        $this->fields_list = array_merge($this->fields_list, array(
            'total_paid_tax_incl' => array(
				'title' => $this->l('Total'),
				'type' => 'price',
                'currency' => true,
                'callback' => 'setOrderCurrency',
                'badge_success' => true
			),
			'payment' => array(
				'title' => $this->l('Payment')
			),
			'osname' => array(
				'title' => $this->l('State'),
                'type' => 'select',
				'color' => 'color',
                'list' => $this->statuses_array,
				'filter_key' => 'os!id_order_state',
				'filter_type' => 'int',
				'order_key' => 'osname'
			),
			'date_add' => array(
                'title' => $this->l('Date'),
                'align' => 'text-right',
                'type' => 'datetime',
                'filter_key' => 'a!date_add'
            ),
            'order_info' => array(
				'title' => $this->l('Order info'),
                'align' => 'text-center',
				'callback' => 'SpecterGetOrderInfo',
				'orderby' => false,
				'search' => false,
			),
        ));
            
        if (Country::isCurrentlyUsed('country', true)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
            SELECT DISTINCT c.id_country, cl.`name`
			FROM `'._DB_PREFIX_.'orders` o
			'.Shop::addSqlAssociation('orders', 'o').'
			INNER JOIN `'._DB_PREFIX_.'address` a ON a.id_address = o.id_address_delivery
			INNER JOIN `'._DB_PREFIX_.'country` c ON a.id_country = c.id_country
			INNER JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country` AND cl.`id_lang` = '.(int)$this->context->language->id.')
			ORDER BY cl.name ASC');
            
            $country_array = array();
            foreach ($result as $row) {
                $country_array[$row['id_country']] = $row['name'];
            }
            
            $part1 = array_slice($this->fields_list, 0, 3);
            $part2 = array_slice($this->fields_list, 3);
            $part1['cname'] = array(
                'title' => $this->trans('Delivery', array(), 'Admin.Global'),
                'type' => 'select',
                'list' => $country_array,
                'filter_key' => 'country!id_country',
                'filter_type' => 'int',
                'order_key' => 'cname'
            );
            $this->fields_list = array_merge($part1, $part2);
        }
        
        $this->shopLinkType = 'shop';
        $this->shopShareDatas = Shop::SHARE_ORDER;

        $this->bulk_actions = array(
			'sendOrders' => array(
                'text' => $this->l('Export orders to Specter'),
                'icon' => 'icon-send'
            )
		);
    }
    
    public function redirectWithNotifications($url)
    {
        $notifications = json_encode(array(
            'errors' => $this->_errors,
            'successes' => $this->_success,
        ));

        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['specter_notifications'] = $notifications;
        } elseif (session_status() == PHP_SESSION_NONE) {
            session_start();
            $_SESSION['specter_notifications'] = $notifications;
        } else {
            setcookie('specter_notifications', $notifications);
        }

        Tools::redirectAdmin($url);
    }
    
    protected function prepareNotifications()
    {
        $notifications = array(
            'errors' => $this->_errors,
            'successes' => $this->_success,
        );

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION['specter_notifications'])) {
            $notifications = array_merge($notifications, json_decode($_SESSION['specter_notifications'], true));
            unset($_SESSION['specter_notifications']);
        } elseif (isset($_COOKIE['specter_notifications'])) {
            $notifications = array_merge($notifications, json_decode($_COOKIE['specter_notifications'], true));
            unset($_COOKIE['specter_notifications']);
        }

        return $notifications;
    }

    public function renderList()
    {
        $this->context->smarty->assign(array(
            'specter_notifications' => $this->prepareNotifications(),
        ));
        $tpl = $this->createTemplate('../../../../modules/specter/views/templates/admin/specter_notifications.tpl');
       
        return $tpl->fetch().parent::renderList();
    }
    public function processBulkSendOrders()
    {
        if (Tools::isSubmit('submitBulksendOrdersorder')) {
            $specterArray = Tools::getValue('orderBox');
            foreach($specterArray AS $orderid)
            {
                $result = SpecterOrderHandler::sendOrderToSBM((int) $orderid);
                if ($result === true) {
                    $this->_success[] = $orderid." OK!";
                } else {
                    $this->_errors[] = $result;
                }
            }
            $url = "index.php?controller=".Tools::getValue('controller')."&token=".Tools::getValue('token');
            $this->redirectWithNotifications($url);
        }
    }
    
    public function SpecterGetOrderInfo($key, $params)
	{
        $adminOrderLink = $this->context->link->getAdminLink('AdminOrders', true, array(), array('id_order' => $key, 'vieworder' => ''));
        $tpl = $this->createTemplate('../../../../modules/specter/views/templates/admin/specter_order_link.tpl');
		$this->context->smarty->assign('adminOrderLink', $adminOrderLink);
		return $tpl->fetch();
	}
    
    public static function setOrderCurrency($echo, $tr)
    {
        $order = new Order($tr['id_order']);
        return Tools::displayPrice($echo, (int)$order->id_currency);
    }
}