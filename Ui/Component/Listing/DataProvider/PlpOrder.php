<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Ui\Component\Listing\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;

class PlpOrder extends AbstractDataProvider
{
    /**
     * @var PlpSession
     */
    protected $plpSession;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param PlpSession $plpSession
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        PlpSession $plpSession,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collectionFactory = $collectionFactory;
        $this->plpSession = $plpSession;
    }

    /**
     * Get collection
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\Collection
     */
    public function getCollection()
    {
        if ($this->collection === null) {
            $this->collection = $this->collectionFactory->create();
            $plpId = $this->plpSession->getCurrentPlpId();
            
            if ($plpId) {
                $this->collection->addFieldToFilter('plp_id', $plpId);
            }
        }
        
        return $this->collection;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        $collection = $this->getCollection();
        
        return [
            'totalRecords' => $collection->getSize(),
            'items' => $collection->getData()
        ];
    }
}
