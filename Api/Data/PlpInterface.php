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
 * Management Correios Plp.
 */
interface PlpInterface
{
    public const ENTITY_ID = 'entity_id';
    public const STORE_ID = 'store_id';
    public const STATUS = 'status';
    public const CAN_ADD_ORDERS = 'can_add_orders';
    public const CAN_REMOVE_ORDERS = 'can_remove_orders';
    public const CAN_CLOSE = 'can_close';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

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
     * Get Store Id
     *
     * @return int
     */
    public function getStoreId();

    /**
     * Set Store Id
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId);

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
     * Get Can Add Orders
     *
     * @return bool
     */
    public function getCanAddOrders();

    /**
     * Set Can Add Orders
     *
     * @param bool $canAddOrders
     * @return $this
     */
    public function setCanAddOrders($canAddOrders);

    /**
     * Get Can Remove Orders
     *
     * @return bool
     */
    public function getCanRemoveOrders();

    /**
     * Set Can Remove Orders
     *
     * @param bool $canRemoveOrders
     * @return $this
     */
    public function setCanRemoveOrders($canRemoveOrders);

    /**
     * Get Can Close
     *
     * @return bool
     */
    public function getCanClose();

    /**
     * Set Can Close
     *
     * @param bool $canClose
     * @return $this
     */
    public function setCanClose($canClose);
}
