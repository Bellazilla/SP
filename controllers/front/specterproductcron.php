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

use Prestaworks\Module\Specter\SpecterConnector;
use Prestaworks\Module\Specter\SpecterProductHandler;

class specterSpecterProductCronModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();

        $secret_key = Tools::hash('SPECTER_SBMID'.Configuration::get('SPECTER_SBMID'));
        $cron_key = Tools::getValue('key');

        if ($secret_key != $cron_key) {
            exit;
        }

        $results = [];
        $sql = "SELECT DISTINCT id_product FROM "._DB_PREFIX_."specterproductstoupdate";
        $products_to_send_to_specter = Db::getInstance()->executeS($sql);
        foreach($products_to_send_to_specter as $product_to_send_to_specter) {
            $id_product = (int) $product_to_send_to_specter["id_product"];
            if ($id_product > 0) {
                $results[] = SpecterProductHandler::SendProductAttributesToSpecter(0, $id_product);
                $results[] = SpecterProductHandler::SendProducts(0, $id_product);
                $sql_delete = "DELETE FROM "._DB_PREFIX_."specterproductstoupdate WHERE id_product=$id_product";
                Db::getInstance()->execute($sql_delete);
            }
        }

        echo "Done";
        exit;
    }
}