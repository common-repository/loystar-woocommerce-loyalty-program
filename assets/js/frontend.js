/* script */
var $ = jQuery;
$('.'+wcLsJson.dobInput).datepicker({
    changeYear: true,
       defaultDate:(new Date(1910, 1 , 1)),
       yearRange: wcLsJson.dateRange,
    dateFormat: 'dd/mm/yy'
});
/**
 * Handles the voucher qualification process
 */
/*
function wcLsVoucher(){
    const phone = $('.wc-ls-phone').val();
    const email = $('.wc-ls-email').val();
    if(phone != "" && email != ""){//both not empty, check
    // submit    
        $.ajax( {
         url: wcLsJson.url + 'wc_ls/v1/redeem/',
         method: 'POST',
        beforeSend: function ( xhr ) {
         xhr.setRequestHeader( 'X-WP-Nonce', wcLsJson.nonce );
        },
        data:{'phone' : phone, 'email' : email , 'nonce' : wcLsJson.nonce}
        } ).done( function ( resp ) {
         if(resp != ""){
            $('.wc-ls-redeem-comment').html(`<div class="wc-ls-alert wc-ls-success">${resp}</div>`);
         }
         return;
         } ).fail( function () {//do nothing, remain silent :)
         return;
        } );
    }
}

$(document).ready(function(){
    //call the function first
    //wcLsVoucher();
    //incase of changes
    //$('.wc-ls-phone').change(function(){wcLsVoucher();});
    //$('.wc-ls-email').change(function(){wcLsVoucher();});
});
*/
//$(document).ready(function(){
//set phone number properly for intl
// here, the index maps to the error code returned from getValidationError 
var wcLsPhoneErrorMap = [ "Invalid number", "Invalid country code", "Too short", "Too long", "Invalid number"];
//start
var wcLsPhoneIntl = $('.wc-ls-intl input').intlTelInput({
    /*initialCountry: "auto",
    geoIpLookup: function(callback) {
    $.get('https://ipinfo.io', function() {}, "jsonp").always(function(resp) {
    const countryCode = (resp && resp.country) ? resp.country : "";//asking for payment shaa,smh
    callback(countryCode);
    });
},//to pick user country*/
    utilsScript: wcLsJson.utilsScript
  });

/*if(wcLsJson.userPhone !== undefined ){
    wcLsPhoneIntl.intlTelInput("setNumber").val(wcLsJson.userPhone);
}*/

//some globals
var wcLsphoneErrMsg = "";

/**
 * Validates the phone number
 * 
 * @param intlTelInput input
 * @returns string or bool
 */
function wcLsValidatePhone(input){
    const phone = input;
    let result = false;
    if(phone.intlTelInput("isValidNumber") == true){
        result = phone.intlTelInput("getNumber");
    }
    else{
        let errorCode = phone.intlTelInput("getValidationError");
        wcLsphoneErrMsg = `Phone validation error: ${wcLsPhoneErrorMap[errorCode]}`;
    }
    return result;
}
/**
 * trigger the loyalty submit the form
 */
function wcLsLoyaltySubmit(){
    let formBody = 'form.ls-loyalty-widget-box';
    $(formBody).prepend('<div class="ls-blanket center"><a href="#no-click" class="ls-nice-load-feel"><i class="fa fa-gear fa-spin fa-2x"></i></a> </div>');
    $(formBody).css('position','relative');
	let wcLsFormData = {};
    let wcLsForm = $(formBody).serializeArray();
    const alertBox = `${formBody} .wc-ls-alert`;
    //check if it's empty before proceeding
    if(wcLsForm === undefined || wcLsForm.length < 1){
        wcLsShowWidgetNote('Please fill in the details','danger');
        wcLsRemoveOverlay(formBody);
        return;
    }
    let _formData = {};
    for (let i = 0; i < wcLsForm.length; i++){
        _formData[wcLsForm[i]['name']] = wcLsForm[i]['value'];
    }
    //add some extras
     _formData['u_id'] = wcLsJson.userId;
     _formData['nonce'] = wcLsJson.nonce;
     _formData['phone'] = wcLsValidatePhone(wcLsPhoneIntl);
     if(_formData['phone'] == false){//error
        wcLsShowWidgetNote(wcLsphoneErrMsg,'danger');
        wcLsRemoveOverlay(formBody);
        return;
     } 
     wcLsFormData = _formData;//JSON.stringify(_formData);
    $.ajax( {
        url: wcLsJson.url + 'wc_ls/v1/loyalty_widget/',
        method: 'POST',
        beforeSend: function ( xhr ) {
           xhr.setRequestHeader( 'X-WP-Nonce', wcLsJson.nonce );
        },
        data:wcLsFormData
    } ).success( function ( response ) {
            wcLsShowWidgetNote(response,'success');
            wcLsRemoveOverlay(formBody);
            window.setTimeout(function(){
                window.location.href = wcLsJson.pageUrl;
            },3000);
    } ).fail( function (jqXHR) {
        const stateCode = jqXHR.status;
        let msg = "";
        response = JSON.parse(jqXHR.responseText);
        if(stateCode !== 422 && stateCode !== 503)
            msg = "Sorry, an internal error occured, try again later.";
        else
            msg = response;
        wcLsShowWidgetNote(msg,'danger');
        wcLsRemoveOverlay(formBody);
            return;
    } );
}
/**
 * Show the alert note
 * @param {string} msg 
 * @param {string} type 
 */
function wcLsShowWidgetNote(msg,type = "success"){
    const alertBox = `form.ls-loyalty-widget-box .wc-ls-alert`;
    $(alertBox).attr('class','wc-ls-alert ls-'+type);
    $(alertBox).text(msg);
    $(alertBox).removeClass('hidden');
    $(alertBox).fadeIn('slow');
}
/**to remove the blanket */
function wcLsRemoveOverlay(parentElement = 'form.ls-loyalty-widget-box'){
    $(parentElement+' .ls-blanket').remove();
}
//for woocommerce
var wcLsCheckoutForm = $('.woocommerce-checkout');
wcLsCheckoutForm.on('checkout_place_order',function(){
    let $ = jQuery;
    let phoneNumber = wcLsValidatePhone(wcLsPhoneIntl);
    if(phoneNumber != false){//phone is valid
        $('.woocommerce-checkout input#billing_phone').val(phoneNumber);//set the real value so it submits it along
        if($('#wc-ls-phone-valid-field').length == 0){//append
            wcLsCheckoutForm.append(`<input id="wc-ls-phone-valid-field" value="${phoneNumber}" type="hidden" name="${wcLsJson.phoneValidatorName}">`);
        }
    }
    else{
        if($('#wc-ls-phone-valid-field-err-msg').length == 0){//append
        wcLsCheckoutForm.append(`<input id="wc-ls-phone-valid-field-err-msg" value="${wcLsphoneErrMsg}" type="hidden" name="${wcLsJson.phoneValidatorErrName}">`);
        }
    }
});

//});