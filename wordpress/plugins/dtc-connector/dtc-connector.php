<?php
/**
 * Plugin Name: DTC Connector
 * Description: Warranty (DTC) registration and registration-check pages backed by the DTC Laravel API. Shortcodes: [dtc_register], [dtc_check].
 * Version: 2.0.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 *
 * Configure in wp-config.php:
 *   define('DTC_API_URL', 'http://dtc.davidtyre.local/api');
 *   define('DTC_API_KEY', '<same value as DTC_API_KEY in the Laravel .env>');
 */

defined('ABSPATH') || exit;

define('DTC_CONNECTOR_DIR', plugin_dir_path(__FILE__));
define('DTC_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once DTC_CONNECTOR_DIR . 'includes/api.php';
require_once DTC_CONNECTOR_DIR . 'includes/ajax.php';
require_once DTC_CONNECTOR_DIR . 'includes/shortcodes.php';

register_activation_hook(__FILE__, 'dtc_connector_create_pages');

/** Creates the two public pages once, so the forms are available right after activation. */
function dtc_connector_create_pages()
{
    $pages = [
        'warranty-registration' => ['Warranty Registration', '[dtc_register]'],
        'check-warranty' => ['Check Warranty', '[dtc_check]'],
    ];

    foreach ($pages as $slug => [$title, $content]) {
        if (get_page_by_path($slug)) {
            continue;
        }
        wp_insert_post([
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);
    }
}
