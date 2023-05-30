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

{if isset($specter_notifications)}
    {if isset($specter_notifications.errors)}
        {foreach from=$specter_notifications.errors item=$errormsg}
            <div class="alert alert-danger">{$errormsg}</div>
        {/foreach}
    {/if}
    {if isset($specter_notifications.successes)}
        {foreach from=$specter_notifications.successes item=$successmsg}
            <div class="alert alert-success">{$successmsg}</div>
        {/foreach}
    {/if}
{/if}