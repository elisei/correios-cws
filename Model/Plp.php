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
use O2TI\SigepWebCarrier\Model\Plp\Source\Status;

class Plp extends AbstractModel implements PlpInterface
{
    /**
     * @var Status
     */
    protected $statusModel;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry @deprecated 102.0.0
     * @param Status $statusModel
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        Status $statusModel,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->statusModel = $statusModel;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

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
    public function getCanSendToCws()
    {
        return (bool)$this->getData(self::CAN_SEND_TO_CWS);
    }

    /**
     * @inheritDoc
     */
    public function setCanSendToCws($canSendToCws)
    {
        return $this->setData(self::CAN_SEND_TO_CWS, (bool)$canSendToCws);
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
    public function setStatus($status)
    {
        $permissions = $this->statusModel->getActionPermissions($status);
        $this->setCanAddOrders($permissions['can_add_orders']);
        
        return $this->setData(self::STATUS, $status);
    }
}
