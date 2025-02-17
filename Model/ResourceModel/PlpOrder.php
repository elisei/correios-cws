<?php

namespace O2TI\SigepWebCarrier\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PlpOrder extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('sales_shipment_correios_plp_order', 'entity_id');
    }

    /**
     * Load PlpOrder by plp_id and order_id
     *
     * @param \O2TI\SigepWebCarrier\Model\PlpOrder $plpOrder
     * @param int $plpId
     * @param string $orderId
     * @return $this
     */
    public function loadByPlpAndOrder($plpOrder, $plpId, $orderId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('plp_id = ?', $plpId)
            ->where('order_id = ?', $orderId);

        $data = $connection->fetchRow($select);
        if ($data) {
            $plpOrder->setData($data);
        }

        return $this;
    }
}
