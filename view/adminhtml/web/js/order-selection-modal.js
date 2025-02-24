define([
    'Magento_Ui/js/modal/modal-component',
    'jquery',
    'mage/translate',
    'uiRegistry',
    'mage/url'
], function (Modal, $, $t, registry, url) {
    'use strict';

    return Modal.extend({
        defaults: {
            options: {
                title: $t('Add Orders'),
                modalClass: 'order-selection-modal'
            },
            listingProvider: 'order_selection_listing.order_selection_listing.order_selection_columns.ids',
            formProvider: 'sigepweb_plp_form.plp_form_data_source',
            orderListingComponent: 'sigepweb_plp_form.sigepweb_plp_form.general.associated_orders.sigepweb_plp_order_listing',
            selectionListingComponent: 'order_selection_listing.order_selection_listing'
        },

        /**
         * Adds selected orders to the PLP
         */
        addSelectedOrders: function () {
            var selections = this.getSelections();
            var self = this;

            if (selections && selections.length) {
                var formData = registry.get(this.formProvider).data;
                var plpId = formData.entity_id;
                
                if (!plpId) {
                    alert($t('Please save the PLP first before adding orders.'));
                    return;
                }
                
                $.ajax({
                    url: url.build('../../../../../addorders'),
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        plp_id: plpId,
                        order_ids: selections.join(','),
                        form_key: window.FORM_KEY
                    },
                    beforeSend: function() {
                        $('body').trigger('processStart');
                    },
                    success: function(response) {
                        $('body').trigger('processStop');
                        
                        if (response.success) {
                            self.reloadOrderListing();
                            self.reloadSelectionListing();
                        }

                        self.closeModal();
                    },
                    error: function() {
                        $('body').trigger('processStop');
                        self.closeModal();
                    }
                });
            } else {
                alert($t('Please select orders to add.'));
            }
        },

        /**
         * Returns array of selected items
         */
        getSelections: function () {
            var listingComponent = registry.get(this.listingProvider);
            
            if (!listingComponent) {
                return [];
            }
            
            return listingComponent.selected();
        },
        
        /**
         * Reloads the order listing component
         */
        reloadOrderListing: function() {
            var orderListing = registry.get(this.orderListingComponent);
            
            if (orderListing) {
                if (typeof orderListing.reload === 'function') {
                    orderListing.reload();
                    return;
                }
            }
            
            orderListing = registry.get('sigepweb_plp_order_listing');
            if (orderListing) {
                if (typeof orderListing.reload === 'function') {
                    orderListing.reload();
                    return;
                }
            }
            
            registry.async('sigepweb_plp_order_listing')(function (component) {
                if (component && typeof component.reload === 'function') {
                    component.reload();
                }
            });
        },
        
        /**
         * Reloads the selection listing component
         */
        reloadSelectionListing: function() {
            var selectionListing = registry.get(this.selectionListingComponent);
            
            if (selectionListing) {
                if (typeof selectionListing.reload === 'function') {
                    selectionListing.reload();
                    return;
                }
            }
            
            selectionListing = registry.get('order_selection_listing');
            if (selectionListing) {
                if (typeof selectionListing.reload === 'function') {
                    selectionListing.reload();
                    return;
                }
            }
            
            registry.async('order_selection_listing')(function (component) {
                if (component && typeof component.reload === 'function') {
                    component.reload();
                }
            });
        },
        
        /**
         * Override openModal to refresh selection grid before opening
         */
        openModal: function() {
            this._super();
            this.reloadSelectionListing();
            return this;
        }
    });
});