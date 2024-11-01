// ( function( $ ) {


// 	$( function() {
// 		if ( 'undefined' === typeof woocommerce_admin ) {
// 			return;
// 		}

// 		// Get value of specific param from URL
// 		function getParamFromUrl(name) {
// 		    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.search);

// 		    if ( results == null ) {
// 		       return null;
// 		    } else {
// 		       return results[1] || 0;
// 		    }
// 		}

// 		var productProfit;

// 		// Ajax to get product profit from database
// 		if ((window.location.search.substring(1).includes('post=')) &&
// 			(window.location.search.substring(1).includes('action=edit'))) {
// 			var url = woocommerce_admin.ajax_url;
// 			var productId = getParamFromUrl('post');

// 			if (productId) {
// 				var data = {
// 					'action': 'get_product_profit',
// 					'productId': productId
// 				};

// 				$.post(url, data, function(response) {
// 					productProfit = response;
// 				});
// 			}
// 		}

// 		// Hide Sale price field
// 		$('#woocommerce-product-data #general_product_data ._sale_price_field').hide();

// 		// Field validation error tips
// 		$( document.body )

// 			.on( 'ta_add_error_tip_for_price', function( e, element, error_type ) {
// 				var offset = element.position();
// 				var error_message = 'The price should be greater than or equal to ' + productProfit;

// 				if ( element.parent().find( '.wc_error_tip' ).length === 0 ) {
// 					element.after( '<div class="wc_error_tip ' + error_type + '">' + error_message + '</div>' );
// 					element.parent().find( '.wc_error_tip' )
// 						.css( 'left', offset.left + element.width() - ( element.width() / 2 ) - ( $( '.wc_error_tip' ).width() / 2 ) )
// 						.css( 'top', offset.top + element.height() )
// 						.fadeIn( '100' );
// 				}
// 			})

// 			.on( 'ta_remove_error_tip_price', function( e, element, error_type ) {
// 				element.parent().find( '.wc_error_tip.' + error_type ).fadeOut( '100', function() { $( this ).remove(); } );
// 			})

// 			.on( 'change', '#_regular_price.wc_input_price[type=text]', function() {
// 				var regular_price_field = $( this );

// 				var regular_price = parseFloat(
// 					window.accounting.unformat( regular_price_field.val(), woocommerce_admin.mon_decimal_point )
// 				);

// 				if ( regular_price < productProfit ) {
// 					$( this ).val( '' );
// 				}
// 			})

// 			.on( 'keyup', '#_regular_price.wc_input_price[type=text]', function() {
// 				var regular_price_field = $( this );

// 				var regular_price = parseFloat(
// 					window.accounting.unformat( regular_price_field.val(), woocommerce_admin.mon_decimal_point )
// 				);

// 				if ( regular_price < productProfit ) {
// 					$( document.body ).triggerHandler( 'ta_add_error_tip_for_price', [ $(this), 'regular_less_than_profit_error' ] );
// 				} else {
// 					$( document.body ).triggerHandler( 'ta_remove_error_tip_price', [ $(this), 'regular_less_than_profit_error' ] );
// 				}
// 			});
// 			$(document.body).on('submit', 'form#ta_product_setting_form', function(e){
// 				e.preventDefault();
// 			});

// 	});

// })( jQuery );

jQuery(document).ready(function ($) {
	// Your code here
	$('form#ta_product_setting_form').on('submit', function (e) {
		e.preventDefault();
		
		var product_category = jQuery("select[name='product_category'] option:selected").val();
		var product_name = jQuery("input[name='product_name']").val();
		var product_ids = jQuery("input[name='product_ids']").val();
		
		if(product_name === '' && product_ids === '' && product_category === ''){
			$('.import_response').html('من فضلك اختار القسم اولا ثم اضغط على استيراد');
		} else if(product_name !== '' && product_ids !== ''){
			$('.import_response').html('من فضلك استخدم اسم المنتج او اكود المنتج، لا يمكن استيراد المنتج باستخدام العنصرين معا');
		} else {
			var formData = $(this).serializeArray();
			$.ajax({
				method: 'POST',
				url: ta_admin.ajaxURL,
				data: formData,
				beforeSend: function () {
					$('.import_running').removeClass('import_success').addClass('loading');
					$('.btn-import_products').attr('disabled', true);
					$('.import_response').html('');
				},
				success: function (response) {
					$('.import_running').removeClass('loading').addClass('import_success');
					$('.btn-import_products').removeAttr('disabled');
					$('.import_response').html(response);
				}
			});
		}
	});

	// js validation for product price with taager product
	if ('1' == ta_admin.taager_product) {
		$('#_regular_price, #ta__shipping_charge').on('change', function () {

			if (!$("#ta__shipping_charge").is(":checked")) {
				
				if (parseFloat($(this).val()) < parseFloat(ta_admin.taager_price)) {
					alert('Product price should be atleast ' + ta_admin.currency + ta_admin.taager_price + '.');
					$('#publish').attr('disabled', true);
				} else {
					$('#publish').removeAttr('disabled');
				}
				// do something if the checkbox is NOT checked
			} 
			else {
				//$("#ta__shipping_charge").find("checkbox").each(function(){
				
				
			
				if (parseFloat($('#_regular_price').val()) < parseFloat(ta_admin.ta_new_min_price)) {
					alert('Product price should be atleast ' + ta_admin.currency + ta_admin.ta_new_min_price + '.');
					$('#publish').attr('disabled', true);
				} else {
					$('#publish').removeAttr('disabled');
				}


			
		//});

			}



			
		});


	}
});

jQuery(document).ready(function ($) {
	
	jQuery('form#ta_shipping_setting_form').on('submit', function (e) {
		e.preventDefault();
		
		var formData = $(this).serializeArray();
		$.ajax({
			method: 'POST',
			url: ta_admin.ajaxURL,
			data: formData,
			beforeSend: function () {
				$('.ta_shipping_running').removeClass('shipping_success').addClass('loading');
				$('.btn-shippig_update').attr('disabled', true);
				$('.ta_shipping_response').addClass('ta_shipping_response--hidden')
			},
			success: function (response) {
				$('.ta_shipping_running').removeClass('loading').addClass('shipping_success');
				$('.btn-shippig_update').removeAttr('disabled');
				$('.ta_shipping_response').removeClass('ta_shipping_response--hidden');
				$('.ta_last_updated_time').html(response);
			}
		});
	});
	
});

//make taager price textbox readonly
jQuery(document).on('ready', function() {
	jQuery('.cs_disable_taager_field').attr('readonly', 'true');	
	jQuery('.cs_disable_taager_field').css('background-color', '#EEEEEE');	
});