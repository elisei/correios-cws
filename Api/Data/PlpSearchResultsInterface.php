<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search Correios Plp.
 */
interface PlpSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get PLPs list
     *
     * @return \O2TI\SigepWebCarrier\Api\Data\PlpInterface[]
     */
    public function getItems();

    /**
     * Set PLPs list
     *
     * @param \O2TI\SigepWebCarrier\Api\Data\PlpInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
