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

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Label Format - Source model for label format options.
 */
class LabelFormat implements OptionSourceInterface
{
    /**
     * Options getter.
     *
     * @return array Array of label format options
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'ET', 'label' => __('Etiqueta')],
            ['value' => 'EV', 'label' => __('Envelope')]
        ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array Array of label format options in key-value format
     */
    public function toArray(): array
    {
        return [
            'ET' => __('Etiqueta'),
            'EV' => __('Envelope')
        ];
    }
}
