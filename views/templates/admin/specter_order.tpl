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

<div class="row">
	<div class="col-lg-7">
		<div class="panel">
			<div class="panel-heading">
				<img src="../modules/specter/img/logo_small.png" title="{l s='Specter' mod='specter'}" alt="{l s='Specter' mod='specter'}"/>{l s='Specter' mod='specter'}
                <a id="specter_debug_button"
                href="{$posturlSpecter}&showdebugdata=1"
                title="{l s='Show data sent to Specter' mod='specter'}"
                alt="{l s='Show data sent to Specter' mod='specter'}"
                class="fancybox ajax pull-right small">
                <img height="16" width="16" src="../modules/specter/img/debug.png" />{l s='Show data sent to Specter' mod='specter'}</a>
			</div>
			{l s='Specter Customer:' mod='specter'}{$id_specter_customer}<br />
			{l s='Specter Order:' mod='specter'}{$id_specter}<br />
			{l s='Specter Invoice:' mod='specter'}{$specterInvoice}
			{if $id_specter < 1}
				<form action="{$posturlSpecter}" method="post">
				<input type="submit" class="btn btn-primary" name="sendmanualorder" value="{l s='Send order to Specter' mod='specter'}" />
				</form>
			{/if}            
		</div>
	</div>
</div>
<script>
$(document).ready(function() {
    $("#specter_debug_button").fancybox({
		'type' : 'iframe'
	});
});
</script>