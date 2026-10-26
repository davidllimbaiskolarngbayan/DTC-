<?php defined('ABSPATH') || exit; ?>
<div id="thank-you-message" class="dtc-panel" role="status">
    <div class="dtc-panel-body">
        <p>Dear <?php echo esc_html($data['first_name'] ?? ''); ?>,</p>
        <p>
            You have successfully registered your tyres with Worry Free Assurance. Your DTC Code is
            <strong><?php echo esc_html($data['dtc_card_no'] ?? ''); ?></strong>.
            <?php if (! empty($data['email'])) : ?>
                A confirmation email is on its way; if you do not see it, please check your junk or spam folder.
            <?php endif; ?>
        </p>
        <p>Tyre: <strong><?php echo esc_html($data['tyre'] ?? ''); ?></strong></p>
        <p>Date of purchase: <strong><?php echo esc_html($data['date_of_purchase'] ?? ''); ?></strong></p>
        <p>Dealer: <strong><?php echo esc_html($data['find_retailer'] ?? ''); ?></strong></p>
        <?php if (! empty($data['thankyou_url'])) : ?>
            <p><a class="dtc-btn" href="<?php echo esc_url($data['thankyou_url']); ?>" target="_blank" rel="noopener">View your registration confirmation</a></p>
        <?php endif; ?>
    </div>
</div>
