<?php
namespace O2TI\SigepWebCarrier\Api;

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
}