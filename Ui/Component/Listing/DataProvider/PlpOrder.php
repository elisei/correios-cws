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
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var PlpSession
     */
    protected $plpSession;

    /**
     * @var array
     */
    protected $loadedData;

    /**
     * @var CollectionFactory
     */
    protected $collection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collection
     * @param PlpSession $plpSession
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collection,
        PlpSession $plpSession,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collection;
        $this->plpSession = $plpSession;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $plpId = $this->plpSession->getCurrentPlpId();
        $items = [];

        if ($plpId) {
            $collection = $this->collection->create();
            $collection->addFieldToFilter('plp_id', $plpId);
            $items = $collection->getData();
        }
        
        $this->loadedData = [
            'totalRecords' => count($items),
            'items' => $items
        ];

        return $this->loadedData;
    }
}
