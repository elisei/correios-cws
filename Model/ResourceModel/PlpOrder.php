<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PlpOrder extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
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
     * @param int $orderId
     * @return $this
     */
    public function loadByPlpAndOrder($plpOrder, $plpId, $orderId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('plp_id = ?', (string) $plpId)
            ->where('order_id = ?', (string) $orderId);

        $data = $connection->fetchRow($select);
        if ($data) {
            $plpOrder->setData($data);
        }

        return $this;
    }

    /**
     * Load PlpOrder by entity_id
     *
     * @param \O2TI\SigepWebCarrier\Model\PlpOrder $plpOrder
     * @param int $entityId
     * @return $this
     */
    public function loadByEntityId($plpOrder, $entityId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('entity_id = ?', (int) $entityId);

        $data = $connection->fetchRow($select);
        if ($data) {
            $plpOrder->setData($data);
        }

        return $this;
    }
}
