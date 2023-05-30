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
	<div class="col-md-4">
		<div class="panel card">
			<div class="card-header"><h3 class="card-header-title">
				<h3 class="card-header-title">
					<img src="{$specter_base_url}/modules/specter/assets/img/logo_small.png" title="{l s='Specter' mod='specter'}" alt="{l s='Specter' mod='specter'}" />{l s='Specter' mod='specter'}
					<a id="specter_debug_button"
						href="{$link_to_controller_specter}&showdebugdata=1"
						title="{l s='Show data sent to Specter' mod='specter'}"
						alt="{l s='Show data sent to Specter' mod='specter'}"
						class="fancybox ajax pull-right small"
						style="float: right;">
                		<img height="16" width="16" src="{$specter_base_url}/modules/specter/assets/img/debug.png" />{l s='Show data sent to Specter' mod='specter'}
					</a>
				</h3>
			</div>
			<div class="card-body">
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
</div>
<script>
$(document).ready(function() {
    $("#specter_debug_button").fancybox({
		'type' : 'iframe'
	});
});
</script>