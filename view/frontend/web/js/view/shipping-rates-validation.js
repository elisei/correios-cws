/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    'O2TI_SigepWebCarrier/js/model/shipping-rates-validator',
    'O2TI_SigepWebCarrier/js/model/shipping-rates-validation-rules'
], function (
    Component,
    defaultShippingRatesValidator,
    defaultShippingRatesValidationRules,
    sigepShippingRatesValidator,
    sigepShippingRatesValidationRules
) {
    'use strict';

    defaultShippingRatesValidator.registerValidator('sigep_web_carrier', sigepShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('sigep_web_carrier', sigepShippingRatesValidationRules);

    // defaultShippingRatesValidator.registerValidator('sigep', sigepShippingRatesValidator);
    // defaultShippingRatesValidationRules.registerRules('sigep', sigepShippingRatesValidationRules);


    return Component;
});
