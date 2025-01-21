<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

declare(strict_types=1);

namespace O2TI\SigepWebCarrier\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SigepWebServices extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _construct()
    {
        $this->_init('sigep_web_services', 'entity_id');
    }
}
