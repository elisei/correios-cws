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
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Service\PlpDaceDownloadService;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\Plp\Source\StatusItem as PlpStatusItem;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlpDaceDownload extends AbstractPlpOperation
{
    /**
     * @var PlpDaceDownloadService
     */
    protected $plpDaceDownService;

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
     * @var Config
     */
    protected $config;

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
     * @param PlpDaceDownloadService $plpDaceDownService
     * @param PlpOrderCollectionFactory $plpOrdCollection
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param Filesystem $filesystem
     * @param DriverFile $driver
     * @param File $fileIo
     * @param DecoderInterface $urlDecoder
     * @param Config $config
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Json $json,
        LoggerInterface $logger,
        PlpRepositoryInterface $plpRepository,
        PlpDaceDownloadService $plpDaceDownService,
        PlpOrderCollectionFactory $plpOrdCollection,
        PlpCollectionFactory $plpCollectionFactory,
        Filesystem $filesystem,
        DriverFile $driver,
        File $fileIo,
        DecoderInterface $urlDecoder,
        Config $config
    ) {
        $this->plpDaceDownService = $plpDaceDownService;
        $this->filesystem = $filesystem;
        $this->driver = $driver;
        $this->fileIo = $fileIo;
        $this->urlDecoder = $urlDecoder;
        $this->config = $config;

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
        $this->operationName = 'DACE download';

        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_AWAITING_DACE;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;

        $this->expectedTypeFilter = 'status';
        $this->expectedOrderStatus = [
            PlpStatusItem::STATUS_ITEM_RECEIPT_CREATED,
            PlpStatusItem::STATUS_ITEM_PENDING_DACE,
            PlpStatusItem::STATUS_ITEM_DACE_ERROR
        ];
        $this->inProgressOrdStatus = PlpStatusItem::STATUS_ITEM_PROCESSING_DACE;
        $this->successOrderStatus = PlpStatusItem::STATUS_ITEM_DACE_COMPLETED;
        $this->failureOrderStatus = PlpStatusItem::STATUS_ITEM_DACE_ERROR;
    }

    /**
     * Create initial result structure
     *
     * @return array
     */
    protected function createInitialResult()
    {
        $this->result = $this->createSuccessResponse(
            __('DACE downloads completed successfully'),
            [
                'success_count' => 0,
                'error_count' => 0,
                'sync_count' => 0,
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
        return __('No PPN orders ready for DACE download in PPN %1', $plpId);
    }

    /**
     * Process individual PPN order
     *
     * @param object $plpOrder
     * @param array $result
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function processPlpOrder($plpOrder, &$result)
    {
        try {
            $this->result = &$result;

            $processingData = $this->getValidatedProcessingData($plpOrder);
            $trackingCode = $processingData['tracking'] ?? null;

            if (!$trackingCode) {
                // phpcs:ignore Magento2.Exceptions.DirectThrow
                throw new LocalizedException(__('PPN Order %1 has no tracking code', $plpOrder->getId()));
            }

            $daceResult = $this->plpDaceDownService->execute([$trackingCode], $this->config->getDaceType());

            if (!$daceResult['success'] || empty($daceResult['data'])) {
                $this->handleDaceSynchronizing($plpOrder, $processingData, $trackingCode, $result);
                return true;
            }

            $this->saveDaceAndUpdateOrder($plpOrder, $processingData, $daceResult['data'], $trackingCode, $result);

            $result['success_count']++;
            return true;

        } catch (LocalizedException $exc) {
            $errorMessage = $exc->getMessage();
            $this->logger->error(__(
                'Error downloading DACE for PPN Order %1: %2',
                $plpOrder->getId(),
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
     * Get and validate processing data from PLP order
     *
     * @param object $plpOrder
     * @return array
     * @throws LocalizedException
     */
    private function getValidatedProcessingData($plpOrder)
    {
        $raw = $plpOrder->getProcessingData();
        if (empty($raw)) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new LocalizedException(__('PPN Order %1 has no processing data', $plpOrder->getId()));
        }

        return $this->json->unserialize($raw);
    }

    /**
     * Save DACE file and update order processing data
     *
     * @param object $plpOrder
     * @param array $processingData
     * @param array $daceData
     * @param string $trackingCode
     * @param array $result
     * @throws LocalizedException
     */
    private function saveDaceAndUpdateOrder($plpOrder, $processingData, $daceData, $trackingCode, &$result)
    {
        $daceContent = $this->extractDaceContent($daceData);
        if (!$daceContent) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new LocalizedException(__('DACE response has no content for tracking %1', $trackingCode));
        }

        $fileName = $this->saveDaceFile($daceContent, $plpOrder, $plpOrder->getPlpId(), $trackingCode);
        if (!$fileName) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new LocalizedException(__('Failed to save DACE file for tracking %1', $trackingCode));
        }

        $processingData['receiptFileName'] = $fileName;

        $daceInfo = $this->extractDaceInfo($trackingCode);
        if ($daceInfo) {
            $processingData['daceData'] = $daceInfo;
        }

        $this->updatePlpOrderStatus($plpOrder, $this->successOrderStatus, $processingData);

        $result['files'][] = [
            'plp_order_id' => $plpOrder->getId(),
            'tracking_code' => $trackingCode,
            'file_name' => $fileName
        ];
    }

    /**
     * Handle DACE not yet available (DC-e still being processed)
     *
     * @param object $plpOrder
     * @param array $processingData
     * @param string $trackingCode
     * @param array $result
     */
    private function handleDaceSynchronizing($plpOrder, $processingData, $trackingCode, &$result)
    {
        $this->logger->info(__(
            'DACE not available for tracking %1 — DC-e still being processed. Will retry.',
            $trackingCode
        ));

        $processingData['daceSynchronizing'] = true;
        $processingData['lastDaceSyncAttempt'] = date('Y-m-d H:i:s');

        $this->updatePlpOrderStatus(
            $plpOrder,
            PlpStatusItem::STATUS_ITEM_PENDING_DACE,
            $processingData
        );

        $result['sync_count']++;
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
        $syncCount = isset($this->result['sync_count']) ? $this->result['sync_count'] : 0;

        if ($successCount > 0 && $errorCount === 0 && $syncCount === 0) {
            $plp->setStatus($this->successPlpStatus);
        } elseif ($syncCount > 0 || $errorCount > 0) {
            // Stay at current status to retry on next cron run
            $plp->setStatus($this->failurePlpStatus);
        }

        $this->plpRepository->save($plp);
    }

    /**
     * Extract DACE metadata from Térmica format
     *
     * @param string $trackingCode
     * @return array|null
     */
    private function extractDaceInfo($trackingCode)
    {
        try {
            $text = $this->fetchDaceText($trackingCode);
            if (!$text) {
                return null;
            }

            $info = $this->parseDaceText($text);
            return !empty($info) ? $info : null;
        } catch (\Exception $exc) {
            $this->logger->error(__(
                'Error extracting DACE info for tracking %1: %2',
                $trackingCode,
                $exc->getMessage()
            ));
            return null;
        }
    }

    /**
     * Fetch DACE text content from API
     *
     * @param string $trackingCode
     * @return string|null
     */
    private function fetchDaceText($trackingCode)
    {
        $termResult = $this->plpDaceDownService->execute([$trackingCode], 'T');

        if (!$termResult['success'] || empty($termResult['data'])) {
            return null;
        }

        return $this->extractDaceContent($termResult['data']);
    }

    /**
     * Parse DACE text to extract structured data
     *
     * @param string $text
     * @return array
     */
    private function parseDaceText($text)
    {
        $info = [];
        $patterns = [
            'chaveDCe' => '/Chave de Acesso DC-e:\s*\r?\n?(\d{40,44})/',
            'protocolo' => '/Protocolo de Autorização:\s*(.+?)[\r\n]/',
            'qrCodeChave' => '/qrcode\?chDCe=([^&]+)/',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $info[$key] = trim($match[1]);
            }
        }

        if (preg_match('/Num:\s*(\d+)\s+Série:\s*(\d+)\s+(.+?)[\r\n]/', $text, $match)) {
            $info['numero'] = $match[1];
            $info['serie'] = $match[2];
            $info['dataEmissao'] = trim($match[3]);
        }

        if (isset($info['qrCodeChave'])) {
            $info['qrCodeUrl'] = 'https://www.fazenda.pr.gov.br/dce/qrcode?chDCe=' . $info['qrCodeChave'];
            unset($info['qrCodeChave']);
        }

        return $info;
    }

    /**
     * Extract DACE content from API response
     *
     * @param array $daceData
     * @return string|null
     */
    private function extractDaceContent($daceData)
    {
        // Response is an array of objects with 'objeto' and 'dados'
        if (is_array($daceData) && isset($daceData[0]['dados'])) {
            return $daceData[0]['dados'];
        }

        if (isset($daceData['dados'])) {
            return $daceData['dados'];
        }

        return null;
    }

    /**
     * Save DACE file to disk
     *
     * @param string $content
     * @param object $plpOrder
     * @param int $plpId
     * @param string $trackingCode
     * @return string|false
     */
    private function saveDaceFile($content, $plpOrder, $plpId, $trackingCode)
    {
        try {
            $daceType = $this->config->getDaceType();

            // T = Térmica (plain text), R = Resumida (base64 PDF), C = Completa (base64 PDF)
            $extension = 'pdf';
            $fileContent = base64_decode($content, true);

            if ($daceType === 'T') {
                $extension = 'txt';
                $fileContent = $content;
            }

            if ($fileContent === false) {
                $this->logger->error(__('Failed to decode base64 DACE content for tracking %1', $trackingCode));
                return false;
            }

            $fileName = $this->generateDaceFileName($plpId, $plpOrder->getId(), $trackingCode, $extension);
            $filePath = $this->getDaceFilePath($fileName);

            $this->driver->filePutContents($filePath, $fileContent);
            return $fileName;

        } catch (\Exception $e) {
            $this->logger->error(__(
                'Error saving DACE file for PPN Order %1: %2',
                $plpOrder->getId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Generate a unique filename for the DACE
     *
     * @param int $plpId
     * @param int $plpOrderId
     * @param string $trackingCode
     * @param string $extension
     * @return string
     */
    private function generateDaceFileName($plpId, $plpOrderId, $trackingCode, $extension = 'pdf')
    {
        $timestamp = date('YmdHis');
        return 'sigepweb_dace_plp_'
            . $plpId
            . '_order_'
            . $plpOrderId
            . '_'
            . $trackingCode
            . '_'
            . $timestamp
            . '.'
            . $extension;
    }

    /**
     * Get file path for DACE file
     *
     * @param string $fileName
     * @return string
     */
    private function getDaceFilePath($fileName)
    {
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $dirPath = 'sigepweb/labels';

        if (!$this->driver->isDirectory($mediaDirectory->getAbsolutePath($dirPath))) {
            $this->fileIo->mkdir($mediaDirectory->getAbsolutePath($dirPath), 0775);
        }

        return $mediaDirectory->getAbsolutePath($dirPath . '/' . $fileName);
    }

    /**
     * Get PLPs awaiting DACE download
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    public function getPlpsAwaitingDace()
    {
        return $this->getPlpsByStatus(PlpStatus::STATUS_PLP_AWAITING_DACE);
    }
}
