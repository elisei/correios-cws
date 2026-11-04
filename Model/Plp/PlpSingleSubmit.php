<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Plp;

use Psr\Log\LoggerInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Gateway\Service\PlpSingleSubmitService;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\Plp\Source\StatusItem as PlpStatusItem;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

class PlpSingleSubmit extends AbstractPlpOperation
{
    /**
     * @var PlpSingleSubmitService
     */
    protected $plpSingleService;

    /**
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * Constructor
     *
     * @param Json $json
     * @param LoggerInterface $logger
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpSingleSubmitService $plpSingleService
     * @param PlpOrderCollectionFactory $plpOrdCollection
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param EventManagerInterface $eventManager
     */
    public function __construct(
        Json $json,
        LoggerInterface $logger,
        PlpRepositoryInterface $plpRepository,
        PlpSingleSubmitService $plpSingleService,
        PlpOrderCollectionFactory $plpOrdCollection,
        PlpCollectionFactory $plpCollectionFactory,
        EventManagerInterface $eventManager
    ) {
        $this->plpSingleService = $plpSingleService;
        $this->eventManager = $eventManager;

        parent::__construct(
            $logger,
            $plpRepository,
            $json,
            $plpOrdCollection,
            $plpCollectionFactory
        );
    }
    
    /**
     * Initialize configuration
     */
    protected function initialize()
    {
        $this->operationName = 'PPN submission';
        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_IN_COMMUNICATION;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_RECEIPT;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        
        $this->expectedTypeFilter = 'status';
        $this->expectedOrderStatus = [PlpStatusItem::STATUS_ITEM_COLLECTION_COMPLETED, PlpStatusItem::STATUS_ITEM_SUBMIT_ERROR];
        $this->inProgressOrdStatus = PlpStatusItem::STATUS_ITEM_PROCESSING_SUBMIT;
        $this->successOrderStatus = PlpStatusItem::STATUS_ITEM_SUBMIT_CREATED;
        $this->failureOrderStatus = PlpStatusItem::STATUS_ITEM_SUBMIT_ERROR;
    }
    
    /**
     * Create initial result structure
     *
     * @return array
     */
    protected function createInitialResult()
    {
        return $this->createSuccessResponse(
            __('PPN submitted successfully'),
            [
                'data' => [],
                'success_orders' => 0,
                'failed_orders' => 0
            ]
        );
    }
    
    /**
     * Get message for when no eligible orders are found
     *
     * @param int $plpId
     * @return string|\Magento\Framework\Phrase
     */
    protected function getNoOrdersMessage($plpId)
    {
        return __('No orders with collected data found in PPN %1', $plpId);
    }

    /**
     * Process individual PPN order
     *
     * @param object $plpOrder
     * @param array $result
     * @return bool
     */
    protected function processPlpOrder($plpOrder, &$result)
    {
        try {
            $request = $plpOrder->getCollectedData();
            if (empty($request)) {
                throw new LocalizedException(__('Order %1 has no collected data', $plpOrder->getOrderId()));
            }
            
            $request = $this->json->unserialize($request);
            
            $serviceResult = $this->plpSingleService->execute($request);

            if (!$serviceResult['success'] || empty($serviceResult['data'])) {
                throw new LocalizedException(__('Service error: %1', $serviceResult['message'] ?? 'Unknown error'));
            }

            if (!isset($serviceResult['data']['id'], $serviceResult['data']['codigoObjeto'])) {
                throw new LocalizedException(
                    __('Service response missing required fields: %1', $this->json->serialize($serviceResult['data']))
                );
            }

            $processingData = [
                'id' => $serviceResult['data']['id'],
                'tracking' => $serviceResult['data']['codigoObjeto']
            ];

            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->successOrderStatus,
                $processingData
            );
            
            $result['success_orders']++;
            
            if (!empty($serviceResult['data'])) {
                $result['data'] = $serviceResult['data'];
            }
            
            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->error(__(
                'Error submitting PPN order %1: %2',
                $plpOrder->getId(),
                $errorMessage
            ));
            
            // Armazenar a mensagem de erro no campo error_message
            $plpOrder->setErrorMessage($errorMessage);
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->failureOrderStatus
            );

            $this->dispatchOrderErrorEvent($plpOrder, $errorMessage);

            $result['failed_orders']++;
            return false;
        }
    }

    /**
     * Dispatch event when an order fails during single submit.
     *
     * @param object $plpOrder
     * @param string $errorMessage
     * @return void
     */
    private function dispatchOrderErrorEvent($plpOrder, $errorMessage)
    {
        try {
            $this->eventManager->dispatch('sigepweb_plp_order_process_error', [
                'plp_id'        => (int) $plpOrder->getPlpId(),
                'order_id'      => $plpOrder->getOrderId(),
                'error_message' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(__(
                'Error dispatching sigepweb_plp_order_process_error event for order %1: %2',
                $plpOrder->getOrderId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Update final PPN status based on processing results
     *
     * @param object $plp
     * @param int $successCount
     * @param int $errorCount
     */
    protected function updateFinalPlpStatus($plp, $successCount, $errorCount)
    {
        if ($successCount > 0 && $errorCount === 0) {
            $plp->setStatus($this->successPlpStatus);
        } elseif ($successCount > 0 && $errorCount > 0) {
            $plp->setStatus($this->successPlpStatus);
        } elseif ($errorCount) {
            $plp->setStatus($this->failurePlpStatus);
        }
        
        $this->plpRepository->save($plp);
    }

    /**
     * Get PLPs that are ready for submission
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    public function getPlpsWithCollectedData()
    {
        return $this->getPlpsByStatus(PlpStatus::STATUS_PLP_COLLECTING_DATA);
    }
}
