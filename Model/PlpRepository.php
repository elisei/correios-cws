<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp as PlpResource;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder as PlpOrderResource;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpSearchResultsInterfaceFactory;
use O2TI\SigepWebCarrier\Api\Data\PlpSearchResultsInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status;

/**
 * Correios Plp Repository.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlpRepository implements PlpRepositoryInterface
{
    /**
     * @var PlpResource
     */
    private $resource;

    /**
     * @var PlpFactory
     */
    private $plpFactory;

    /**
     * @var PlpOrderFactory
     */
    private $plpOrderFactory;

    /**
     * @var PlpOrderResource
     */
    private $plpOrderResource;

    /**
     * @var PlpCollectionFactory
     */
    private $plpCollectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var PlpSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var Status
     */
    protected $statusModel;

    /**
     * @param PlpResource $resource
     * @param PlpFactory $plpFactory
     * @param PlpOrderFactory $plpOrderFactory
     * @param PlpOrderResource $plpOrderResource
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param PlpSearchResultsInterfaceFactory $searchResultsFactory
     * @param Status $statusModel
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function __construct(
        PlpResource $resource,
        PlpFactory $plpFactory,
        PlpOrderFactory $plpOrderFactory,
        PlpOrderResource $plpOrderResource,
        PlpCollectionFactory $plpCollectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        PlpSearchResultsInterfaceFactory $searchResultsFactory,
        Status $statusModel
    ) {
        $this->resource = $resource;
        $this->plpFactory = $plpFactory;
        $this->plpOrderFactory = $plpOrderFactory;
        $this->plpOrderResource = $plpOrderResource;
        $this->plpCollectionFactory = $plpCollectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->statusModel = $statusModel;
    }

    /**
     * @inheritDoc
     */
    public function save(PlpInterface $plp)
    {
        try {

            if ($plp->getStatus()) {
                $permissions = $this->statusModel->getActionPermissions($plp->getStatus());
                $plp->setCanAddOrders($permissions['can_add_orders']);
            }

            $this->resource->save($plp);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save PPN: %1', $exception->getMessage()),
                $exception
            );
        }
        return $plp;
    }

    /**
     * @inheritDoc
     */
    public function getById($plpId)
    {
        $plp = $this->plpFactory->create();
        $this->resource->load($plp, $plpId);
        if (!$plp->getId()) {
            throw new NoSuchEntityException(__('PPN with id "%1" does not exist.', $plpId));
        }
        return $plp;
    }

    /**
     * @inheritDoc
     */
    public function delete(PlpInterface $plp)
    {
        try {
            $this->resource->delete($plp);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete PPN: %1', $exception->getMessage())
            );
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->plpCollectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function addOrderToPlp($plpId, $orderId, $username = null)
    {
        try {
            $orderIds = is_array($orderId) ? $orderId : [$orderId];
            
            // Verifica pedidos já existentes na PPN
            $existingOrders = [];
            foreach ($orderIds as $id) {
                $plpOrder = $this->plpOrderFactory->create();
                $plpOrder->getResource()->loadByPlpAndOrder($plpOrder, $plpId, $id);
                if ($plpOrder->getId()) {
                    $existingOrders[] = $id;
                }
            }
            
            if (!empty($existingOrders)) {
                throw new CouldNotSaveException(
                    __('Orders "%1" are already in PPN "%2"', implode(', ', $existingOrders), $plpId)
                );
            }

            foreach ($orderIds as $id) {
                $plpOrder = $this->plpOrderFactory->create();
                $plpOrder->setPlpId($plpId)
                    ->setOrderId($id)
                    ->setUsername($username);
                
                $this->plpOrderResource->save($plpOrder);
            }
            
            return true;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not add order(s) to PPN: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function updateOrderStatus($plpId, $orderId, $status, $errorMessage = null, $shipmentId = null)
    {
        try {
            $plp = $this->getById($plpId);
            
            $plpOrder = $this->plpOrderFactory->create();
            $plpOrder->getResource()->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PPN "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setStatus($status)
                ->setErrorMessage($errorMessage)
                ->setShipmentId($shipmentId);
            
            $this->plpOrderResource->save($plpOrder);
            
            if ($status === 'success') {
                $allOrders = $this->plpOrderFactory->create()->getCollection()
                    ->addFieldToFilter('plp_id', $plpId);
                
                $allSuccess = true;
                foreach ($allOrders as $order) {
                    if ($order->getStatus() !== 'success') {
                        $allSuccess = false;
                        break;
                    }
                }
                
                if ($allSuccess) {
                    $plp->setStatus('processing');
                    $this->resource->save($plp);
                }
            }
            
            return true;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not update order status: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById($plpId)
    {
        try {
            $plp = $this->getById($plpId);

            if ($plp->getStatus() === 'processing') {
                throw new LocalizedException(
                    __('Cannot delete PPN that is being processed.')
                );
            }
            
            $this->resource->delete($plp);
            return true;
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete PPN: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function updateOrderCollectedData($plpId, $orderId, $collectedData)
    {
        try {
            $plpOrder = $this->plpOrderFactory->create();
            $plpOrder->getResource()->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PPN "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setCollectedData($collectedData);
            $this->plpOrderResource->save($plpOrder);
            
            return true;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not update order collected data: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function updateOrderProcessingData($plpId, $orderId, $processingData)
    {
        try {
            $plpOrder = $this->plpOrderFactory->create();
            $plpOrder->getResource()->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PPN "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setProcessingData($processingData);
            $this->plpOrderResource->save($plpOrder);
            
            return true;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not update order processing data: %1', $exception->getMessage()),
                $exception
            );
        }
    }
}
