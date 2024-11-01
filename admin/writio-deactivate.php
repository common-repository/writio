<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function writio_deactivate() {
  // deactivate server side
  $writio_account_email = get_option( 'writio_account_email' );

  if ( ! empty( $writio_account_email ) ) {
    // send a request to Writio on deactivate
    // encode payload
    $payload = json_encode( array(
      'siteUrl' => site_url(),
      'email'   => $writio_account_email,
    ) );


    wp_remote_post( "https://be.writio.com/v1/auth/uninstall", array(
      'headers' => array('Content-Type' => 'application/json; charset=utf8'),
      'body'    => $payload,
      'timeout' => 5
    ) );
  }
}