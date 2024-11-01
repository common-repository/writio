<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Register Restful endpoints
function writio_api_init() {
  register_rest_route( 'writio-api/v1', '/writio-request', array(
    'methods'             => 'POST',
    'callback'            => 'writio_api_callback',
    'permission_callback' => 'writio_api_permission_callback',
    'timeout'             => 120,
  ) );
  register_rest_route( 'writio-api/v1', '/writio-token', array(
    'methods'             => 'GET',
    'callback'            => 'writio_token_callback',
    'permission_callback' => 'writio_token_permission_callback',
  ) );
  register_rest_route( 'writio-api/v1', '/writio-data', array(
    'methods'             => 'GET',
    'callback'            => 'writio_post_data_callback',
    'permission_callback' => 'writio_api_permission_callback',
  ) );
}

// API endpoint to create posts generated using Writio
function writio_api_callback( $request ) {
  // get the post data from the request
  $log_id = $request->get_param( 'logId' );
  $article_id = $request->get_param( 'articleId' );
  $post_title = $request->get_param( 'title' );
  $post_content = $request->get_param( 'content' );
  $post_status = $request->get_param( 'status' );
  $images = $request->get_param( 'images' );
  $featured_image = $request->get_param( 'featured_image' );
  $author_id = $request->get_param( 'authorId' );
  $category_id = $request->get_param( 'categoryId' );

  return writio_post_processing( $log_id, $article_id, $post_title, $post_content, $post_status, $images, $featured_image, $author_id, $category_id );
}

// API endpoint to validate use of the plugin endpoints and generate a token for subsequent requests
function writio_token_callback( $request ) {
  // get the auth key sent with the request
  $authKey = $request->get_param( 'auth' );

  // get the Writio account email from settings
  $writioAccountEmail = get_option( 'writio_account_email' );

  if ( empty ( $writioAccountEmail ) ) {
    $response = array(
      'message' => 'Email not found',
      'success' => false,
    );
    return new WP_REST_Response( $response );
  }

  // encode payload
  $payload = json_encode( array(
    'authKey' => $authKey,
    'siteUrl' => site_url(),
    'email'   => $writioAccountEmail
  ) );

  // validate the writio token
  // prevent use of this endpoint by requests not from writio.com
  $writioResponse = wp_remote_post( "https://be.writio.com/v1/auth/verify", array(
    'headers' => array( 'Content-Type' => 'application/json; charset=utf8' ),
    'body'    => $payload,
    'timeout' => 5
  ) );

  // if a writio auth key was obtained from the verify endpoint
  // create a unique token hash and return it
  if ( ! is_wp_error( $writioResponse ) ) {
    $responseBody = wp_remote_retrieve_body( $writioResponse );
    $parsed       = json_decode( $responseBody );

    if ( ! is_null( $parsed->data ) ) {

      $expires = $parsed->data->expires;
      $writioAuthKey = $parsed->data->writioAuthKey;
      $token = hash_hmac( 'sha256', $writioAuthKey . ':' . $expires, AUTH_KEY ) . ':' . $expires;

      $response = array(
        'token'   => $token,
        'success' => true
      );
    } else {
      $response = array(
        'message' => 'Error authenticating request',
        'success' => false
      );
    }
  } else {
    $response = array(
      'message' => 'Error getting response from writio.com',
      'success' => false
    );
  }

  return new WP_REST_Response( $response );
}

function writio_post_data_callback( $request ) {
    // get all author and categories to send back for Writio to use as options
    $authors = array();
    $users = get_users();
    foreach ( $users as $user ) {
      if ( $user->roles && ( in_array( 'author', $user->roles ) || in_array( 'administrator', $user->roles ) || in_array( 'editor', $user->roles ) || in_array( 'contributor', $user->roles )) ) {
        $author = array(
          'authorId' => $user->ID,
          'author'   => $user->display_name,
        );
        $authors[] = $author;
      }
    }

    $categories = array();
    $args = array(
      'hide_empty' => false,
    );
    $terms = get_terms( 'category', $args );
    foreach ( $terms as $term ) {
      $category = array(
        'categoryId' => $term->term_id,
        'category'   => $term->name,
      );
      $categories[] = $category;
    }

    $response = array(
      'authors'       => $authors,
      'categories'    => $categories,
      'restUrl'       => get_rest_url(),
      'pluginVersion' => WRITIO_VERSION,
    );

    return new WP_REST_Response( $response );
}

function writio_api_permission_callback( $request ) {
  // get the writio token sent with the request to post content
  $headers = $request->get_headers();
  $token = isset( $headers['x_writio_token'] ) ? $headers['x_writio_token'][0] : '';

  if ( $token === '' ) {
    return false;
  }

  // check that the token is valid and is not expired
  $splitToken = explode( ':', $token );
  if ( count( $splitToken ) !== 3 ) {
    return false;
  }

  $expires = (int) $splitToken[1];
  if ( time() > $expires ) {
    return false;
  }

  $writioAuthKey = $splitToken[2];

  $expectedToken = hash_hmac( 'sha256', $writioAuthKey . ':' . $expires, AUTH_KEY );
  if ( $splitToken[0] === $expectedToken ) {
    return true;
  }

  return false;
}

function writio_token_permission_callback( $request ) {
    // check to make sure that an auth key was sent with the request
    $authKey = $request->get_param( 'auth' );

    if ( $authKey === '' ) {
      return false;
    }

    return true;
}

function custom_media_sideload_directory($upload) {
    $custom_dir = '/images';

    // Set the new directory path
    $upload['subdir'] = $custom_dir . $upload['subdir'];
    $upload['path'] = $upload['basedir'] . $upload['subdir'];
    $upload['url'] = $upload['baseurl'] . $upload['subdir'];

    return $upload;
}

function writio_remove_images_from_post_content( $post_content, $image_urls ) {
    foreach ( $image_urls as $image_url ) {
      $pattern = "/\/([\w-]+\.\w+)\?/";
      if (preg_match($pattern, $image_url, $matches)) {
        $filename = $matches[1];
        $image_string = "/wp-content/uploads/images/" . $filename;
        $pattern = '/<img[^>]+src="' . preg_quote($image_string, '/') . '"[^>]*>/i';
        $post_content = preg_replace($pattern, '', $post_content);
      }
    }

    return $post_content;
}

function writio_attach_images_to_post( $post_id, $image_urls ) {
    foreach ( $image_urls as $image_url ) {
      // check if the URL is valid and retrieve the attachment ID
      $attachment_id = attachment_url_to_postid( $image_url );
      if ( ! $attachment_id ) {
        continue;
      }

      // attach the image to the post
      $attachment_data = array(
        'ID' => $attachment_id,
        'post_parent' => $post_id,
      );
      wp_update_post($attachment_data);
    }
}

function writio_attach_featured_image_to_post( $post_id, $featured_image_src ) {
    // check if the URL is valid and retrieve the attachment ID
    $attachment_id = attachment_url_to_postid( $featured_image_src );
    if ( $attachment_id ) {
      $attachment_data = array(
        'ID' => $attachment_id,
        'post_parent' => $post_id,
      );
      wp_update_post($attachment_data);

      // add the image url as post meta
      add_post_meta( $post_id, '_thumbnail_id', $attachment_id );
    }
}

function writio_post_status_callback( $log_id, $articleId, $post_url, $success, $message, $response ) {
  $writioAccountEmail = get_option( 'writio_account_email' );

  // encode payload
  $payload = json_encode( array(
    'articleId' => $articleId,
    'siteUrl'   => site_url(),
    'email'     => $writioAccountEmail,
    'link'      => $post_url,
    'logId'     => $log_id,
    'success'   => $success,
    'message'   => $message,
  ) );

  // let the writio service know the status of the post
  $writioResponse = wp_remote_post( "https://be.writio.com/v1/article/callback", array(
    'headers' => array( 'Content-Type' => 'application/json; charset=utf8' ),
    'body'    => $payload,
    'timeout' => 5
  ) );

  return new WP_REST_Response( $response );
}

function writio_post_processing( $log_id, $article_id, $post_title, $post_content, $post_status, $images, $featured_image, $author_id, $category_id ) {
  // handle uploading images for the post first
  $success_images = array();
  $bad_images = array();
  if ( ! empty( $images ) ) {
    // remove the date based structure of image uploading
    add_filter( 'pre_option_uploads_use_yearmonth_folders', '__return_zero' );
    add_filter( 'upload_dir', 'custom_media_sideload_directory' );
    foreach ( $images as $image ) {
      $res = media_sideload_image( $image, 0, null, 'src' );
      if ( is_wp_error( $res ) ) {
        $bad_images[] = $image;
      } else {
        $success_images[] = $res;
      }
    }
  }

  // handle uploading the featured image for the post if there is one chosen
  $featured_image_src = '';
  if ( ! empty( $featured_image ) ) {
    add_filter( 'pre_option_uploads_use_yearmonth_folders', '__return_zero' );
    add_filter( 'upload_dir', 'custom_media_sideload_directory' );

    $res = media_sideload_image( $featured_image, 0, null, 'src' );
    if ( is_wp_error( $res ) ) {
      $bad_images[] = $featured_image;
    } else {
      $featured_image_src = $res;
    }
  }

  // if there were any bad images, remove them from the content
  $updated_post_content = writio_remove_images_from_post_content( $post_content, $bad_images );

  // if the post author isn't set, try to use writio!
  $post_author = 0;
  if ( $author_id > 0 ) {
    $post_author = $author_id;
  } else {
    $user = get_user_by( 'login', 'writio.com' );
    if ( $user ) {
      $post_author = $user->ID;
    }
  }

  // add the category id for the post
  $post_category = array();
  if ( $category_id > 0 ) {
    $post_category[] = $category_id;
  }

  // create the post
  $post = array(
    'post_title'    => $post_title,
    'post_content'  => $updated_post_content,
    'post_status'   => $post_status,
    'post_author'   => $post_author,
    'post_category' => $post_category,
  );

  $post_id = wp_insert_post( $post );
  if ( is_wp_error( $post_id ) ) {
    $response = array(
      'success' => false,
      'message' => $post_id->get_error_message(),
    );
    return writio_post_status_callback( $log_id, $article_id, "", false, $post_id->get_error_message(), $response );
  }

  // get the post URL generated
  if ( $post_status === 'draft' ) {
    $post_url = site_url() . "/" . sanitize_title($post_title);
  } else {
    $post_url = get_permalink( $post_id );
  }

  if ( empty ( $post_url ) ) {
    $response = array(
      'success' => false,
      'message' => 'Post URL did not return for post id: ' . $post_id,
    );
    return writio_post_status_callback( $log_id, $article_id, "", false, 'Post URL did not return for post id: ' . $post_id, $response );
  }

  // attach any good images to the post
  if ( count( $success_images ) > 0 ) {
    writio_attach_images_to_post( $post_id, $success_images );
  }

  // attach the featured image to the post
  if ( $featured_image_src !== '' ) {
    writio_attach_featured_image_to_post( $post_id, $featured_image_src );
  }

  // if there are any images that couldn't be uploaded, report images
  if ( count( $bad_images ) > 0 ) {
    $response = array(
      'success' => true,
      'message' => json_encode($bad_images),
    );
    return writio_post_status_callback( $log_id, $article_id, $post_url, true, "Some images failed to save: " . json_encode($bad_images), $response );
  }

  $response = array(
    'link'    => $post_url,
    'success' => true,
    'message' => 'Post created successfully!',
  );

  return writio_post_status_callback( $log_id, $article_id, $post_url, true, "Post created successfully!", $response );
}