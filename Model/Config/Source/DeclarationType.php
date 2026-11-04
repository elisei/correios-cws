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

class DeclarationType implements OptionSourceInterface
{
    /**
     * Correios issue the DC-e (emiteDCe = S)
     */
    public const DCE = 'dce';

    /**
     * NF-e read from the invoice comments
     */
    public const NFE_INVOICE = 'nfe_invoice';

    /**
     * NF-e read from the order comments (status history)
     */
    public const NFE_ORDER = 'nfe_order';

    /**
     * Options getter.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::DCE, 'label' => __('DC-e emitida pelos Correios')],
            ['value' => self::NFE_INVOICE, 'label' => __('NF-e (comentário do invoice)')],
            ['value' => self::NFE_ORDER, 'label' => __('NF-e (comentário do pedido)')],
        ];
    }
}
