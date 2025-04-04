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
use O2TI\SigepWebCarrier\Gateway\Service\PlpAsyncLabelService;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\Plp\Source\StatusItem as PlpStatusItem;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

class PlpLabelRequest extends AbstractPlpOperation
{
    /**
     * @var PlpAsyncLabelService
     */
    protected $plpAsyncLabelService;

    /**
     * Constructor
     *
     * @param Json $json
     * @param LoggerInterface $logger
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpAsyncLabelService $plpAsyncLabelService
     * @param PlpOrderCollectionFactory $plpOrdCollection
     * @param PlpCollectionFactory $plpCollectionFactory
     */
    public function __construct(
        Json $json,
        LoggerInterface $logger,
        PlpRepositoryInterface $plpRepository,
        PlpAsyncLabelService $plpAsyncLabelService,
        PlpOrderCollectionFactory $plpOrdCollection,
        PlpCollectionFactory $plpCollectionFactory
    ) {
        $this->plpAsyncLabelService = $plpAsyncLabelService;
        
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
        $this->operationName = 'label request';
        
        // Define PPN statuses
        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_RECEIPT;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_FILE_CREATION;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_REQUESTING_RECEIPT;
        
        // Define order statuses
        $this->expectedTypeFilter = 'status';
        $this->expectedOrderStatus = [
            PlpStatusItem::STATUS_ITEM_SUBMIT_CREATED,
            PlpStatusItem::STATUS_ITEM_PENDING_REQUEST_LABELS
        ];
        $this->inProgressOrdStatus = PlpStatusItem::STATUS_ITEM_PROCESSING_REQUEST_LABELS;
        $this->successOrderStatus = PlpStatusItem::STATUS_ITEM_RECEIPT_CREATED;
        $this->failureOrderStatus = PlpStatusItem::STATUS_ITEM_RECEIPT_ERROR;
    }
    
    /**
     * Create initial result structure
     *
     * @return array
     */
    protected function createInitialResult()
    {
        return $this->createSuccessResponse(
            __('Label requests submitted successfully'),
            [
                'success_count' => 0,
                'error_count' => 0,
                'receipts' => []
            ]
        );
    }
    
    /**
     * Get message for when no eligible orders are found
     *
     * @param int $plpId
     * @return \Magento\Framework\Phrase
     */
    protected function getNoOrdersMessage($plpId)
    {
        return __('No processed orders found in PPN %1', $plpId);
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
            $processingData = $plpOrder->getProcessingData();

            if (empty($processingData)) {
                throw new LocalizedException(__(
                    'PPN Order %d has no processing data',
                    $plpOrder->getId()
                ));
            }
            
            $processingData = $this->json->unserialize($processingData);
            
            if (isset($processingData['labelReceiptId'])) {
                $this->logger->info(__(
                    'PPN Order %1 already has a label receipt ID: %2',
                    $plpOrder->getId(),
                    $processingData['labelReceiptId']
                ));
                $result['success_count']++;
                return true;
            }
            
            if (!isset($processingData['tracking'])) {
                throw new LocalizedException(__(
                    'PPN Order %d has no tracking code',
                    $plpOrder->getId()
                ));
            }
            
            $trackingCode = $processingData['tracking'];
            
            $serviceResult = $this->plpAsyncLabelService->execute($trackingCode);
            
            if (!$serviceResult['success']) {
                throw new LocalizedException(__(
                    'Failed to request label for tracking code %s: %s',
                    $trackingCode,
                    $serviceResult['message']
                ));
            }
            
            if (!isset($serviceResult['data']['idRecibo'])) {
                throw new LocalizedException(__(
                    'Invalid response for tracking code %s: Missing receipt ID',
                    $trackingCode
                ));
            }
            
            $receiptId = $serviceResult['data']['idRecibo'];
            
            $processingData['labelReceiptId'] = $receiptId;
            
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->successOrderStatus,
                $processingData
            );
            
            $result['receipts'][] = [
                'plp_order_id' => $plpOrder->getId(),
                'tracking_code' => $trackingCode,
                'receipt_id' => $receiptId
            ];
            
            $result['success_count']++;
            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->error(__(
                'Error requesting label for PPN order %1: %2',
                $plpOrder->getId(),
                $errorMessage
            ));
            
            // Armazenar mensagem de erro no campo error_message
            $plpOrder->setErrorMessage($errorMessage);
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->failureOrderStatus
            );
            
            $result['error_count']++;
            return false;
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
     * Get PLPs with submitted status
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    public function getPlpsWithSubmittedStatus()
    {
        return $this->getPlpsByStatus(PlpStatus::STATUS_PLP_REQUESTING_RECEIPT);
    }
}
