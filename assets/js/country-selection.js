jQuery(document).ready(function ($) {

  jQuery('form#ta_country_selection_form').on('submit', function (e) {
		var selected_country = jQuery("select[name='country_selection'] option:selected").val();
    // Disable button after submitting country selection 
    if( selected_country ) {
      $('.btn-country-selection').attr('disabled', true);
    }
  });

});
