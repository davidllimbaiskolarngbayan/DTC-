<?php

defined('ABSPATH') || exit;

add_shortcode('dtc_register', 'dtc_register_shortcode');
add_shortcode('dtc_check', 'dtc_check_shortcode');

function dtc_enqueue_assets(): void
{
    wp_enqueue_style('dtc-connector', DTC_CONNECTOR_URL . 'assets/dtc.css', [], '2.0.0');
    wp_enqueue_script('dtc-connector', DTC_CONNECTOR_URL . 'assets/dtc-form.js', ['jquery'], '2.0.0', true);
    wp_localize_script('dtc-connector', 'dtc_params', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('dtc_nonce'),
    ]);
}

function dtc_render_template(string $template): string
{
    dtc_enqueue_assets();

    ob_start();
    include DTC_CONNECTOR_DIR . 'templates/' . $template . '.php';

    return (string) ob_get_clean();
}

function dtc_register_shortcode(): string
{
    return dtc_render_template('register-form');
}

function dtc_check_shortcode(): string
{
    return dtc_render_template('check-form');
}
