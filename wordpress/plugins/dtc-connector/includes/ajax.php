<?php

defined('ABSPATH') || exit;

// Prefixed so the plugin can run alongside a theme that defines dtc_submit() and friends.
add_action('wp_ajax_dtc_connector_submit', 'dtc_connector_submit');
add_action('wp_ajax_nopriv_dtc_connector_submit', 'dtc_connector_submit');
add_action('wp_ajax_dtc_connector_card_valid', 'dtc_connector_card_valid');
add_action('wp_ajax_nopriv_dtc_connector_card_valid', 'dtc_connector_card_valid');
add_action('wp_ajax_dtc_connector_check_registry', 'dtc_connector_check_registry');
add_action('wp_ajax_nopriv_dtc_connector_check_registry', 'dtc_connector_check_registry');

// Names must match the rules declared by the Laravel market class (App\Implementations\Markets\Standard).
const DTC_SUBMIT_FIELDS = [
    'first_name', 'last_name', 'email', 'contact_no', 'vehicle_reg_id', 'brand', 'model', 'mileage',
    'tyre', 'tyre_size', 'rim_size', 'quantity', 'date_of_purchase', 'state', 'find_retailer', 'dtc_card_no',
];

function dtc_post_value(string $name): string
{
    return isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
}

function dtc_connector_submit()
{
    check_ajax_referer('dtc_nonce', 'security');

    // Honeypot: bots fill hidden fields.
    if (dtc_post_value('website') !== '') {
        wp_send_json_success(['html' => '<p>Thank you.</p>']);
    }

    $payload = [];
    foreach (DTC_SUBMIT_FIELDS as $name) {
        $payload[$name] = dtc_post_value($name);
    }
    $payload['dtc_card_no'] = strtoupper($payload['dtc_card_no']);
    $payload['is_dtc'] = '1';
    $payload['tnc'] = dtc_post_value('tnc') === '' ? '' : '1';
    $payload['subscribe'] = dtc_post_value('subscribe') === '' ? '0' : '1';

    $result = DTC_Api::request('POST', 'register', $payload);
    if (! $result['ok']) {
        wp_send_json_error(['messages' => DTC_Api::errorMessages($result)], $result['status'] === 422 ? 422 : 502);
    }

    ob_start();
    $data = $payload + ['thankyou_url' => $result['body']['thankyou_url'] ?? ''];
    include DTC_CONNECTOR_DIR . 'templates/thankyou.php';

    wp_send_json_success(['html' => ob_get_clean()]);
}

function dtc_connector_card_valid()
{
    check_ajax_referer('dtc_nonce', 'security');

    $code = strtoupper(dtc_post_value('dtc_card_no'));
    if (! preg_match('/^[A-Z0-9\-]{1,64}$/', $code)) {
        wp_send_json_success(['valid' => false, 'message' => 'Invalid DTC Code.']);
    }

    $result = DTC_Api::request('GET', 'dtc_codes/is_exist/' . $code);
    if (! $result['ok']) {
        wp_send_json_error(['messages' => [$result['message']]], 502);
    }

    $valid = ! empty($result['body']['success']);
    wp_send_json_success(['valid' => $valid, 'message' => $valid ? 'DTC Code is valid.' : 'DTC Code not found or already used.']);
}

function dtc_connector_check_registry()
{
    check_ajax_referer('dtc_nonce', 'security');

    $result = DTC_Api::request('POST', 'checkRecordByDtcCodeOrPlateNum', ['dtc_code' => dtc_post_value('dtc_code')]);
    if (! $result['ok']) {
        wp_send_json_error(['messages' => DTC_Api::errorMessages($result)], $result['status'] === 422 ? 422 : 502);
    }

    $record = $result['body']['data'] ?? null;
    ob_start();
    include DTC_CONNECTOR_DIR . 'templates/check-result.php';

    wp_send_json_success(['html' => ob_get_clean()]);
}
