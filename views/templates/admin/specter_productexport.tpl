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

<script src="../modules/specter/views/js/specteradmin.js" type="text/javascript"></script>
<link type="text/css" rel="stylesheet" href="../modules/specter/views/css/specter_admin.css" />

<script type="text/javascript">
    var nbr = {$num_of_products};
	var nbr_attribs = {$num_of_products_attributes};
	var toSendEachIteration = {$toSendEachIteration};
    
    var lang = {$lang};
    var totalCalls = {$totalCalls};
	var totalCalls2 = {$totalCalls2};
	var totalCalls_total = {$totalCalls_total};
    {* var currentUrl = "../modules/specter/library/specterSendProducts.php?token={$token}&employeeId={$id_employee}"; *}
    var currentUrl = "{$specterurl}";
</script>



<div id="fieldset_products_2" class="panel">
    <div class="panel-heading"><i class="icon-AdminParentPreferences"></i>{l s='Product handling' mod='specter'}</div>
    <div class="form-wrapper">
        <h3 id="number_of_calls_made">{l s='Send to specter' mod='specter'}</h3>
        <div id="sendinfobox" style="display:none">
        <h4 id="number_of_calls_made">{l s='Product calls' mod='specter'}<img src="../modules/specter/img/ajax-loader.gif" id="products_is_sending" style="display:none" /></h4>
        {assign var=loop value=1}
        {while $totalCalls >= $loop}
            <span class="specterorage" id="call_{$loop}"></span>
          {assign var=loop value=$loop+1}
        {/while}
        {assign var=loop value=1}
        {while $totalCalls2 >= $loop}
            <span class="specterorage" id="call__{$loop}"></span>
          {assign var=loop value=$loop+1}
        {/while}
        <div style="clear:both;"></div>
        </div>
        
        <button id="start_sending_products" onclick="startsendingproducts(1);" class="btn btn-default" type="button" value="0">
        <i class="icon icon-angle-right icon-lg"></i>
        {l s='Send all products' mod='specter'}
        </button>
        <br />
        <br />
        <br />
        <h3 id="number_of_calls_made">{l s='Autoset values' mod='specter'}</h3>
        <form action="{$postURL}" method="post">
        <button id="btnSubmit_setreference" name="btnSubmit_setreference" class="btn btn-default" type="submit" value="0">
        <i class="icon icon-angle-right icon-lg"></i>
        {l s='Set missing reference' mod='specter'}
        </button>
        </form>
        
    </div>
    <div class="panel-footer"></div>
</div>