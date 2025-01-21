define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';

    return function (config) {
        $(document).on('click', '#update_rules_button', function () {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: FORM_KEY
                },
                showLoader: true,
                success: function (response) {
                    if (response.success) {
                        alert({
                            title: $t('Success'),
                            content: response.message,
                            actions: {
                                always: function() {
                                    location.reload();
                                }
                            }
                        });
                    } else {
                        alert({
                            title: $t('Error'),
                            content: response.message
                        });
                    }
                }
            });
        });
    };
});