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

{if $successList}
    {foreach from=$successList item=successItem}
        <div class="alert alert-success">
            {$successItem}
        </div>
    {/foreach}
{/if}
{if $postErrors}
    {foreach from=$postErrors item=errorItem}
        <div class="alert alert-warning">
            {$errorItem}
        </div>
    {/foreach}
{/if}
{if isset($isSaved) && $isSaved}
	<div class="alert alert-success">
		{l s='Settings updated' mod='specter'}
	</div>
{/if}

<div class="bootstrap">
    <div class="tab-content">
        <div id="pane1" class="tab-pane active">
            <div class="sidebar col-lg-2">
                <ul id="spectertabs" class="nav nav-tabs">
                    <li class="nav-item selected"><a href="javascript:;" title="{l s='General settings' mod='specter'}" data-panel="1" data-fieldset="general"><i class="icon-AdminAdmin"></i>{l s='General settings' mod='specter'}</a></li>
                    <li class="nav-item"><a href="javascript:;" title="{l s='Call backs' mod='specter'}" data-panel="1" data-fieldset="callbacks_1"><i class="icon-AdminAdmin"></i>{l s='Call backs' mod='specter'}</a></li>
                    <li class="nav-item"><a href="javascript:;" title="{l s='Instructions' mod='specter'}" data-panel="1" data-fieldset="cronjob_3"><i class="icon-question-circle"></i> {l s='Instructions' mod='specter'}</a></li>
                </ul>
            </div>
            <div id="specter-admin" class="col-lg-10">
                {$specterform}
                
                
                <div id="fieldset_cronjob_3" class="panel" style="display: none;">
                    <div class="panel-heading"><i class="icon-question-circle"></i> {l s='Instructions' mod='specter'}</div>
                    <div class="form-wrapper">
                    <b>{l s='This module allows you to connect your shop to SBM.' mod='specter'}</b><br />
                    {l s='Some of the features includeded are:' mod='specter'}
                    <ul>
                        <li>&gt; {l s='Automatic transfer of orders.' mod='specter'}</li>
                        <li>&gt; {l s='Automatic transfer of customers.' mod='specter'}</li>
                        <li>&gt; {l s='Transfer products.' mod='specter'}</li>
                        <li>&lt; {l s='Realtime sync of stock.' mod='specter'}</li>
                        <li>&lt; {l s='PDF invoice download.' mod='specter'}</li>
                        <li>&lt; {l s='Product updates.' mod='specter'}</li>
                        <li>&lt; {l s='Order status change.' mod='specter'}</li>
                    </ul>
                    <b>{l s='The following steps needs to be completed.' mod='specter'}</b>
                    <ul>
                        <li>1. {l s='Enter your API information, this is provided by Specter.' mod='specter'}</li>
                        <li>2. {l s='Set reference on all products/combinations.' mod='specter'}</li>
                        <li>3. {l s='Send products to Specter (optional).' mod='specter'}</li>
                        <li>4. {l s='Set wich callbacks to activate.' mod='specter'}</li>
                        <li>5. {l s='Match tax rules (if price updates are used).' mod='specter'}</li>
                        <li>6. {l s='Advacned stock management needs to be turned off.' mod='specter'}</li>
                        <li>7. {l s='Set up cron rule according to information below.' mod='specter'}</li>
                    </ul>
                
                        <br />
                        <h3 id="number_of_calls_made">{l s='Cronjob instructions' mod='specter'}</h3>
                        <p>{l s='You need to set up a cronjob that calls the following url %s as often as you want syncs to be performed.' sprintf=$cronurl1 mod='specter'}
                        <br />{l s='This may depend on amount of orders, hosting performance and other factors. We recommend every ten minutes.' mod='specter'}
                        <br />
                        {l s='Cronjob URL:' mod='specter'} <strong>{$cronurl1}</strong>
                        <br />
                        </p>
                        <a href="{$cronurl1}" target="_blank">{l s='Click here to run cronjob once right now.' mod='specter'}</a>
                        <br />
                        <br />
                        <p>{l s='If you want automatic sending of product data to specter, set another cronjob for this url %s as often as you want syncs to be performed.' sprintf=$cronurl2 mod='specter'}
                        <br />{l s='Cronjob URL:' mod='specter'} <strong>{$cronurl2}</strong>
                        <br />{l s='This will send all products that have been changed since last time the url was called.' mod='specter'}
                        <br />
                        </p>
                        <a href="{$cronurl2}" target="_blank">{l s='Click here to run cronjob once right now.' mod='specter'}</a>

                        <br />
                        <br />
                        <br />
                        <h3>{l s='Current carrier references' mod='specter'}</h3>
                        <p>{l s='The carrier id_reference values listed below are sent to specter as externaldeliverymethod and can be used in specter to map the customer choice to a carrier option in the TMA connections for Unifuan.' sprintf=$cronurl1 mod='specter'}
                        <table class="table">
                            <thead>
                                <tr>
                                    <td>{l s='Reference' mod='specter'}</td>
                                    <td>{l s='Name' mod='specter'}</td>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$carrieroptions item=carrieroption}
                                    <tr scope="row">
                                        <td>{$carrieroption.id_reference}</td>
                                        <td>{$carrieroption.name}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>

                    <div class="panel-footer"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../modules/specter/views/js/specteradmin.js" type="text/javascript"></script>
<link type="text/css" rel="stylesheet" href="../modules/specter/views/css/specter_admin.css" />
