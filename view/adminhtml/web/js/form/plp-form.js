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
                'open': {
                    'can_add_orders': true,
                    'can_remove_orders': true,
                    'can_close': false
                },
                'collecting': {
                    'can_add_orders': false,
                    'can_remove_orders': false,
                    'can_close': false
                },
                'formed': {
                    'can_add_orders': false,
                    'can_remove_orders': false,
                    'can_close': true
                },
                'processing': {
                    'can_add_orders': false,
                    'can_remove_orders': false,
                    'can_close': false
                },
                'processed': {
                    'can_add_orders': false,
                    'can_remove_orders': false,
                    'can_close': false
                },
                'creating_shipment': {
                    'can_add_orders': false,
                    'can_remove_orders': false,
                    'can_close': false
                },
                'closed': {
                    'can_add_orders': false,
                    'can_remove_orders': true,
                    'can_close': false
                }
            };
            
            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.can_add_orders')(function (field) {
                field.value(permissions[status]?.can_add_orders || false);
                field.disabled(true);
            });
            
            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.can_remove_orders')(function (field) {
                field.value(permissions[status]?.can_remove_orders || false);
                field.disabled(true);
            });
            
            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.can_close')(function (field) {
                field.value(permissions[status]?.can_close || false);
                field.disabled(true);
            });
            
            registry.async('sigepweb_plp_form.sigepweb_plp_form.general.select_orders_container')(function (field) {
                field.visible(permissions[status]?.can_add_orders || false);
            });
        }
    });
});