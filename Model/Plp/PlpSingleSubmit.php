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
    protected $plpSingleSubmitService;

    /**
     * Constructor
     *
     * @param Json $json
     * @param LoggerInterface $logger
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpSingleSubmitService $plpSingleSubmitService
     * @param PlpOrderCollectionFactory $plpOrderCollectionFactory
     * @param PlpCollectionFactory $plpCollectionFactory
     */
    public function __construct(
        Json $json,
        LoggerInterface $logger,
        PlpRepositoryInterface $plpRepository,
        PlpSingleSubmitService $plpSingleSubmitService,
        PlpOrderCollectionFactory $plpOrderCollectionFactory,
        PlpCollectionFactory $plpCollectionFactory
    ) {
        $this->plpSingleSubmitService = $plpSingleSubmitService;
        
        parent::__construct(
            $logger,
            $plpRepository,
            $json,
            $plpOrderCollectionFactory,
            $plpCollectionFactory
        );
    }
    
    /**
     * Initialize configuration
     */
    protected function initialize()
    {
        $this->operationName = 'PLP submission';
        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_IN_COMMUNICATION;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_RECEIPT;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        
        $this->expectedTypeFilterOrder = 'status';
        $this->expectedOrderStatuses = PlpStatusItem::STATUS_ITEM_COLLECTION_COMPLETED;
        $this->inProgressOrderStatus = PlpStatusItem::STATUS_ITEM_PROCESSING_SUBMIT;
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
            __('PLP submitted successfully'),
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
        return __('No orders with collected data found in PLP %1', $plpId);
    }

    /**
     * Process individual PLP order
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
            
            $serviceResult = $this->plpSingleSubmitService->execute($request);

            if (!$serviceResult['success']) {
                throw new LocalizedException(__('Service error: %1', $serviceResult['message']));
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
                'Error submitting PLP order %1: %2',
                $plpOrder->getId(),
                $errorMessage
            ));
            
            // Armazenar a mensagem de erro no campo error_message
            $plpOrder->setErrorMessage($errorMessage);
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->failureOrderStatus
            );
            
            $result['failed_orders']++;
            return false;
        }
    }
    
    /**
     * Update final PLP status based on processing results
     *
     * @param object $plp
     * @param int $successCount
     * @param int $errorCount
     */
    protected function updateFinalPlpStatus($plp, $successCount, $errorCount)
    {
        if ($successCount > 0 && $errorCount === 0) {
            $plp->setStatus($this->successPlpStatus);
            $message = __(
                'All %1 orders in PLP %2 were processed successfully',
                $successCount,
                $plp->getId()
            );
        } elseif ($successCount > 0 && $errorCount > 0) {
            $plp->setStatus($this->successPlpStatus);
            $message = __(
                '%1 orders processed successfully and %2 orders failed in PLP %3',
                $successCount,
                $errorCount,
                $plp->getId()
            );
        } else {
            $plp->setStatus($this->failurePlpStatus);
            $message = __(
                'All %1 orders in PLP %2 failed processing',
                $errorCount,
                $plp->getId()
            );
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
