<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function writio_activate() {
  // On activate, indicate that the plugin needs redirection
  add_option( 'writio_do_activation_redirect', true );
}

function writio_redirect() {
  // Redirect, then delete the option so it only does the redirect once
  if ( get_option( 'writio_do_activation_redirect', false ) ) {
    delete_option( 'writio_do_activation_redirect' );
    if( !isset( $_GET['activate-multi'] ) ) {
      wp_redirect( "options-general.php?page=writio" );
      exit;
    }
  }
}

function create_new_author_user() {
  $username = 'writio.com';
  $random_number = rand(12, 16);
  $password = wp_generate_password( $random_number, true );
  $email = 'noreply@writio.com';
  $first_name = 'Writio.com';
  $last_name = 'Author';

  if ( ! username_exists( $username ) && ! email_exists( $email ) ) {
    $user_id = wp_create_user( $username, $password, $email );

    if ( is_int( $user_id ) ) {
      $wp_user_object = new WP_User( $user_id );
      $wp_user_object->set_role( 'author' );

      // Set the first and last name of the author
      wp_update_user(
        array(
          'ID'         => $user_id,
          'nickname'   => $first_name,
          'first_name' => $first_name,
          'last_name'  => $last_name
        )
      );
    }
  }
}