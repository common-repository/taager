<?php

defined( 'ABSPATH' ) || exit;

define ('EGYPT_ISO_CODE_3', 'EGY');
define ('EGYPT_CURRENCY', 'EGP');

define ('SAUDI_ISO_CODE_3', 'SAU');
define ('SAUDI_CURRENCY', 'SAR');

define ('EMIRATES_ISO_CODE_3', 'ARE');
define ('EMIRATES_CURRENCY', 'AED');

define ('IRAQ_ISO_CODE_3', 'IRQ');
define ('IRAQ_CURRENCY', 'IQD');

define ('EGYPT_PHONE_NUMBER_HINT', '01xxxxxxxxx');
define ('SAUDI_PHONE_NUMBER_HINT', '05xxxxxxxx');
define ('EMIRATES_PHONE_NUMBER_HINT', '05xxxxxxxx');
define ('IRAQ_PHONE_NUMBER_HINT', '07xxxxxxxxx');

define ('ALL_PRODUCTS_CATEGORY', 'جميع المنتجات');

function taager_get_country_iso_code() {
  return get_option ( 'ta_selected_country' );
}

function taager_get_currency() {
  switch ( taager_get_country_iso_code() ) {
    case EMIRATES_ISO_CODE_3:
      return EMIRATES_CURRENCY;
    case SAUDI_ISO_CODE_3:
      return SAUDI_CURRENCY;
    case EGYPT_ISO_CODE_3:
    default:
      return EGYPT_CURRENCY;
  }
}

function taager_get_country_phone_number_length() {
  switch ( taager_get_country_iso_code() ) {
    case EMIRATES_ISO_CODE_3:
      return strlen(EMIRATES_PHONE_NUMBER_HINT);
    case SAUDI_ISO_CODE_3:
      return strlen(SAUDI_PHONE_NUMBER_HINT);
    case EGYPT_ISO_CODE_3:
    default:
      return strlen(EGYPT_PHONE_NUMBER_HINT);
  }
}

function taager_get_country_phone_number_hint() {
  switch ( taager_get_country_iso_code() ) {
    case EMIRATES_ISO_CODE_3:
      return EMIRATES_PHONE_NUMBER_HINT;
    case SAUDI_ISO_CODE_3:
      return SAUDI_PHONE_NUMBER_HINT;
    case EGYPT_ISO_CODE_3:
    default:
      return EGYPT_PHONE_NUMBER_HINT;
    }
}