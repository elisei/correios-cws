<?php
namespace O2TI\SigepWebCarrier\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp as PlpResource;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder as PlpOrderResource;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpSearchResultsInterfaceFactory;
use O2TI\SigepWebCarrier\Api\Data\PlpSearchResultsInterface;

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
     * @param PlpResource $resource
     * @param PlpFactory $plpFactory
     * @param PlpOrderFactory $plpOrderFactory
     * @param PlpOrderResource $plpOrderResource
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param PlpSearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        PlpResource $resource,
        PlpFactory $plpFactory,
        PlpOrderFactory $plpOrderFactory,
        PlpOrderResource $plpOrderResource,
        PlpCollectionFactory $plpCollectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        PlpSearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->plpFactory = $plpFactory;
        $this->plpOrderFactory = $plpOrderFactory;
        $this->plpOrderResource = $plpOrderResource;
        $this->plpCollectionFactory = $plpCollectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritDoc
     */
    public function save(PlpInterface $plp)
    {
        try {
            $this->resource->save($plp);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save PLP: %1', $exception->getMessage()),
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
            throw new NoSuchEntityException(__('PLP with id "%1" does not exist.', $plpId));
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
                __('Could not delete PLP: %1', $exception->getMessage())
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
    public function addOrderToPlp($plpId, $orderId)
    {
        try {
            $plp = $this->getById($plpId);
            $orderIds = is_array($orderId) ? $orderId : [$orderId];
            
            // Verifica pedidos já existentes na PLP
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
                    __('Orders "%1" are already in PLP "%2"', implode(', ', $existingOrders), $plpId)
                );
            }
            
            // Adiciona os pedidos à PLP
            foreach ($orderIds as $id) {
                $plpOrder = $this->plpOrderFactory->create();
                $plpOrder->setPlpId($plpId)
                    ->setOrderId($id)
                    ->setStatus('pending');
                
                $this->plpOrderResource->save($plpOrder);
            }
            
            return true;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not add order(s) to PLP: %1', $exception->getMessage()),
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
                    __('Order with id "%1" does not exist in PLP "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setStatus($status)
                ->setErrorMessage($errorMessage)
                ->setShipmentId($shipmentId);
            
            $this->plpOrderResource->save($plpOrder);
            
            // Atualizar status da PLP se necessário
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
            
            // Verificar se a PLP está em um estado que permite exclusão
            if ($plp->getStatus() === 'processing') {
                throw new LocalizedException(
                    __('Cannot delete PLP that is being processed.')
                );
            }
            
            $this->resource->delete($plp);
            return true;
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete PLP: %1', $e->getMessage())
            );
        }
    }
}
