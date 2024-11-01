<?php

require plugin_dir_path( __FILE__ ) . 'mixpanel-php/lib/Mixpanel.php';

function ta_set_mixpanel_user_profile($ta_user) {
  if($ta_user) {
    // create/update a profile for user
    $GLOBALS['mp']->people->set($ta_user->TagerID, array(
      '$first_name'   => $ta_user->firstName,
      '$last_name'    => $ta_user->lastName,
      '$name'         => $ta_user->firstName . ' ' . $ta_user->lastName,
      '$email'        => $ta_user->email,
      '$phone'        => $ta_user->phoneNum,
      'taager_id'     => $ta_user->TagerID,
      'Loyalty Program'  => $ta_user->loyaltyProgram? $ta_user->loyaltyProgram->loyaltyProgram : 'N/A',
    ), 0);
    $GLOBALS['mp']->identify($ta_user->TagerID);
    $GLOBALS['mp']->flush();
  }
}

function ta_track_event($event_name, $properties = array()) {
  ta_set_mixpanel_user_profile(get_option('ta_user'));
	taager_plugin_upgrade_function();

  $properties = array_merge($properties, array(
    "Domain" => $_SERVER['SERVER_NAME'],
    "Plugin version" => get_option( 'ta_plugin_version' ),
    "Wordpress version" => get_bloginfo('version'),
    "Plugin selected country" => taager_get_country_iso_code(),
  ));
}

$mp = Mixpanel::getInstance('e5231d1e1635cadae71810dfe573f369');
