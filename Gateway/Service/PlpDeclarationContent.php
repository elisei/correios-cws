<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Gateway\Service;

use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Http\Client\ApiClient;
use Laminas\Http\Request;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

class PlpDeclarationContent
{
    /**
     * @var ApiClient
     */
    private $apiClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AuthenticationService
     */
    private $authService;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var DriverFile
     */
    private $driverFile;

    /**
     * @param ApiClient $apiClient
     * @param LoggerInterface $logger
     * @param Json $json
     * @param Config $config
     * @param AuthenticationService $authService
     * @param Filesystem $filesystem
     * @param DriverFile $driverFile
     */
    public function __construct(
        ApiClient $apiClient,
        LoggerInterface $logger,
        Json $json,
        Config $config,
        AuthenticationService $authService,
        Filesystem $filesystem,
        DriverFile $driverFile
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->json = $json;
        $this->config = $config;
        $this->authService = $authService;
        $this->filesystem = $filesystem;
        $this->driverFile = $driverFile;
    }

    /**
     * Execute declaration content request
     *
     * @param array $ids Order IDs
     * @return array
     */
    public function execute(array $ids): array
    {
        $result = [
            'success' => true,
            'message' => __('Declaration content retrieved successfully'),
            'file_path' => null
        ];

        try {
            if (empty($ids)) {
                $result['success'] = false;
                $result['message'] = __('No order IDs provided');
                return $result;
            }

            $idsParam = implode(',', $ids);
            $endpoint = $this->config->getBaseUrl() . 'prepostagem/v1/prepostagens/declaracaoconteudo/' . $idsParam;
            
            $headers = $this->authService->getBearerHeader();
            $headers['Accept'] = 'text/html,application/xhtml+xml';
            
            $content = $this->apiClient->requestContent(
                $endpoint,
                $headers,
                [],
                Request::METHOD_GET
            );
            
            if (empty($content)) {
                $result['success'] = false;
                $result['message'] = __('Empty content received from Correios API');
                return $result;
            }

            $filename = 'declaration_' . implode('_', $ids) . '_' . date('YmdHis') . '.html';
            $filePath = $this->saveContentToFile($content, $filename);
            
            $result['filepath'] = $filePath;
            $result['filename'] = $filename;

        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result['success'] = false;
            $result['message'] = __('Error retrieving declaration content: %1', $e->getMessage());
        }

        return $result;
    }

    /**
     * Save content to file
     *
     * @param string $content
     * @param string $filename
     * @return string File path
     * @throws LocalizedException
     */
    private function saveContentToFile(string $content, string $filename): string
    {
        try {
            $directoryPath = $this->getDeclarationDirectory();
            $filePath = $directoryPath . '/' . $filename;
            
            $this->driverFile->filePutContents($filePath, $content);
            
            return $filePath;
        } catch (FileSystemException $e) {
            $this->logger->critical(
                'Error saving declaration content to file: ' . $e->getMessage(),
                ['exception' => $e]
            );
            throw new LocalizedException(__('Could not save declaration content to file: %1', $e->getMessage()));
        }
    }

    /**
     * Get declaration directory path
     *
     * @return string
     * @throws FileSystemException
     */
    private function getDeclarationDirectory(): string
    {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $dirPath = 'sigepweb/declarations';
        
        $absolutePath = $varDirectory->getAbsolutePath($dirPath);
        
        if (!$this->driverFile->isDirectory($absolutePath)) {
            $this->driverFile->createDirectory($absolutePath, 0755);
        }
        
        return $absolutePath;
    }
}
