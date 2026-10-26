<?php defined('ABSPATH') || exit; ?>
<div id="dtc-register-wrapper" class="dtc">
    <div id="message" role="alert" aria-live="polite"></div>

    <form id="dtc-register-form" method="POST" novalidate>
        <p class="dtc-intro">
            All tyres come with a limited warranty against manufacturing defects. This registration page is for
            Worry Free Assurance warranty purposes only.
        </p>

        <div class="dtc-panel">
            <h3 class="dtc-panel-header">Tell Us About Yourself</h3>
            <div class="dtc-panel-body dtc-grid">
                <div class="dtc-section">
                    <p class="dtc-title-header">Your Name</p>
                    <input type="text" name="first_name" placeholder="* First Name" required autocomplete="given-name" aria-label="First name">
                    <input type="text" name="last_name" placeholder="* Last Name" required autocomplete="family-name" aria-label="Last name">
                </div>
                <div class="dtc-section">
                    <p class="dtc-title-header">Contact Details</p>
                    <input type="tel" name="contact_no" id="contact_no" placeholder="* Phone / Tel. No" required autocomplete="tel" aria-label="Phone number">
                    <input type="email" name="email" placeholder="* Email (user@gmail.com)" required autocomplete="email" aria-label="Email" aria-required="true">
                </div>
            </div>
        </div>

        <div class="dtc-panel">
            <h3 class="dtc-panel-header">Register Your Tyres</h3>
            <div class="dtc-panel-body dtc-grid dtc-grid-3">
                <div class="dtc-section">
                    <p class="dtc-title-header">About your vehicle</p>
                    <input type="text" name="vehicle_reg_id" id="vehicle_reg_id" placeholder="* Registration Plate Number" required maxlength="14" aria-label="Registration plate number">
                    <input type="text" name="brand" placeholder="Vehicle brand" aria-label="Vehicle brand">
                    <input type="text" name="model" placeholder="Vehicle model" aria-label="Vehicle model">
                    <input type="number" name="mileage" min="0" max="2000000" placeholder="Mileage" aria-label="Mileage">
                </div>

                <div class="dtc-section">
                    <p class="dtc-title-header">About your tyres</p>
                    <input type="text" name="tyre" placeholder="* Product Model" required aria-label="Product model">
                    <input type="text" name="tyre_size" placeholder="Tyre size (e.g. 205/55)" aria-label="Tyre size">
                    <select name="rim_size" aria-label="Rim size">
                        <option value="">Rim Size</option>
                        <?php for ($i = 12; $i <= 22; $i++) : ?>
                            <option value="R<?php echo (int) $i; ?>">R<?php echo (int) $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="quantity" required aria-label="Quantity">
                        <option value="">* Quantity</option>
                        <?php for ($i = 1; $i <= 4; $i++) : ?>
                            <option value="<?php echo (int) $i; ?>"><?php echo (int) $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <input type="date" name="date_of_purchase" required max="<?php echo esc_attr(wp_date('Y-m-d')); ?>" aria-label="Date of purchase">
                </div>

                <div class="dtc-section">
                    <p class="dtc-title-header">Store</p>
                    <input type="text" name="state" placeholder="State / Province" aria-label="State or province">
                    <input type="text" name="find_retailer" placeholder="* Dealer / Store name" required aria-label="Dealer or store name">

                    <p class="dtc-title-header">DTC Code</p>
                    <input type="text" name="dtc_card_no" id="dtc_card_no" placeholder="* DTC CODE" required maxlength="64" aria-label="DTC Code">
                    <button type="button" id="dtc-card-no" class="dtc-btn dtc-btn-secondary">Verify</button>
                    <p id="dtc-card-message" role="status"></p>
                </div>
            </div>
            <div class="dtc-panel-body dtc-consent">
                <label><input type="checkbox" name="tnc" id="tnc" value="1" required> I have read and agree with the Terms &amp; Conditions and Privacy Policy.</label>
                <label><input type="checkbox" name="subscribe" value="1" checked> Yes, I'd like to receive updates by email.</label>
            </div>
        </div>

        <p class="dtc-hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></p>
        <button type="submit" class="dtc-btn">SUBMIT</button>
    </form>
</div>
