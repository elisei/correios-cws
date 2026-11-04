<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Data;

use Magento\Framework\DataObject;
use O2TI\SigepWebCarrier\Api\Data\PlpCreateResultInterface;

class PlpCreateResult extends DataObject implements PlpCreateResultInterface
{
    /**
     * @inheritDoc
     */
    public function getPlpId()
    {
        return $this->getData('plp_id');
    }

    /**
     * @inheritDoc
     */
    public function setPlpId($plpId)
    {
        return $this->setData('plp_id', $plpId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderIds()
    {
        return $this->getData('order_ids') ?? [];
    }

    /**
     * @inheritDoc
     */
    public function setOrderIds(array $orderIds)
    {
        return $this->setData('order_ids', $orderIds);
    }
}
