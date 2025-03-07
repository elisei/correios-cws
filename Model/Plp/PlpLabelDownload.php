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
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Url\DecoderInterface;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Gateway\Service\PlpLabelDownloadService;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\Plp\Source\StatusItem as PlpStatusItem;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

class PlpLabelDownload extends AbstractPlpOperation
{
    /**
     * @var PlpLabelDownloadService
     */
    protected $plpLabelDownloadService;
    
    /**
     * @var Filesystem
     */
    protected $filesystem;
    
    /**
     * @var DriverFile
     */
    protected $driver;
    
    /**
     * @var File
     */
    protected $fileIo;

    /**
     * @var DecoderInterface
     */
    protected $urlDecoder;
    
    /**
     * @var array
     */
    protected $result;

    /**
     * Constructor
     *
     * @param Json $json
     * @param LoggerInterface $logger
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpLabelDownloadService $plpLabelDownloadService
     * @param PlpOrderCollectionFactory $plpOrderCollectionFactory
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param Filesystem $filesystem
     * @param DriverFile $driver
     * @param File $fileIo
     * @param DecoderInterface $urlDecoder
     */
    public function __construct(
        Json $json,
        LoggerInterface $logger,
        PlpRepositoryInterface $plpRepository,
        PlpLabelDownloadService $plpLabelDownloadService,
        PlpOrderCollectionFactory $plpOrderCollectionFactory,
        PlpCollectionFactory $plpCollectionFactory,
        Filesystem $filesystem,
        DriverFile $driver,
        File $fileIo,
        DecoderInterface $urlDecoder
    ) {
        $this->plpLabelDownloadService = $plpLabelDownloadService;
        $this->filesystem = $filesystem;
        $this->driver = $driver;
        $this->fileIo = $fileIo;
        $this->urlDecoder = $urlDecoder;
        
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
        $this->operationName = 'label download';
        
        // Define PLP statuses
        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_FILE_CREATION;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_AWAITING_SHIPMENT;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
        
        // Define order statuses
        $this->expectedTypeFilterOrder = 'status';
        $this->expectedOrderStatuses = [
            PlpStatusItem::STATUS_ITEM_RECEIPT_CREATED,
            PlpStatusItem::STATUS_ITEM_PENDING_DOWNLOAD
        ];
        $this->inProgressOrderStatus = PlpStatusItem::STATUS_ITEM_PROCESSING_DOWNLOAD;
        $this->successOrderStatus = PlpStatusItem::STATUS_ITEM_DOWNLOAD_COMPLETED;
        $this->failureOrderStatus = PlpStatusItem::STATUS_ITEM_RECEIPT_CREATED;
    }
    
    /**
     * Create initial result structure
     *
     * @return array
     */
    protected function createInitialResult()
    {
        $this->result = $this->createSuccessResponse(
            __('Label downloads completed successfully'),
            [
                'success_count' => 0,
                'error_count' => 0,
                'sync_count' => 0,
                'downloads' => [],
                'synchronizing' => [],
                'files' => []
            ]
        );
        
        return $this->result;
    }
    
    /**
     * Get message for when no eligible orders are found
     *
     * @param int $plpId
     * @return \Magento\Framework\Phrase
     */
    protected function getNoOrdersMessage($plpId)
    {
        return __('No PLP orders with receipt IDs found in PLP %1', $plpId);
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
            $this->result = &$result;
            
            $processingData = $plpOrder->getProcessingData();

            if (empty($processingData)) {
                // phpcs:ignore Magento2.Exceptions.DirectThrow
                throw new \Exception(__(
                    'PLP Order %1 has no processing data',
                    $plpOrder->getId()
                ));
            }
            
            $processingData = $this->json->unserialize($processingData);
            
            if (!isset($processingData['labelReceiptId'])) {
                // phpcs:ignore Magento2.Exceptions.DirectThrow
                throw new \Exception(__(
                    'PLP Order %1 has no label receipt ID',
                    $plpOrder->getId()
                ));
            }

            $receiptId = $processingData['labelReceiptId'];
            
            $serviceResult = $this->plpLabelDownloadService->execute($receiptId);
            
            if (!$serviceResult['success']) {
                // phpcs:ignore Magento2.Exceptions.DirectThrow
                throw new \Exception(__(
                    'Failed to download label for receipt ID %1: %2',
                    $receiptId,
                    $serviceResult['message']
                ));
            }
            
            if ($this->isLabelSynchronizing($serviceResult)) {
                $this->handleSynchronizingLabel($plpOrder, $processingData, $receiptId, $result);
                return true; // This is a successful sync request, not an error
            }
            
            $fileName = $this->saveShippingLabelFile($serviceResult['data'], $plpOrder, $plpOrder->getPlpId());
            
            if (!$fileName) {
                // phpcs:ignore Magento2.Exceptions.DirectThrow
                throw new \Exception(__(
                    'Failed to save label file for receipt ID %1',
                    $receiptId
                ));
            }
            
            $processingData['labelFileName'] = $fileName;
            $result['files'][] = [
                'plp_order_id' => $plpOrder->getId(),
                'tracking_code' => $processingData['tracking'] ?? 'N/A',
                'receipt_id' => $receiptId,
                'file_name' => $fileName
            ];
            
            $this->saveDownloadData($plpOrder, $processingData, $serviceResult, $result);
            
            $result['success_count']++;
            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->critical(__(
                'Exception occurred while processing receipt ID %1: %2',
                $processingData['labelReceiptId'] ?? 'unknown',
                $errorMessage
            ));
            
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
     * Update final PLP status based on processing results
     *
     * @param object $plp
     * @param int $successCount
     * @param int $errorCount
     */
    protected function updateFinalPlpStatus($plp, $successCount, $errorCount)
    {
        $syncCount = isset($this->result['sync_count']) ? $this->result['sync_count'] : 0;
        
        if ($successCount > 0 && $errorCount === 0 && $syncCount === 0) {
            $plp->setStatus($this->successPlpStatus);
            $this->logger->info(__(
                'Setting PLP %1 status to %2 (success)',
                $plp->getId(),
                $this->successPlpStatus
            ));
        } elseif ($successCount > 0 || $syncCount > 0) {
            $plp->setStatus($this->expectedPlpStatus);
            $this->logger->info(__(
                'Setting PLP %1 status to %2 (partial success)',
                $plp->getId(),
                $this->expectedPlpStatus
            ));
        } else {
            $plp->setStatus($this->failurePlpStatus);
            $this->logger->info(__(
                'Setting PLP %1 status to %2 (failure)',
                $plp->getId(),
                $this->failurePlpStatus
            ));
            
            if ($errorCount > 0) {
                $this->resetFailedPlpOrdersToInitialState($plp->getId());
            }
        }
        
        $this->plpRepository->save($plp);
        
        $this->generateDownloadResultMessage($plp->getId());
    }

    /**
     * Reset failed PLP orders to their initial state
     *
     * @param int $plpId
     */
    protected function resetFailedPlpOrdersToInitialState($plpId)
    {
        $failedOrders = $this->getPlpOrdersByStatus($plpId, $this->failureOrderStatus);
        
        foreach ($failedOrders as $failedOrder) {
            $processingData = $failedOrder->getProcessingData();
            $processingData = $processingData ? $this->json->unserialize($processingData) : [];
            
            // Remover apenas flags de controle, não informações de erro
            unset($processingData['labelSynchronizing']);
            
            $failedOrder->setErrorMessage(null);
            
            $this->updatePlpOrderStatus(
                $failedOrder,
                PlpStatusItem::STATUS_ITEM_RECEIPT_CREATED,
                $processingData
            );
        }
    }
    
    /**
     * Generate appropriate result message based on success/error/sync counts
     *
     * @param int $plpId
     */
    private function generateDownloadResultMessage($plpId)
    {
        $successCount = $this->result['success_count'];
        $errorCount = $this->result['error_count'];
        $syncCount = $this->result['sync_count'];
        $fileCount = count($this->result['files']);
        
        if ($successCount > 0) {
            $this->result['message'] = __('Successfully downloaded %1 labels for PLP %2', $successCount, $plpId);
            
            if ($fileCount > 0) {
                $this->result['message'] = __('%1 and saved %2 label files', $this->result['message'], $fileCount);
            }
            
            if ($errorCount > 0) {
                $this->result['message'] = __('%1 (%2 errors occurred)', $this->result['message'], $errorCount);
            }
            
            if ($syncCount > 0) {
                $this->result['message'] = __('%1 (%2 still synchronizing)', $this->result['message'], $syncCount);
            }
        } elseif ($syncCount > 0) {
            $this->result['success'] = true;
            $this->result['message'] = __(
                'All %1 labels for PLP %2 are still synchronizing. Try again later.',
                $syncCount,
                $plpId
            );
        } else {
            $this->result['success'] = false;
            $this->result['message'] = __('Failed to download any labels for PLP %1', $plpId);
        }
    }
    
    /**
     * Check if label is still synchronizing
     *
     * @param array $serviceResult
     * @return bool
     */
    private function isLabelSynchronizing($serviceResult)
    {
        return isset($serviceResult['data']['status']) && $serviceResult['data']['status'] === 'synchronizing';
    }

    /**
     * Handle label that is still synchronizing
     *
     * @param object $plpOrder
     * @param array $processingData
     * @param string $receiptId
     * @param array $result
     */
    private function handleSynchronizingLabel($plpOrder, $processingData, $receiptId, &$result)
    {
        $this->logger->info(__(
            'Receipt ID %s is still synchronizing. Will try again later.',
            $receiptId
        ));
        
        $processingData['labelSynchronizing'] = true;
        $processingData['lastSyncAttempt'] = date('Y-m-d H:i:s');
        
        $this->updatePlpOrderStatus(
            $plpOrder,
            PlpStatusItem::STATUS_ITEM_PENDING_DOWNLOAD,
            $processingData
        );
        
        $trackingCode = $processingData['tracking'] ?? 'N/A';
        $result['synchronizing'][] = [
            'plp_order_id' => $plpOrder->getId(),
            'tracking_code' => $trackingCode,
            'receipt_id' => $receiptId
        ];
        
        $result['sync_count']++;
    }

    /**
     * Save download data to the PLP order
     *
     * @param object $plpOrder
     * @param array $processingData
     * @param array $serviceResult
     * @param array $result
     */
    private function saveDownloadData($plpOrder, $processingData, $serviceResult, &$result)
    {
        $processingData['labelDownloadData'] = [
            'status' => 'downloaded',
            'downloadedAt' => date('Y-m-d H:i:s')
        ];
        
        if (isset($processingData['labelFileName'])) {
            $processingData['labelDownloadData']['fileName'] = $processingData['labelFileName'];
        }
        
        unset($processingData['labelSynchronizing']);
        
        $this->updatePlpOrderStatus(
            $plpOrder,
            $this->successOrderStatus,
            $processingData
        );
        
        $trackingCode = $processingData['tracking'] ?? 'N/A';
        $receiptId = $processingData['labelReceiptId'];
        
        $result['downloads'][] = [
            'plp_order_id' => $plpOrder->getId(),
            'tracking_code' => $trackingCode,
            'receipt_id' => $receiptId
        ];
    }
    
    /**
     * Save shipping label as a file
     *
     * @param array $labelData
     * @param object $plpOrder
     * @param int $plpId
     * @return string|false
     */
    private function saveShippingLabelFile($labelData, $plpOrder, $plpId)
    {
        try {
            $processingData = $this->json->unserialize($plpOrder->getProcessingData());
            $trackingCode = $processingData['tracking'] ?? 'unknown';
            
            if (isset($labelData['nome']) && isset($labelData['dados'])) {
                $originalName = $labelData['nome'];
                $fileInfo = $this->fileIo->getPathInfo($originalName);
                $extension = $fileInfo['extension'] ?? 'pdf';
                $fileName = $this->generateLabelFileName($plpId, $plpOrder->getId(), $trackingCode, $extension);
                $filePath = $this->getLabelFilePath($fileName);
                $fileContent = $labelData['dados'];
                $fileCreated = $this->createLabelFile($filePath, $fileContent, 'base64');
                
                if ($fileCreated) {
                    return $fileName;
                }
            } else {
                $fileName = $this->generateLabelFileName($plpId, $plpOrder->getId(), $trackingCode);
                $filePath = $this->getLabelFilePath($fileName);
                
                $fileContent = isset($labelData['labelContent']) ?
                    $labelData['labelContent'] : $this->json->serialize($labelData);
                $fileCreated = $this->createLabelFile(
                    $filePath,
                    $fileContent,
                    isset($labelData['labelContentType']) ? $labelData['labelContentType'] : null
                );
                
                if ($fileCreated) {
                    return $fileName;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error(__(
                'Error saving label file for PLP Order %1: %2',
                $plpOrder->getId(),
                $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Generate a unique filename for the shipping label
     *
     * @param int $plpId
     * @param int $plpOrderId
     * @param string $trackingCode
     * @param string $extension
     * @return string
     */
    protected function generateLabelFileName($plpId, $plpOrderId, $trackingCode, $extension = 'pdf')
    {
        $timestamp = date('YmdHis');
        return 'sigepweb_label_plp_'
            . $plpId
            .'_order_'
            . $plpOrderId
            . '_'
            . $trackingCode
            . '_'
            . $timestamp
            . '.'
            . $extension;
    }

    /**
     * Get file path for the label file
     *
     * @param string $fileName
     * @return string
     */
    protected function getLabelFilePath($fileName)
    {
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $dirPath = 'sigepweb/labels';
        
        if (!$this->driver->isDirectory($mediaDirectory->getAbsolutePath($dirPath))) {
            $this->fileIo->mkdir($mediaDirectory->getAbsolutePath($dirPath), 0775);
        }
        
        return $mediaDirectory->getAbsolutePath($dirPath . '/' . $fileName);
    }

    /**
     * Create the label file
     *
     * @param string $filePath
     * @param string $content
     * @param string|null $contentType
     * @return bool
     */
    protected function createLabelFile($filePath, $content, $contentType = null)
    {
        try {
            if ($contentType && (strpos($contentType, 'base64') !== false || $contentType === 'base64')) {
                $content = $this->urlDecoder->decode($content);
            }
            
            $this->driver->filePutContents($filePath, $content);
            return true;
        } catch (\Exception $e) {
            $this->logger->critical(
                __('Error creating label file: %1', $e->getMessage())
            );
            return false;
        }
    }

    /**
     * Get PLPs with submitted status
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    public function getPlpsWithSubmittedStatus()
    {
        return $this->getPlpsByStatus(PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION);
    }
}
