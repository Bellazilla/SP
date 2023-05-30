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

<div class="panel product-tab">
<h3>{l s='Specter' mod='specter'}</h3>
{l s='Click here to send the product to SBM, do not forget to set a reference before you do.' mod='specter'}<br />
<input type="button" class="btn btn-primary" onclick="javascript:sendProduct();" value="{l s='Send to SBM' mod='specter'}">
<div id="product_result"></div>

{literal}
<script>
function sendProduct()
{
	$.ajax({
		type: 'GET',
		url: '{/literal}{$specterurl}{literal}',
		cache: false,
		success: function(jsonData)
		{
			$('#product_result').html(jsonData);
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) 
		{
			alert("TECHNICAL ERROR: unable to access file.\n\nDetails:\nError thrown: " + XMLHttpRequest + "\n" + 'Text status: ' + textStatus);
		}
	});
}
</script>
{/literal}
</div>