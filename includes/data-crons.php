<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function pull_unpublished_articles() {
  pull_unpublished(false);

}

function pull_unpublished_articles_admin() {
  pull_unpublished(true);
}

function pull_unpublished( $isPublisherRequest ) {
  $writio_account_email = get_option( 'writio_account_email' );

  if ( ! empty( $writio_account_email ) ) {
    // encode payload
    $payload = json_encode(array(
      'siteUrl'            => site_url(),
      'email'              => $writio_account_email,
      'isPublisherRequest' => $isPublisherRequest,
    ) );

    $writioResponse = wp_remote_post( "https://be.writio.com/v1/articles/pull", array(
      'headers' => array('Content-Type' => 'application/json; charset=utf8'),
      'body'    => $payload,
      'timeout' => 5
    ) );

    if ( ! is_wp_error( $writioResponse ) ) {
      $responseBody = wp_remote_retrieve_body( $writioResponse );
      $parsed       = json_decode( $responseBody );

      if ( ! is_null( $parsed->success ) && $parsed->success ) {
        foreach ($parsed->data as $article) {
          $log_id = $article->logId;
          $article_id = $article->articleId;
          $post_title = $article->title;
          $post_content = $article->content;
          $post_status = $article->status;
          $images = $article->images;
          $featured_image = $article->featured_image;
          $author_id = $article->authorId;
          $category_id = $article->categoryId;
          // double check to make sure the post doesn't already exist before creating
          if ( ! post_exists_by_title( $article->title ) ) {
            writio_post_processing( $log_id, $article_id, $post_title, $post_content, $post_status, $images, $featured_image, $author_id, $category_id );
          } else {
            $response = array(
              'message' => 'Post already published with this title for articleId: ' . $article_id,
              'success' => false
            );
            writio_post_status_callback( $log_id, $article_id, "", false, "Post already published with this title for articleId: " . $article_id, $response );
          }
        }
      }
    }
  }
}

function schedule_pull_unpublished_articles() {
  if ( ! wp_next_scheduled( 'writio_pull_unpublished_articles' ) ) {
    wp_schedule_event( time(), 'hourly', 'writio_pull_unpublished_articles' );
  }
}

// check to see if a post with a specific title exists
function post_exists_by_title( $title ) {
  $args = array(
    'post_type'      => 'post',
    'posts_per_page' => 1,
    'post_status'    => 'any',
    'title'          => $title,
    'exact'          => true,
  );

  $query = new WP_Query( $args );
  if ( $query->have_posts() ) {
    return true;
  }

  return false;
}