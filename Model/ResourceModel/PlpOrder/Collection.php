<?php

namespace O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \O2TI\SigepWebCarrier\Model\PlpOrder::class,
            \O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder::class
        );
    }
}