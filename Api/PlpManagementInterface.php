<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Api;

/**
 * PLP Management Service - handles PLP creation with order validation.
 */
interface PlpManagementInterface
{
    /**
     * Create a new PLP with the given orders, validate shipping eligibility,
     * and mark it as ready to send to CWS.
     *
     * @param string[] $orderIds
     * @param int|null $storeId
     * @return \O2TI\SigepWebCarrier\Api\Data\PlpCreateResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\InputException
     */
    public function create(array $orderIds, $storeId = null);
}
