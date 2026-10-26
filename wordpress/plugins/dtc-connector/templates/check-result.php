<?php defined('ABSPATH') || exit; ?>
<?php if (empty($record)) : ?>
    <p>No registration found for that DTC Code or plate number.</p>
<?php else : ?>
    <table class="dtc-result">
        <tr><th scope="row">DTC Code</th><td><?php echo esc_html($record['dtc_card_no'] ?? ''); ?></td></tr>
        <tr><th scope="row">Vehicle</th><td><?php echo esc_html(trim(($record['vehicle_reg_id'] ?? '') . ' ' . ($record['brand'] ?? '') . ' ' . ($record['model'] ?? ''))); ?></td></tr>
        <tr><th scope="row">Tyre</th><td><?php echo esc_html(trim(($record['tyre'] ?? '') . ' ' . ($record['tyre_size'] ?? ''))); ?></td></tr>
        <tr><th scope="row">Quantity</th><td><?php echo esc_html($record['quantity'] ?? ''); ?></td></tr>
        <tr><th scope="row">Date of Purchase</th><td><?php echo esc_html($record['date_of_purchase'] ?? ''); ?></td></tr>
        <tr><th scope="row">Dealer</th><td><?php echo esc_html($record['store_name'] ?? ''); ?></td></tr>
        <tr><th scope="row">Registered On</th><td><?php echo esc_html($record['created_at'] ?? ''); ?></td></tr>
    </table>
<?php endif; ?>
