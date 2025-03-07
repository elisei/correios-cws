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
 * Class PrintLayout - Source model for print layout options.
 *
 * @api
 */
class PrintLayout implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter.
     *
     * @return array Array of print layout options
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'PADRAO', 'label' => __('Padrão')],
            ['value' => 'LINEAR_100_150', 'label' => __('Linear 100 x 150')],
            ['value' => 'LINEAR_100_80', 'label' => __('Linear 100 x 80')],
            ['value' => 'LINEAR_A4', 'label' => __('Linear em A4')],
            ['value' => 'LINEAR_A', 'label' => __('Linear em A')]
        ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array Array of print layout options in key-value format
     */
    public function toArray(): array
    {
        return [
            'PADRAO' => __('Padrão'),
            'LINEAR_100_150' => __('Linear 100 x 150'),
            'LINEAR_100_80' => __('Linear 100 x 80'),
            'LINEAR_A4' => __('Linear em A4'),
            'LINEAR_A' => __('Linear em A')
        ];
    }
}
