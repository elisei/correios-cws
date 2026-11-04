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
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use ZipArchive;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Service\PlpDeclarationContent;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

class DeclarationProcessor
{
    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @var PlpOrderCollectionFactory
     */
    protected $plpOrderCollec;

    /**
     * @var PlpDeclarationContent
     */
    protected $declarationService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var DriverFile
     */
    protected $driverFile;

    /**
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpOrderCollectionFactory $plpOrderCollec
     * @param PlpDeclarationContent $declarationService
     * @param LoggerInterface $logger
     * @param Json $json
     * @param Config $config
     * @param Filesystem $filesystem
     * @param DriverFile $driverFile
     */
    public function __construct(
        PlpRepositoryInterface $plpRepository,
        PlpOrderCollectionFactory $plpOrderCollec,
        PlpDeclarationContent $declarationService,
        LoggerInterface $logger,
        Json $json,
        Config $config,
        Filesystem $filesystem,
        DriverFile $driverFile
    ) {
        $this->plpRepository = $plpRepository;
        $this->plpOrderCollec = $plpOrderCollec;
        $this->declarationService = $declarationService;
        $this->logger = $logger;
        $this->json = $json;
        $this->config = $config;
        $this->filesystem = $filesystem;
        $this->driverFile = $driverFile;
    }

    /**
     * Process declaration content for a PPN
     *
     * @param int $plpId PPN ID
     * @return array
     * @throws LocalizedException
     */
    public function processDeclaration(int $plpId): array
    {
        try {
            $plp = $this->plpRepository->getById($plpId);
            if (!$plp) {
                throw new LocalizedException(__('PPN with ID %1 not found', $plpId));
            }

            $collection = $this->plpOrderCollec->create();
            $collection->addFieldToFilter('plp_id', $plpId);

            if ($collection->getSize() === 0) {
                throw new LocalizedException(__('No orders found in PPN %1', $plpId));
            }

            // When DC-e is enabled, serve the DACE files instead of the old declaration
            if ($this->config->isEmiteDceEnabled()) {
                return $this->processDaceDeclaration($collection, $plpId);
            }

            return $this->processLegacyDeclaration($collection, $plpId);

        } catch (RuntimeException $exc) {
            $this->logger->critical($exc);
            throw new LocalizedException(__('Error processing declaration: %1', $exc->getMessage()));
        }
    }

    /**
     * Process DACE-based declaration (DC-e enabled)
     *
     * @param \O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\Collection $collection
     * @param int $plpId
     * @return array
     * @throws LocalizedException
     */
    private function processDaceDeclaration($collection, int $plpId): array
    {
        $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $daceFiles = [];

        foreach ($collection as $plpOrder) {
            $processingData = $plpOrder->getProcessingData();
            if (empty($processingData)) {
                continue;
            }

            $data = $this->json->unserialize($processingData);
            if (!empty($data['receiptFileName'])) {
                $relativePath = 'sigepweb/labels/' . $data['receiptFileName'];
                if ($mediaDir->isExist($relativePath)) {
                    $daceFiles[] = [
                        'filepath' => $mediaDir->getAbsolutePath($relativePath),
                        'filename' => $data['receiptFileName'],
                        'tracking' => $data['tracking'] ?? 'N/A'
                    ];
                }
            }
        }

        if (empty($daceFiles)) {
            throw new LocalizedException(__('No DACE files found for PPN %1', $plpId));
        }

        // Single file — return directly
        if (count($daceFiles) === 1) {
            return [
                'success' => true,
                'filename' => $daceFiles[0]['filename'],
                'filepath' => $daceFiles[0]['filepath']
            ];
        }

        // Multiple files — create ZIP
        return $this->createDaceZip($daceFiles, $plpId);
    }

    /**
     * Create a ZIP file with all DACE files
     *
     * @param array $daceFiles
     * @param int $plpId
     * @return array
     * @throws LocalizedException
     */
    private function createDaceZip(array $daceFiles, int $plpId): array
    {
        $mediaWrite = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $zipFileName = 'sigepweb_dace_plp_' . $plpId . '_' . date('YmdHis') . '.zip';
        $zipPath = $mediaWrite->getAbsolutePath('sigepweb/labels/' . $zipFileName);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new LocalizedException(__('Could not create ZIP file for DACE'));
        }

        foreach ($daceFiles as $dace) {
            $content = $this->driverFile->fileGetContents($dace['filepath']);
            $zip->addFromString($dace['filename'], $content);
        }

        $zip->close();

        return [
            'success' => true,
            'filename' => $zipFileName,
            'filepath' => $zipPath
        ];
    }

    /**
     * Process legacy declaration (DC-e disabled)
     *
     * @param \O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\Collection $collection
     * @param int $plpId
     * @return array
     * @throws LocalizedException
     */
    private function processLegacyDeclaration($collection, int $plpId): array
    {
        $orderIds = [];
        foreach ($collection as $plpOrder) {
            $processingData = $plpOrder->getProcessingData();
            if (!empty($processingData)) {
                $data = $this->json->unserialize($processingData);
                if (isset($data['id'])) {
                    $orderIds[] = $data['id'];
                }
            }
        }

        if (empty($orderIds)) {
            throw new LocalizedException(__('No valid order IDs found in PPN %1', $plpId));
        }

        $result = $this->declarationService->execute($orderIds);

        if (!$result['success']) {
            throw new LocalizedException($result['message']);
        }

        return [
            'success' => $result['success'],
            'filename' => $result['filename'],
            'filepath' => $result['filepath']
        ];
    }
}
