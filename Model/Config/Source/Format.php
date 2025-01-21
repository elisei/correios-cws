<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Config\Source;

/**
 * Class Format - Source model for package format options.
 *
 * @api
 */
class Format implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter.
     *
     * @return array Array of format options
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '001', 'label' => __('Envelope')],
            ['value' => '002', 'label' => __('Caixa')],
            ['value' => '003', 'label' => __('Cilindro')]
        ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array Array of format options in key-value format
     */
    public function toArray(): array
    {
        return [
            '001' => __('Envelope'),
            '002' => __('Caixa'),
            '003' => __('Cilindro')
        ];
    }
}
