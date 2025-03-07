define([
    'jquery',
    'uiRegistry',
    'Magento_Ui/js/form/form'
], function ($, registry, Form) {
    'use strict';
    
    return Form.extend({
        initialize: function () {
            this._super();
            
            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.status')(function (statusField) {
                statusField.on('value', function (value) {
                    this.updatePermissions(value);
                }.bind(this));
                
                this.updatePermissions(statusField.value());
            }.bind(this));
            
            return this;
        },
        
        /**
         * Update permission fields based on status value
         * 
         * @param {String} status
         */
        updatePermissions: function (status) {
            var permissions = {
                'opened': {
                    'can_add_orders': true,
                    'can_send_to_cws': true
                }
            };

            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.can_add_orders')(function (field) {
                field.disabled(permissions[status]?.can_add_orders ? false : true);
            });

            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.can_send_to_cws')(function (field) {
                field.disabled(permissions[status]?.can_send_to_cws ? false : true);
            });

            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.select_orders_container')(function (field) {
                field.visible(permissions[status]?.can_add_orders || false);
            });
        }
    });
});