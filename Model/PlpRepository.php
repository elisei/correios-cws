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
use O2TI\SigepWebCarrier\Model\Plp\StatusManager;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpSearchResultsInterfaceFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Class PlpRepository - Gerencia operações da PLP
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
     * @var StatusManager
     */
    private $statusManager;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @param PlpResource $resource
     * @param PlpFactory $plpFactory
     * @param PlpOrderFactory $plpOrderFactory
     * @param PlpOrderResource $plpOrderResource
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param PlpSearchResultsInterfaceFactory $searchResultsFactory
     * @param StatusManager $statusManager
     * @param DateTime $dateTime
     */
    public function __construct(
        PlpResource $resource,
        PlpFactory $plpFactory,
        PlpOrderFactory $plpOrderFactory,
        PlpOrderResource $plpOrderResource,
        PlpCollectionFactory $plpCollectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        PlpSearchResultsInterfaceFactory $searchResultsFactory,
        StatusManager $statusManager,
        DateTime $dateTime
    ) {
        $this->resource = $resource;
        $this->plpFactory = $plpFactory;
        $this->plpOrderFactory = $plpOrderFactory;
        $this->plpOrderResource = $plpOrderResource;
        $this->plpCollectionFactory = $plpCollectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->statusManager = $statusManager;
        $this->dateTime = $dateTime;
    }

    /**
     * @inheritDoc
     */
    public function save(PlpInterface $plp)
    {
        try {
            // Se for uma PLP existente, valida a transição de status
            if ($plp->getId()) {
                $originalPlp = $this->getById($plp->getId());
                $newStatus = $plp->getStatus();
                
                if ($originalPlp->getStatus() !== $newStatus &&
                    !$this->statusManager->isValidTransition($originalPlp->getStatus(), $newStatus)) {
                    throw new LocalizedException(
                        __('Invalid status transition from %1 to %2', $originalPlp->getStatus(), $newStatus)
                    );
                }
            }

            // Atualiza flags baseadas no status
            $this->statusManager->updatePlpFlags($plp);

            $this->resource->save($plp);
            return $plp;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save PLP: %1', $e->getMessage()),
                $e
            );
        }
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
    public function deleteById($plpId)
    {
        try {
            $plp = $this->getById($plpId);

            // Verifica se a PLP pode ser excluída baseado no status
            if (!$this->canDeletePlp($plp)) {
                throw new LocalizedException(
                    __('Cannot delete PLP in status: %1', $plp->getStatus())
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

    /**
     * @inheritDoc
     */
    public function transitionStatus($plpId, $newStatus, $message = null)
    {
        try {
            $plp = $this->getById($plpId);
            $currentStatus = $plp->getStatus();

            if (!$this->statusManager->isValidTransition($currentStatus, $newStatus)) {
                throw new LocalizedException(
                    __('Invalid status transition from %1 to %2', $currentStatus, $newStatus)
                );
            }

            // Atualiza histórico de status
            $history = $plp->getStatusHistory() ? json_decode($plp->getStatusHistory(), true) : [];
            $history[] = [
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'message' => $message,
                'date' => $this->dateTime->gmtDate()
            ];

            $plp->setStatus($newStatus)
                ->setStatusHistory(json_encode($history));

            // Atualiza flags baseadas no novo status
            $this->statusManager->updatePlpFlags($plp);

            $this->save($plp);
            return true;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Could not transition PLP status: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function addOrderToPlp($plpId, array $orderIds)
    {
        try {
            $plp = $this->getById($plpId);

            if (!$plp->getCanAddOrders()) {
                throw new LocalizedException(
                    __('Cannot add orders to PLP in status: %1', $plp->getStatus())
                );
            }

            // Verifica pedidos já existentes
            $existingOrders = [];
            foreach ($orderIds as $orderId) {
                $plpOrder = $this->plpOrderFactory->create();
                $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
                if ($plpOrder->getId()) {
                    $existingOrders[] = $orderId;
                }
            }

            if (!empty($existingOrders)) {
                throw new LocalizedException(
                    __('Orders already in PLP: %1', implode(', ', $existingOrders))
                );
            }

            // Adiciona novos pedidos
            foreach ($orderIds as $orderId) {
                $plpOrder = $this->plpOrderFactory->create();
                $plpOrder->setPlpId($plpId)
                    ->setOrderId($orderId)
                    ->setStatus('pending');
                $this->plpOrderResource->save($plpOrder);
            }

            return true;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Could not add orders to PLP: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function removeOrderFromPlp($plpId, $orderId)
    {
        try {
            $plp = $this->getById($plpId);

            if (!$plp->getCanRemoveOrders()) {
                throw new LocalizedException(
                    __('Cannot remove orders from PLP in status: %1', $plp->getStatus())
                );
            }

            $plpOrder = $this->plpOrderFactory->create();
            $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);

            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order %1 not found in PLP %2', $orderId, $plpId)
                );
            }

            $this->plpOrderResource->delete($plpOrder);
            return true;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Could not remove order from PLP: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function requestClosing($plpId)
    {
        try {
            $plp = $this->getById($plpId);

            if (!$plp->getCanRequestClosing()) {
                throw new LocalizedException(
                    __('Cannot request closing for PLP in status: %1', $plp->getStatus())
                );
            }

            // Verifica se existem pedidos na PLP
            $plpOrders = $this->plpOrderFactory->create()->getCollection()
                ->addFieldToFilter('plp_id', $plpId);

            if ($plpOrders->getSize() == 0) {
                throw new LocalizedException(
                    __('Cannot close PLP without orders')
                );
            }

            // Inicia processo de fechamento alterando o status
            return $this->transitionStatus(
                $plpId,
                \O2TI\SigepWebCarrier\Model\Plp\Source\Status::STATUS_COLLECTING,
                'Closing process initiated'
            );
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Could not request PLP closing: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getStatusDetails($plpId)
    {
        try {
            $plp = $this->getById($plpId);
            
            return [
                'current_status' => $plp->getStatus(),
                'can_add_orders' => $plp->getCanAddOrders(),
                'can_remove_orders' => $plp->getCanRemoveOrders(),
                'can_request_closing' => $plp->getCanRequestClosing(),
                'status_history' => json_decode($plp->getStatusHistory(), true) ?: [],
                'updated_at' => $plp->getUpdatedAt()
            ];
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Could not get PLP status details: %1', $e->getMessage())
            );
        }
    }

    /**
     * Verifica se a PLP pode ser excluída
     *
     * @param PlpInterface $plp
     * @return bool
     */
    private function canDeletePlp(PlpInterface $plp)
    {
        $nonDeletableStatuses = [
            \O2TI\SigepWebCarrier\Model\Plp\Source\Status::STATUS_PROCESSING,
            \O2TI\SigepWebCarrier\Model\Plp\Source\Status::STATUS_CREATING_SHIPMENT
        ];

        return !in_array($plp->getStatus(), $nonDeletableStatuses);
    }

    /**
     * @inheritDoc
     */
    public function updateOrderStatus($plpId, $orderId, $status, $errorMessage = null, $shipmentId = null)
    {
        try {
            $plpOrder = $this->plpOrderFactory->create();
            $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PLP "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setStatus($status)
                ->setErrorMessage($errorMessage)
                ->setShipmentId($shipmentId);
            
            $this->plpOrderResource->save($plpOrder);
            
            // Verifica se é necessário atualizar o status da PLP
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
                    $plp = $this->getById($plpId);
                    return $this->transitionStatus($plpId, 'processing', 'All orders processed successfully');
                }
            }
            
            return true;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not update order status: %1', $e->getMessage())
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
            $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PLP "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setCollectedData($collectedData)
                ->setCollectionStatus('collected');
            
            $this->plpOrderResource->save($plpOrder);

            // Verifica se todos os pedidos foram coletados
            $allOrders = $this->plpOrderFactory->create()->getCollection()
                ->addFieldToFilter('plp_id', $plpId);
            
            $allCollected = true;
            foreach ($allOrders as $order) {
                if ($order->getCollectionStatus() !== 'collected') {
                    $allCollected = false;
                    break;
                }
            }
            
            if ($allCollected) {
                return $this->transitionStatus($plpId, 'formed', 'All orders data collected');
            }
            
            return true;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not update order collected data: %1', $e->getMessage())
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
            $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PLP "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setProcessingData($processingData)
                ->setProcessingStatus('processed');
            
            $this->plpOrderResource->save($plpOrder);

            // Verifica se todos os pedidos foram processados
            $allOrders = $this->plpOrderFactory->create()->getCollection()
                ->addFieldToFilter('plp_id', $plpId);
            
            $allProcessed = true;
            foreach ($allOrders as $order) {
                if ($order->getProcessingStatus() !== 'processed') {
                    $allProcessed = false;
                    break;
                }
            }
            
            if ($allProcessed) {
                return $this->transitionStatus($plpId, 'processed', 'All orders data processed');
            }
            
            return true;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not update order processing data: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function updateOrderCollectionStatus($plpId, $orderId, $collectionStatus)
    {
        try {
            $plpOrder = $this->plpOrderFactory->create();
            $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PLP "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setCollectionStatus($collectionStatus);
            $this->plpOrderResource->save($plpOrder);
            
            return true;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not update order collection status: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function updateOrderProcessingStatus($plpId, $orderId, $processingStatus)
    {
        try {
            $plpOrder = $this->plpOrderFactory->create();
            $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);
            
            if (!$plpOrder->getId()) {
                throw new NoSuchEntityException(
                    __('Order with id "%1" does not exist in PLP "%2".', $orderId, $plpId)
                );
            }
            
            $plpOrder->setProcessingStatus($processingStatus);
            $this->plpOrderResource->save($plpOrder);
            
            return true;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not update order processing status: %1', $e->getMessage())
            );
        }
    }
}
