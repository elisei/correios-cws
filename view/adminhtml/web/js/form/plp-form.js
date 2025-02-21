define([
    'Magento_Ui/js/form/form',
    'underscore',
    'mage/translate'
], function (Form, _, $t) {
    'use strict';

    return Form.extend({
        defaults: {
            listens: {
                'sales_order_grid.sales_order_grid.sales_order_columns.ids:selected': 'onOrdersSelected'
            },
            processingSelection: false
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            
            this.source.set('data.sales_order_grid.selected', null);
            this.source.set('data.sales_order_grid', []);

            return this;
        },

        /**
         * Get child component
         *
         * @param {String} name
         * @returns {Object}
         */
        getChild: function (name) {
            return this.requestModule(this.name + '.' + name);
        },

        /**
         * Handler of the orders selection
         * 
         * @param {Array} selected
         */
        onOrdersSelected: function (selected) {
            if (this.processingSelection) {
                return;
            }

            try {
                this.processingSelection = true;

                selected = selected || [];
                if (typeof selected === 'string') {
                    selected = [selected];
                }

                selected = selected.filter(function(id) {
                    return id && id.toString().trim() !== '';
                });

                var data = [];
                if (selected.length) {
                    data = _.map(selected, function(id) {
                        return {
                            'entity_id': id.toString()
                        };
                    });
                }

                if (this.source) {
                    this.source.set('data.sales_order_grid', data);
                }
            } catch (e) {
                console.error('Error processing order selection:', e);
            } finally {
                this.processingSelection = false;
            }
        }
    });
});
