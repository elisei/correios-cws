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
 * Management Correios Plp Order.
 */
interface PlpOrderInterface
{
    public const ENTITY_ID = 'entity_id';
    public const PLP_ID = 'plp_id';
    public const ORDER_ID = 'order_id';
    public const STATUS = 'status';
    public const ERROR_MESSAGE = 'error_message';
    public const SHIPMENT_ID = 'shipment_id';
    public const COLLECTED_DATA = 'collected_data';
    public const PROCESSING_DATA = 'processing_data';

    /**
     * Get Entity Id
     *
     * @return int
     */
    public function getEntityId();

    /**
     * Set Entity Id
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * Get Plp Id
     *
     * @return int
     */
    public function getPlpId();

    /**
     * Set Plp Id
     *
     * @param int $plpId
     * @return $this
     */
    public function setPlpId($plpId);

    /**
     * Get Order Id
     *
     * @return string
     */
    public function getOrderId();

    /**
     * Set Order Id
     *
     * @param string $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * Get Status
     *
     * @return string
     */
    public function getStatus();

    /**
     * Set Status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * Get Error Message
     *
     * @return string|null
     */
    public function getErrorMessage();

    /**
     * Set Error Message
     *
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage($errorMessage);

    /**
     * Get Ship Id
     *
     * @return string|null
     */
    public function getShipmentId();

    /**
     * Set Ship Id
     *
     * @param string|null $shipmentId
     * @return $this
     */
    public function setShipmentId($shipmentId);

    /**
     * Get Collected Data
     *
     * @return string|null
     */
    public function getCollectedData();

    /**
     * Set Collected Data
     *
     * @param string|null $collectedData
     * @return $this
     */
    public function setCollectedData($collectedData);

    /**
     * Get Processing Data
     *
     * @return string|null
     */
    public function getProcessingData();

    /**
     * Set Processing Data
     *
     * @param string|null $processingData
     * @return $this
     */
    public function setProcessingData($processingData);
}
