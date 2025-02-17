<?php

namespace O2TI\SigepWebCarrier\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Plp extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('sales_shipment_correios_plp', 'entity_id');
    }
}