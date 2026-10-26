jQuery(function ($) {
    'use strict';

    var params = window.dtc_params || {};

    function showMessages($box, messages, type) {
        var $list = $('<ul>');
        $.each(messages, function (_, message) {
            $list.append($('<li>').text(message)); // text(): server messages are never parsed as HTML
        });
        $box.empty().append($('<div>').addClass('dtc-' + type).append($list)).get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function failureMessages(xhr) {
        var json = xhr.responseJSON;
        if (json && json.data && json.data.messages) {
            return json.data.messages;
        }
        return ['Something went wrong. Please try again later.'];
    }

    function post(data) {
        data.security = params.nonce;
        return $.ajax({ type: 'POST', url: params.ajax_url, data: data, dataType: 'json' });
    }

    // Plate numbers: uppercase letters and digits only.
    $('#vehicle_reg_id').on('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 14);
    });
    $('#dtc_card_no').on('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
    });

    // Verify the DTC Code against the generated codes.
    $('#dtc-card-no').on('click', function () {
        var $message = $('#dtc-card-message');
        var code = $('#dtc_card_no').val();
        if (!code) {
            $message.text('Please enter your DTC Code.');
            return;
        }
        post({ action: 'dtc_connector_card_valid', dtc_card_no: code })
            .done(function (response) { $message.text(response.data.message); })
            .fail(function (xhr) { $message.text(failureMessages(xhr)[0]); });
    });

    $('#dtc-register-form').on('submit', function (event) {
        event.preventDefault();
        var form = this;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var $button = $(form).find('button[type=submit]').prop('disabled', true).text('SUBMITTING...');
        var data = $(form).serializeArray().reduce(function (acc, field) { acc[field.name] = field.value; return acc; }, { action: 'dtc_connector_submit' });

        post(data)
            .done(function (response) {
                $('#dtc-register-wrapper').html(response.data.html).get(0).scrollIntoView({ behavior: 'smooth' });
            })
            .fail(function (xhr) {
                showMessages($('#message'), failureMessages(xhr), 'errors');
                $button.prop('disabled', false).text('SUBMIT');
            });
    });

    $('#dtc-check-registry-form').on('submit', function (event) {
        event.preventDefault();
        var code = $('#dtc_code').val();
        if (!code) {
            return;
        }
        post({ action: 'dtc_connector_check_registry', dtc_code: code })
            .done(function (response) { $('#result').html(response.data.html); })
            .fail(function (xhr) { showMessages($('#result'), failureMessages(xhr), 'errors'); });
    });
});
