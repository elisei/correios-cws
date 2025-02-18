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

class Plp extends AbstractDb
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
        $this->_init('sales_shipment_correios_plp', 'entity_id');
    }
}
