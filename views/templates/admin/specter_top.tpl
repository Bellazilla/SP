{**
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
*}

<ul class="header-list component component-specter">
  <li class="dropdown dropdown-specter">
    <button class="dropdown-toggle dropdown-toggle-specter" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <img src="{$module_dir}views/templates/admin/img/specter_logo.png" alt="Specter logo">
      {if $nonsentorders > 0}<span>{$nonsentorders}</span>{/if}
    </button>
    <div class="dropdown-menu dropdown-menu-right dropdown-menu-specter">
      <div>{l s='20 last unsent orders' mod='specter'}</div>
      <ul>
        {foreach from=$nonsent_orders_list item=nonsent_order name=nonsent_orders_list}
          <li>
            <strong>{$nonsent_order.reference} (#{$nonsent_order.id_order})</strong><br>
            {$nonsent_order.payment} - {if isset($nonsent_order.current_state)}{$nonsent_order.current_state}{/if}
          </li>
        {/foreach}
      </ul>
      <a href="index.php?controller=AdminSpecterOrderManager&token={$spectertoken}">{l s='Open Specter module' mod='specter'}</a>
    </div>
  </li>
</ul>