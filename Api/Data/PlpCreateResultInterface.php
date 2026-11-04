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

/**
 * Result DTO for PLP creation via REST API.
 */
interface PlpCreateResultInterface
{
    /**
     * Get PLP entity ID.
     *
     * @return int
     */
    public function getPlpId();

    /**
     * Set PLP entity ID.
     *
     * @param int $plpId
     * @return $this
     */
    public function setPlpId($plpId);

    /**
     * Get list of order IDs associated with the PLP.
     *
     * @return string[]
     */
    public function getOrderIds();

    /**
     * Set list of order IDs associated with the PLP.
     *
     * @param string[] $orderIds
     * @return $this
     */
    public function setOrderIds(array $orderIds);
}
