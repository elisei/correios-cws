<?php
namespace O2TI\SigepWebCarrier\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpSearchResultsInterface;

/**
 * Interface PlpRepositoryInterface
 */
interface PlpRepositoryInterface
{
    /**
     * Save PLP
     *
     * @param PlpInterface $plp
     * @return PlpInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(PlpInterface $plp);

    /**
     * Get PLP by ID
     *
     * @param int $plpId
     * @return PlpInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($plpId);

    /**
     * Get list of PLPs
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return PlpSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

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

    /**
     * Transition PLP status
     *
     * @param int $plpId
     * @param string $newStatus
     * @param string|null $message
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function transitionStatus($plpId, $newStatus, $message = null);

    /**
     * Get PLP status details
     *
     * @param int $plpId
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStatusDetails($plpId);

    /**
     * Remove order from PLP
     *
     * @param int $plpId
     * @param string $orderId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function removeOrderFromPlp($plpId, $orderId);

    /**
     * Request PLP closing
     *
     * @param int $plpId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function requestClosing($plpId);
}
