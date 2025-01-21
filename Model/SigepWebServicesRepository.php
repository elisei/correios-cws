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

namespace O2TI\SigepWebCarrier\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface;
use O2TI\SigepWebCarrier\Api\Data\SigepWebServicesSearchResultsInterfaceFactory;
use O2TI\SigepWebCarrier\Api\SigepWebServicesRepositoryInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\SigepWebServices as ResourceSigepWebServices;
use O2TI\SigepWebCarrier\Model\ResourceModel\SigepWebServices\CollectionFactory as SigepWebServicesCollectionFactory;

class SigepWebServicesRepository implements SigepWebServicesRepositoryInterface
{
    /**
     * @var ResourceSigepWebServices
     */
    protected $resource;

    /**
     * @var SigepWebServicesFactory
     */
    protected $serviceFactory;

    /**
     * @var SigepWebServicesCollectionFactory
     */
    protected $servCollecFactory;

    /**
     * @var SigepWebServicesSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @param ResourceSigepWebServices $resource
     * @param SigepWebServicesFactory $serviceFactory
     * @param SigepWebServicesCollectionFactory $servCollecFactory
     * @param SigepWebServicesSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceSigepWebServices $resource,
        SigepWebServicesFactory $serviceFactory,
        SigepWebServicesCollectionFactory $servCollecFactory,
        SigepWebServicesSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->serviceFactory = $serviceFactory;
        $this->servCollecFactory = $servCollecFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritdoc
     */
    public function save(SigepWebServicesInterface $service)
    {
        try {
            $this->resource->save($service);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the service: %1', $exception->getMessage()),
                $exception
            );
        }
        return $service;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function getById($id)
    {
        $service = $this->serviceFactory->create();
        $this->resource->load($service, $id);
        if (!$service->getId()) {
            throw new NoSuchEntityException(__('Sigep Web Service with id "%1" does not exist.', $id));
        }
        return $service;
    }

    /**
     * @inheritdoc
     */
    public function getByCode($code)
    {
        $service = $this->serviceFactory->create();
        $this->resource->load($service, $code, 'code');
        if (!$service->getId()) {
            throw new NoSuchEntityException(__('Sigep Web Service with code "%1" does not exist.', $code));
        }
        return $service;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->servCollecFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(SigepWebServicesInterface $service)
    {
        try {
            $this->resource->delete($service);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the service: %1', $exception->getMessage())
            );
        }
        return true;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function deleteById($id)
    {
        return $this->delete($this->getById($id));
    }
}
