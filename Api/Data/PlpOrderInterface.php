<?php
namespace O2TI\SigepWebCarrier\Api\Data;

interface PlpOrderInterface
{
    public const ENTITY_ID = 'entity_id';
    public const PLP_ID = 'plp_id';
    public const ORDER_ID = 'order_id';
    public const STATUS = 'status';
    public const ERROR_MESSAGE = 'error_message';
    public const SHIPMENT_ID = 'shipment_id';

    /**
     * @return int
     */
    public function getEntityId();

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * @return int
     */
    public function getPlpId();

    /**
     * @param int $plpId
     * @return $this
     */
    public function setPlpId($plpId);

    /**
     * @return string
     */
    public function getOrderId();

    /**
     * @param string $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * @return string
     */
    public function getStatus();

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * @return string|null
     */
    public function getErrorMessage();

    /**
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage($errorMessage);

    /**
     * @return string|null
     */
    public function getShipmentId();

    /**
     * @param string|null $shipmentId
     * @return $this
     */
    public function setShipmentId($shipmentId);
}
