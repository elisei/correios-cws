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
 * Management Correios Plp.
 */
interface PlpRepositoryInterface
{
    /**
     * Save PLP
     *
     * @param \O2TI\SigepWebCarrier\Api\Data\PlpInterface $plp
     * @return \O2TI\SigepWebCarrier\Api\Data\PlpInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\O2TI\SigepWebCarrier\Api\Data\PlpInterface $plp);

    /**
     * Get PLP by ID
     *
     * @param int $plpId
     * @return \O2TI\SigepWebCarrier\Api\Data\PlpInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($plpId);

    /**
     * Add orders to PLP
     *
     * @param int $plpId
     * @param string[] $orderIds
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addOrderToPlp($plpId, array $orderIds);

    /**
     * Update order status in PLP
     *
     * @param int $plpId
     * @param string $orderId
     * @param string $status
     * @param string|null $errorMessage
     * @param string|null $shipmentId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrderStatus($plpId, $orderId, $status, $errorMessage = null, $shipmentId = null);

    /**
     * Delete PLP
     *
     * @param int $plpId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($plpId);

    /**
     * Update order collected data
     *
     * @param int $plpId
     * @param string $orderId
     * @param string $collectedData
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrderCollectedData($plpId, $orderId, $collectedData);

    /**
     * Update order processing data
     *
     * @param int $plpId
     * @param string $orderId
     * @param string $processingData
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrderProcessingData($plpId, $orderId, $processingData);

    /**
     * Update order collection status
     *
     * @param int $plpId
     * @param string $orderId
     * @param string $collectionStatus
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrderCollectionStatus($plpId, $orderId, $collectionStatus);

    /**
     * Update order processing status
     *
     * @param int $plpId
     * @param string $orderId
     * @param string $processingStatus
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrderProcessingStatus($plpId, $orderId, $processingStatus);
}
