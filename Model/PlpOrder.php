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
use O2TI\SigepWebCarrier\Api\Data\PlpOrderInterface;

class PlpOrder extends AbstractModel implements PlpOrderInterface
{
    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\PlpOrder::class);
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
    public function getPlpId()
    {
        return $this->getData(self::PLP_ID);
    }

    /**
     * @inheritDoc
     */
    public function setPlpId($plpId)
    {
        return $this->setData(self::PLP_ID, $plpId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
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
    public function getErrorMessage()
    {
        return $this->getData(self::ERROR_MESSAGE);
    }

    /**
     * @inheritDoc
     */
    public function setErrorMessage($errorMessage)
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
    }

    /**
     * @inheritDoc
     */
    public function getShipmentId()
    {
        return $this->getData(self::SHIPMENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setShipmentId($shipmentId)
    {
        return $this->setData(self::SHIPMENT_ID, $shipmentId);
    }
}
