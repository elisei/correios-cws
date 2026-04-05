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

class DaceType implements OptionSourceInterface
{
    /**
     * Options getter.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'T', 'label' => __('Térmica (Texto)')],
            ['value' => 'R', 'label' => __('Resumida (PDF)')],
            ['value' => 'C', 'label' => __('Completa (PDF)')]
        ];
    }
}
