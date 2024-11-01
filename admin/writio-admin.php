<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function writio_settings_init() {
  add_settings_section(
   'writio_settings_section',
   'Connect to Writio',
   'writio_settings_section_callback',
   'writio'
  );

  add_settings_field(
   'writio_account_email',
   'Writio Account Email',
   'writio_account_email_settings_callback',
   'writio',
   'writio_settings_section'
  );

  add_settings_field(
   'writio_pull_articles',
   'Article Publishing',
   'writio_account_pull_unpublished_articles_callback',
   'writio',
   'writio_settings_section'
  );

  register_setting(
   'writio_settings',
   'writio_account_email',
   'writio_sanitize_callback',
   'writio'
  );
}

function writio_settings_section_callback() {
  $writio_account_email = get_option( 'writio_account_email' );
  $message = "Enter the email associated with your Writio account below.";
  $message_color = "#2271b1";

  if ( ! empty( $writio_account_email ) ) {
    // send a request to Writio to validate the account
    // encode payload
    $payload = json_encode( array(
     'siteUrl'       => site_url(),
     'email'         => $writio_account_email,
     'restUrl'       => get_rest_url(),
     'pluginVersion' => WRITIO_VERSION,
    ) );

    // validate the Writio email entered by the user
    $writioResponse = wp_remote_post( "https://be.writio.com/v1/auth/account", array(
      'headers' => array( 'Content-Type' => 'application/json; charset=utf8' ),
      'body'    => $payload,
      'timeout' => 5
    ) );

    if ( ! is_wp_error( $writioResponse ) ) {
      $responseBody = wp_remote_retrieve_body( $writioResponse );
      $parsed       = json_decode( $responseBody );

      if ( ! is_null( $parsed->success ) && $parsed->success ) {
        $message = $parsed->message ? $parsed->message : "Your Writio account is connected!";
        $message_color = "green";
      } else {
        $message = $parsed->error ? $parsed->error : "An error occurred while connecting your account. Please try again later.";
        $message_color = "red";
      }
    } else {
      $message = "An error occurred while connecting your account. Please try again later.";
      $message_color = "red";
    }
  }

  echo '<p style="color: ' . esc_attr($message_color) . ';">' . esc_html($message) . '</p>';
}

function writio_account_email_settings_callback() {
  $writio_account_email = get_option( 'writio_account_email' );
  echo '<input type="text" name="writio_account_email" value="' . esc_attr($writio_account_email) . '">';
}

function writio_account_pull_unpublished_articles_callback() {
  $writio_account_email = get_option( 'writio_account_email' );
  if ( ! empty( $writio_account_email ) ) {
    ?>
    <a class="button button-secondary" href="<?php echo '?page=writio&retry_publishing=1'; ?>">
      Retry Now
    </a>
    <p>This will retrigger any articles that are waiting to be published.</p>
    <?php
    if ( isset( $_GET['retry_publishing'] ) ) {
      pull_unpublished_articles_admin();
      ?>
      <div id="message" class="updated notice is-dismissible">
        <p>
          <strong>
            <?php _e( 'Success! A request to publish articles has been submitted.', 'writio' ); ?>
          </strong>
        </p>
      </div>
      <?php
    }
  } else {
      echo esc_html("After you have successfully connected to Writio, you can retrigger any articles that are waiting to be published.");
  }
}

function writio_add_settings_page() {
  add_options_page(
   'Writio Account Settings',
   'Writio',
   'manage_options',
   'writio',
   'writio_settings_page_callback'
  );
}
add_action('admin_menu', 'writio_add_settings_page');

function writio_settings_page_callback() {
  ?>
  <div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <?php settings_errors(' writio_settings '); ?>
    <form method="post" action="options.php">
      <?php settings_fields( 'writio_settings' ); ?>
      <?php do_settings_sections( 'writio' ); ?>
      <?php submit_button( 'Connect', 'primary', 'submit', false); ?>
    </form>
  </div>
  <?php
}

function writio_sanitize_callback($value) {
    return sanitize_text_field($value);
}

