<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Listen for changes to authors and categories and trigger the article data request endpoint
function article_data_listener() {
  add_action( 'user_register', 'trigger_article_data_request' );
  add_action( 'profile_update', 'trigger_article_data_request' );
  add_action( 'delete_user', 'trigger_article_data_request' );
  add_action( 'create_category', 'trigger_article_data_request' );
  add_action( 'edit_category', 'trigger_article_data_request' );
  add_action( 'delete_category', 'trigger_article_data_request' );
}

function trigger_article_data_request() {
  $writio_account_email = get_option( 'writio_account_email' );

  if ( ! empty( $writio_account_email ) ) {
    $payload = json_encode( array(
      'siteUrl'       => site_url(),
      'email'         => $writio_account_email,
    ) );

    // send a request to Writio to update the article data
    wp_remote_post( "https://be.writio.com/v1/article/data", array(
      'headers' => array( 'Content-Type' => 'application/json; charset=utf8' ),
      'body'    => $payload,
      'timeout' => 5
    ) );
  }
}