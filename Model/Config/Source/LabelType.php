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
 * Class Label Type - Source model for label type options.
 *
 * @api
 */
class LabelType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter.
     *
     * @return array Array of label type options
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'P', 'label' => __('Padrão')],
            ['value' => 'R', 'label' => __('Reduzida')]
        ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array Array of label type options in key-value format
     */
    public function toArray(): array
    {
        return [
            'P' => __('Padrão'),
            'R' => __('Reduzida')
        ];
    }
}
