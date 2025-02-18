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
}
