<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Config\Source;

use O2TI\SigepWebCarrier\Model\ResourceModel\SigepWebServices\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Service implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * Construct.
     *
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Services to array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $service = [];
        $collection = $this->collectionFactory->create();

        if ($collection) {
            foreach ($collection as $value) {
                $service[] = [
                    'value' => $value->getCode(),
                    'label' => $value->getName()
                 ];
            }
        }
        
        return $service;
    }
}
