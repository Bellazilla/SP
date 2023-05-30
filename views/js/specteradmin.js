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

var pwd_refer = "0";
var sent = 0;
var specter_call = 0;
var specter_call2 = 0;
    
$("#specter-admin .panel").hide();
$("#fieldset_general").show();


$('#spectertabs a').click(function(){
    $("#specter-admin .panel").hide();
    $("#fieldset_"+$(this).attr('data-fieldset')).show();
    $("#spectertabs li").removeClass('selected');
    $(this).parent().addClass('selected');
    if ($(this).attr("data-fieldset")=="specterlist_4") {
        $("#form-specter").show();
        $("#form-specter .panel").show();
    }
});
    
    

function startsendingproducts(isRestart)
{
    if (isRestart==1) {
        sent = 0;
        specter_call = 0;
        specter_call2 = 0
    }
	//var sendAll = $('#specter_send_all').val();
	if(specter_call == 0) {
        $('#products_is_sending').show();
        $('#start_sending_products').hide();
        $('#sendinfobox').show();
        $('.spectergreen').addClass("specterorage").removeClass("spectergreen");
        $('.specterred').addClass("specterorage").removeClass("specterred");
    }
    
    if(specter_call < totalCalls) {
        specter_call++;
        $.ajax({
            type: 'GET',
            dataType: "json",
            url: currentUrl+"&Call="+specter_call+"&totalCalls="+totalCalls,
            cache: false,
            async: true,
            success: function(jsonData)
            {
                console.log(jsonData);
                if (jsonData.success === true) {
                    $("#call_"+specter_call).addClass("spectergreen").removeClass("specterorage");
                } else {
                    $("#call_"+specter_call).addClass("specterred").removeClass("specterorage");
                }
                
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                $("#call_"+specter_call).addClass("specterred").removeClass("specterorage");
                
            },
            complete: function()
            {
                if(specter_call < totalCalls) {
                    startsendingproducts(0);
                }
            }
        });
    }
    
	if((specter_call >= totalCalls) && (specter_call2 < totalCalls2)) {
		specter_call2++;
		$.ajax({
            dataType: "json",
			type: 'GET',
			url: currentUrl+"&lang="+lang+"&Call="+specter_call2+"&totalCalls="+totalCalls2+"&attributes=true",
            async: true,
			cache: false,
			success: function(jsonData)
			{
                console.log(jsonData);
                if (jsonData.success === true) {
                    $("#call__"+specter_call2).addClass("spectergreen").removeClass("specterorage");
                } else {
                    $("#call__"+specter_call2).addClass("specterred").removeClass("specterorage");
                }
			},
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				$("#call__"+specter_call2).addClass("specterred").removeClass("specterorage");
			},
            complete: function()
            {
                if(specter_call2 < totalCalls2) {
                    startsendingproducts(0);
                }
            }
		});
	}
    
    if((specter_call >= totalCalls) && (specter_call2 >= totalCalls2) && (specter_call>0)) {
        $('#products_is_sending').hide();
        $('#start_sending_products').show();
    }
}