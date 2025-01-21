<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

declare(strict_types=1);

namespace O2TI\SigepWebCarrier\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface SigepWebServicesSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get services list.
     *
     * @return \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface[]
     */
    public function getItems();

    /**
     * Set services list.
     *
     * @param \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
