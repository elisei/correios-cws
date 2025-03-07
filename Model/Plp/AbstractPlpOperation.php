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
use Magento\Framework\Phrase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\Plp\Source\StatusItem as PlpStatusItem;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
abstract class AbstractPlpOperation
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;
    
    /**
     * @var Json
     */
    protected $json;
    
    /**
     * @var PlpOrderCollectionFactory
     */
    protected $plpOrdCollection;
    
    /**
     * @var PlpCollectionFactory
     */
    protected $plpCollectionFactory;
    
    /**
     * Operation name for logging and messages
     *
     * @var string
     */
    protected $operationName = 'PLP operation';
    
    /**
     * Expected PLP status for this operation
     *
     * @var string
     */
    protected $expectedPlpStatus;
    
    /**
     * In-progress PLP status for this operation
     *
     * @var string
     */
    protected $inProgressPlpStatus;
    
    /**
     * Success PLP status for this operation
     *
     * @var string
     */
    protected $successPlpStatus;
    
    /**
     * Failure PLP status for this operation
     *
     * @var string
     */
    protected $failurePlpStatus;
    
    /**
     * Expected order statuses for this operation
     *
     * @var array
     */
    protected $expectedOrderStatus = [];
    
    /**
     * Expected type filter for match
     *
     * @var string
     */
    protected $expectedTypeFilter;

    /**
     * In-progress order status for this operation
     *
     * @var string
     */
    protected $inProgressOrdStatus;
    
    /**
     * Success order status for this operation
     *
     * @var string
     */
    protected $successOrderStatus;
    
    /**
     * Failure order status for this operation
     *
     * @var string
     */
    protected $failureOrderStatus;
    
    /**
     * @var array
     */
    protected $standResponseFields = [
        'success' => true,
        'message' => '',
        'processed' => 0,
        'errors' => 0
    ];

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param PlpRepositoryInterface $plpRepository
     * @param Json $json
     * @param PlpOrderCollectionFactory $plpOrdCollection
     * @param PlpCollectionFactory $plpCollectionFactory
     */
    public function __construct(
        LoggerInterface $logger,
        PlpRepositoryInterface $plpRepository,
        Json $json,
        PlpOrderCollectionFactory $plpOrdCollection,
        PlpCollectionFactory $plpCollectionFactory
    ) {
        $this->logger = $logger;
        $this->plpRepository = $plpRepository;
        $this->json = $json;
        $this->plpOrdCollection = $plpOrdCollection;
        $this->plpCollectionFactory = $plpCollectionFactory;
        
        $this->initialize();
    }
    
    /**
     * Initialize configuration - to be implemented by subclasses
     */
    abstract protected function initialize();
    
    /**
     * Process an individual PLP order
     *
     * @param object $plpOrder The PLP order to process
     * @param array $result Reference to result array to update processing statistics
     * @return bool True if processing was successful
     */
    abstract protected function processPlpOrder($plpOrder, &$result);

    /**
     * Create a success response
     *
     * @param string|Phrase $message
     * @param array $data Additional data to include in response
     * @return array
     */
    protected function createSuccessResponse($message, $data = [])
    {
        $response = array_merge($this->standResponseFields, [
            'success' => true,
            'message' => $message
        ]);

        foreach ($data as $key => $value) {
            $response[$key] = $value;
        }

        return $response;
    }

    /**
     * Create an error response
     *
     * @param string|Phrase $message
     * @param array $data Additional data to include in response
     * @param \Exception|null $exception
     * @return array
     */
    protected function createErrorResponse($message, $data = [], $exception = null)
    {
        $response = array_merge($this->standResponseFields, [
            'success' => false,
            'message' => $message
        ]);

        foreach ($data as $key => $value) {
            $response[$key] = $value;
        }

        if ($exception !== null) {
            $this->logger->critical($exception);
            if (!isset($response['exception'])) {
                $response['exception'] = $exception->getMessage();
            }
        }

        return $response;
    }

    /**
     * Generate result message based on process counts
     *
     * @param int $successCount
     * @param int $errorCount
     * @param int $plpId
     * @param string|null $customOperationName Optional override for operation name
     * @return array
     */
    protected function generateResultMessage($successCount, $errorCount, $plpId, $customOperationName = null)
    {
        $operationName = $customOperationName ?? $this->operationName;
        
        $result = [
            'success' => true,
            'message' => '',
            'processed' => $successCount,
            'errors' => $errorCount
        ];

        if ($successCount > 0 && $errorCount == 0) {
            $result['message'] = __('Successfully %1 %2 items for PLP %3', $operationName, $successCount, $plpId);
        } elseif ($successCount > 0 && $errorCount > 0) {
            $result['message'] = __(
                '%1 %2 items for PLP %3 with %4 errors',
                $operationName,
                $successCount,
                $plpId,
                $errorCount
            );
        } elseif ($successCount == 0 && $errorCount > 0) {
            $result['success'] = false;
            $result['message'] = __(
                'Failed to %1 any items for PLP %2 (%3 errors)',
                $operationName,
                $plpId,
                $errorCount
            );
        }

        return $result;
    }

    /**
     * Get PLP by ID with flexible validation
     *
     * @param int $plpId
     * @param string|null $expectedStatus Expected PLP status (optional)
     * @param bool $strictValidation If true, error on invalid status
     * @return array|object Returns array with error or PLP object
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    protected function getPlpWithValidation($plpId, $expectedStatus = null, $strictValidation = false)
    {
        try {
            $plp = $this->plpRepository->getById($plpId);
            
            if (!$plp) {
                return $this->createErrorResponse(__('PLP does not exist'));
            }

            if ($expectedStatus !== null && $plp->getStatus() !== $expectedStatus) {
                $message = __(
                    'PLP is not in expected status. Current: %1, Expected: %2',
                    $plp->getStatus(),
                    $expectedStatus
                );
                
                if ($strictValidation) {
                    return $this->createErrorResponse($message);
                }
                
                $this->logger->info($message);
            }
            
            return $plp;
            
        } catch (\Exception $exc) {
            $this->logger->critical($exc);
            return $this->createErrorResponse(
                __('Error retrieving PLP %1: %2', $plpId, $exc->getMessage()),
                [],
                $exc
            );
        }
    }

    /**
     * Handle exception safely
     *
     * @param \Exception $exc Exception to handle
     * @param int $plpId PLP ID
     * @param string|null $customOperationName Operation being performed (optional override)
     * @param string|null $failureStatus Status to set on failure (optional override)
     * @return array Error response
     */
    protected function handleException($exc, $plpId, $customOperationName = null, $failureStatus = null)
    {
        $this->logger->critical($exc);
        $operationName = $customOperationName ?? $this->operationName;
        $failureStatus = $failureStatus ?? $this->failurePlpStatus;
        
        $errorResponse = $this->createErrorResponse(
            __('Error during %1 for PLP %2: %3', $operationName, $plpId, $exc->getMessage()),
            [],
            $exc
        );
            
        if ($failureStatus !== null) {
            try {
                $plp = $this->plpRepository->getById($plpId);
                if ($plp) {
                    $plp->setStatus($failureStatus);
                    $this->plpRepository->save($plp);
                }
            } catch (\Exception $saveEx) {
                $this->logger->critical($saveEx);
            }
        }
        
        return $errorResponse;
    }
    
    /**
     * Update PLP order status and optionally save processing data
     *
     * @param object $plpOrder PLP order object
     * @param string $status New status to set
     * @param array|null $processingData Optional processing data to save
     * @param array|null $collectData Optional collected data to save
     * @param int|null $shipmentId Optional id for shipment
     * @return bool Success
     */
    protected function updatePlpOrderStatus(
        $plpOrder,
        $status,
        $processingData = null,
        $collectData = null,
        $shipmentId = null
    ) {
        try {
            if ($processingData !== null) {
                if (is_array($processingData)) {
                    $processingData = $this->json->serialize($processingData);
                }
                $plpOrder->setProcessingData($processingData);
            }

            if ($collectData !== null) {
                if (is_array($collectData)) {
                    $collectData = $this->json->serialize($collectData);
                }
                $plpOrder->setCollectedData($collectData);
            }

            if ($shipmentId) {
                $plpOrder->setShipmentId($shipmentId);
            }

            $plpOrder->setStatus($status);
            $plpOrder->save();
            return true;
        } catch (\Exception $exc) {
            $this->logger->error(__(
                'Error updating PLP order status for ID %1: %2',
                $plpOrder->getId(),
                $exc->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get PLP orders with specific processing status
     *
     * @param int $plpId PLP ID
     * @param array|string $processingStatus Status(es) to filter by
     * @param string|null $typeFilter Type for Filter
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\Collection
     */
    protected function getPlpOrdersByStatus(
        $plpId,
        $processingStatus,
        $typeFilter = 'status'
    ) {
        $collection = $this->plpOrdCollection->create();
        $collection->addFieldToFilter('plp_id', $plpId);
        
        if (is_array($processingStatus)) {
            $collection->addFieldToFilter($typeFilter, ['in' => $processingStatus]);
        }

        if (!is_array($processingStatus)) {
            $collection->addFieldToFilter($typeFilter, $processingStatus);
        }
        
        return $collection;
    }
    
    /**
     * Get PLPs with specific status
     *
     * @param string|array $status Status(es) to filter by
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    protected function getPlpsByStatus($status)
    {
        $collection = $this->plpCollectionFactory->create();
        
        if (is_array($status)) {
            $collection->addFieldToFilter('status', ['in' => $status]);
        }

        if (is_array($status)) {
            $collection->addFieldToFilter('status', $status);
        }
        
        return $collection;
    }
    
    /**
     * Template method for executing PLP operations
     *
     * @param int $plpId
     * @return array
     */
    public function execute($plpId)
    {
        $result = $this->createInitialResult();

        try {
            $plp = $this->getPlpWithValidation($plpId, $this->expectedPlpStatus, true);
            
            if (is_array($plp)) {
                return $plp; // Return error response if validation failed
            }

            $plp->setStatus($this->inProgressPlpStatus);
            $this->plpRepository->save($plp);

            $plpOrders = $this->getEligibleOrders($plpId);
            
            if ($plpOrders->getSize() === 0) {
                $plp->setStatus($this->failurePlpStatus);
                $this->plpRepository->save($plp);
                
                return $this->createErrorResponse(
                    $this->getNoOrdersMessage($plpId),
                    $result
                );
            }

            $successCount = 0;
            $errorCount = 0;

            foreach ($plpOrders as $plpOrder) {
                try {
                    $this->updatePlpOrderStatus($plpOrder, $this->inProgressOrdStatus);
                    
                    $success = $this->processPlpOrder($plpOrder, $result);
                    
                    if ($success) {
                        $successCount++;
                    }

                    if (!$success) {
                        $errorCount++;
                    }

                } catch (\Exception $exc) {
                    $this->logger->error(__(
                        'Error processing order %1 in PLP %2: %3',
                        $plpOrder->getOrderId(),
                        $plpId,
                        $exc->getMessage()
                    ));
                    
                    $this->updatePlpOrderStatus(
                        $plpOrder,
                        $this->failureOrderStatus,
                        ['error' => $exc->getMessage()]
                    );
                    
                    $errorCount++;
                }
            }

            $this->updateFinalPlpStatus($plp, $successCount, $errorCount);
            
            $result = $this->generateResultMessage($successCount, $errorCount, $plpId);
            $result['processed'] = $successCount;
            $result['errors'] = $errorCount;

        } catch (\Exception $exc) {
            $result = $this->handleException($exc, $plpId);
        }

        return $result;
    }
    
    /**
     * Get eligible orders for processing in this operation
     *
     * @param int $plpId
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\Collection
     */
    protected function getEligibleOrders($plpId)
    {
        return $this->getPlpOrdersByStatus($plpId, $this->expectedOrderStatus, $this->expectedTypeFilter);
    }
    
    /**
     * Create initial result structure - may be overridden by subclasses
     *
     * @return array
     */
    protected function createInitialResult()
    {
        return $this->createSuccessResponse(
            __('Operation completed successfully'),
            [
                'processed' => 0,
                'errors' => 0
            ]
        );
    }
    
    /**
     * Get message for when no eligible orders are found
     *
     * @param int $plpId
     * @return string|Phrase
     */
    protected function getNoOrdersMessage($plpId)
    {
        return __('No eligible orders found for %1 in PLP %2', $this->operationName, $plpId);
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
        } elseif ($successCount > 0) {
            $plp->setStatus($this->successPlpStatus);
        } elseif ($errorCount > 0) {
            $plp->setStatus($this->failurePlpStatus);
        }
        
        $this->plpRepository->save($plp);
    }
}
