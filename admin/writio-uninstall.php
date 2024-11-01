<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function writio_uninstall() {
    // remove the scheduled pull unpublished article cron
    wp_clear_scheduled_hook( 'writio_pull_unpublished_articles' );
}