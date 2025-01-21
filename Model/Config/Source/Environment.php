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
class Environment implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter.
     *
     * @return array Array of format options
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'PRODUCAO', 'label' => __('Produção')],
            ['value' => 'HOMOLOG', 'label' => __('Homologação')],
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
            'PRODUCAO' => __('Produção'),
            'HOMOLOG'  => __('Homologação')
        ];
    }
}
