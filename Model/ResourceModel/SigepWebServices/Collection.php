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

namespace O2TI\SigepWebCarrier\Model\ResourceModel\SigepWebServices;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use O2TI\SigepWebCarrier\Model\SigepWebServices as Model;
use O2TI\SigepWebCarrier\Model\ResourceModel\SigepWebServices as ResourceModel;

/**
 * Collection to services
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'sigep_web_services_collection';

    /**
     * Initialize collection model
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
