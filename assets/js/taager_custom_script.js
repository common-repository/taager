jQuery(document).ready(function(){
	const phoneNumberFieldIds = ['billing_phone', 'billing_phone2'];
	phoneNumberFieldIds.forEach((phoneNumberFieldId) => {
		handlePhoneNumberFieldValidity(phoneNumberFieldId);
	});

	jQuery('#billing_state').on('change', onProvinceChange);

	showMoyasarFormOnPrepaidSelected();
});

function handlePhoneNumberFieldValidity (formFieldId, isRequired) {
	jQuery(`#${formFieldId}`).on('blur change', function (){
		const phoneNumber = jQuery(this).val();
		const formRow = jQuery(this).parents('.form-row');

		const phoneNumberLength = document.getElementById(formFieldId).attributes.maxlength.nodeValue;
		const errorMessage = document.getElementById(formFieldId).attributes.errorMessage.nodeValue;

		if (phoneNumber || isRequired) {
			const phoneRegex = new RegExp('^\\d{' + phoneNumberLength + '}$');
			if(!phoneRegex.test(phoneNumber)){
				if(jQuery(`#${formFieldId}__error_message`).length == 0){
					jQuery(this).parent().append(`<span id="${formFieldId}__error_message" class="error" style="color:red">${errorMessage}</span>`);
				}
				formRow.addClass('woocommerce-invalid'); 
				formRow.removeClass('woocommerce-validated');
			} else {
				jQuery(this).parent().find(`#${formFieldId}__error_message`).remove();
				formRow.addClass('woocommerce-validated');
			}
		} else {
			jQuery(this).parent().find(`#${formFieldId}__error_message`).remove();
			formRow.removeClass('woocommerce-validated');
		}
	});
}

function showMoyasarFormOnPrepaidSelected() {
	jQuery("input[name='payment_method']").on('change', function($event) {
		let selectedPaymentMethod = $event.target.value;
		if(selectedPaymentMethod === 'COD') {
			jQuery("#payment-details").addClass("hidden");
			jQuery("#place_order").prop('disabled', false);
			removeDistrictAndZonesFromCheckoutForm();
		} else if (selectedPaymentMethod === 'creditcard'){
			jQuery("#payment-details").removeClass("hidden");
			jQuery("#place_order").prop('disabled', true);
			appendDistrictAndZonesToCheckoutForm();
		}
	});
}

function removeDistrictAndZonesFromCheckoutForm() {
	jQuery('#billing_zone_field').remove();
	jQuery('#billing_district_field').remove();
}

function appendDistrictAndZonesToCheckoutForm() {
	jQuery.ajax(({
		type: 'POST',
		url: window.location.href.match(/^.+\.com/)[0] + '/wp-admin/admin-ajax.php',
		data: {
			action: 'taager_get_zones_and_districts_label'
		},
		success: function(response) {
			const { zone_label, district_label} = JSON.parse(response);
		
			const districtsSelectionElement = jQuery(`
				<p class="form-row form-row-wide address-field" id="billing_district_field" data-priority="87">
					<label for="billing_district" class="">${district_label}<abbr class="required" title="required">*</abbr></label>
					<span class="woocommerce-input-wrapper">
						<select name="billing_district" id="billing_district" class="select" autocomplete="address-level3">
							<option value=""></option>
						</select>
					</span>
				</p>
			`);

			const zonesSelectionElement = jQuery(`
				<p class="form-row form-row-wide address-field" id="billing_zone_field" data-priority="86">
					<label for="billing_zone" class="">${zone_label}<abbr class="required" title="required">*</abbr></label>
					<span class="woocommerce-input-wrapper">
						<select name="billing_zone" id="billing_zone" class="select" autocomplete="address-level2">
							<option value=""></option>
						</select>
					</span>
				</p>
			`);
		
			jQuery('#billing_state_field')[0].after(zonesSelectionElement[0], districtsSelectionElement[0]);
			onProvinceChange();
			jQuery('#billing_zone').on('change', onZoneChange);
		}}));
}

function onProvinceChange() {
	const selectedProvinceId = jQuery('#billing_state')[0]?.value;

	if (jQuery('#billing_zone').length){
		if( selectedProvinceId ) {
			jQuery.ajax(({
				type: 'POST',
				url: window.location.href.match(/^.+\.com/)[0] + '/wp-admin/admin-ajax.php',
				data: {
					selected_province_id: selectedProvinceId,
					action: 'taager_get_selected_province_zones'
				},
				success: function(response) {
					replaceSelectOptionsWithEmptyOption('billing_zone');
					for(const zone of JSON.parse(response)) {
						jQuery('#billing_zone').append(new Option(zone.zone_name, zone.zone_id));
					}

					replaceSelectOptionsWithEmptyOption('billing_district');
				}
			}));
		} else {
			replaceSelectOptionsWithEmptyOption('billing_zone');
			replaceSelectOptionsWithEmptyOption('billing_district');
		}
	}
}

function onZoneChange() {
	const selectedZoneId = jQuery('#billing_zone')[0]?.value;
	if(selectedZoneId) {
		jQuery.ajax(({
			type: 'POST',
			url: window.location.href.match(/^.+\.com/)[0] + '/wp-admin/admin-ajax.php',
			data: {
				selected_zone_id: selectedZoneId,
				action: 'taager_get_selected_zone_districts'
			},
			success: function(response) {
				replaceSelectOptionsWithEmptyOption('billing_district');
				for(const district of JSON.parse(response)) {
					jQuery('#billing_district').append(new Option(district.district_name, district.district_id));
				}
			},
		}));
	} else {
		replaceSelectOptionsWithEmptyOption('billing_district');
	}
}

function replaceSelectOptionsWithEmptyOption(elementId) {
	jQuery(`#${elementId}`).children().remove();
	jQuery(`#${elementId}`).append(new Option('', ''));
}
