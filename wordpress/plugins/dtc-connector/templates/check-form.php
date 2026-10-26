<?php defined('ABSPATH') || exit; ?>
<div id="dtc-check-wrapper" class="dtc">
    <form id="dtc-check-registry-form" method="POST" novalidate>
        <div class="dtc-panel">
            <h3 class="dtc-panel-header">Verify your Registration</h3>
            <div class="dtc-panel-body">
                <p class="dtc-title-header">Enter the DTC Code or Vehicle Plate Number</p>
                <input type="text" name="dtc_code" id="dtc_code" placeholder="DTC Code or Plate Number" required maxlength="64" aria-label="DTC Code or plate number">
                <button type="submit" class="dtc-btn">SUBMIT</button>
            </div>
        </div>
    </form>
    <div id="result" role="status" aria-live="polite"></div>
</div>
