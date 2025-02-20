<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model;

use Magento\Framework\Model\AbstractModel;
use O2TI\SigepWebCarrier\Api\Data\PlpInterface;

class Plp extends AbstractModel implements PlpInterface
{
    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Plp::class);
    }

     /**
     * @inheritDoc
     */
    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getCanAddOrders()
    {
        return (bool)$this->getData(self::CAN_ADD_ORDERS);
    }

    /**
     * @inheritDoc
     */
    public function setCanAddOrders($canAddOrders)
    {
        return $this->setData(self::CAN_ADD_ORDERS, (bool)$canAddOrders);
    }

    /**
     * @inheritDoc
     */
    public function getCanRemoveOrders()
    {
        return (bool)$this->getData(self::CAN_REMOVE_ORDERS);
    }

    /**
     * @inheritDoc
     */
    public function setCanRemoveOrders($canRemoveOrders)
    {
        return $this->setData(self::CAN_REMOVE_ORDERS, (bool)$canRemoveOrders);
    }

    /**
     * @inheritDoc
     */
    public function getCanRequestClosing()
    {
        return (bool)$this->getData(self::CAN_REQUEST_CLOSING);
    }

    /**
     * @inheritDoc
     */
    public function setCanRequestClosing($canRequestClosing)
    {
        return $this->setData(self::CAN_REQUEST_CLOSING, (bool)$canRequestClosing);
    }

    /**
     * @inheritDoc
     */
    public function getStatusHistory()
    {
        return $this->getData(self::STATUS_HISTORY);
    }

    /**
     * @inheritDoc
     */
    public function setStatusHistory($statusHistory)
    {
        return $this->setData(self::STATUS_HISTORY, $statusHistory);
    }
}
