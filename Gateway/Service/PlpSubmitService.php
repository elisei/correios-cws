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

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Http\Client\ApiClient;

class PlpSubmitService
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
    private $driver;

    /**
     * @param ApiClient $apiClient
     * @param LoggerInterface $logger
     * @param Json $json
     * @param Config $config
     * @param AuthenticationService $authService
     * @param Filesystem $filesystem
     * @param DriverFile $driver
     */
    public function __construct(
        ApiClient $apiClient,
        LoggerInterface $logger,
        Json $json,
        Config $config,
        AuthenticationService $authService,
        Filesystem $filesystem,
        DriverFile $driver
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->json = $json;
        $this->config = $config;
        $this->authService = $authService;
        $this->filesystem = $filesystem;
        $this->driver = $driver;
    }

    /**
     * Submit PPN file to Correios API
     *
     * @param string $fileName
     * @return array
     */
    public function execute(string $fileName): array
    {
        $result = [
            'success' => true,
            'message' => __('PPN file submitted successfully'),
            'data' => []
        ];

        try {
            $filePath = $this->getFilePath($fileName);
            
            if (!$this->driver->isExists($filePath)) {
                $result['success'] = false;
                $result['message'] = __('File %1 not found', $filePath);
                return $result;
            }

            $postingCard = $this->config->getPostingCard();
            $correiosId = $this->config->getCorreiosId();
            
            $endpoint = $this->config->getBaseUrl() . 'prepostagem/v1/prepostagens/lista/objetosregistrados';
            $endpoint .= '?numeroCartaoPostagem=' . urlencode($postingCard);
            $endpoint .= '&idCorreios=' . urlencode($correiosId);
            $auth = $this->authService->getBearerHeader();
            $response = $this->apiClient->uploadFile(
                $endpoint,
                $auth,
                'arquivo',
                $fileName,
                $filePath
            );
            
            if (isset($response['idLote'])) {
                $result['data'] = $response;
            }

            if (!isset($response['idLote'])) {
                $result['success'] = false;
                $result['message'] = __('Failed to submit PPN: Invalid API response');
            }

        } catch (\Exception $exc) {
            $this->logger->critical($exc);
            $result['success'] = false;
            $result['message'] = __('Error submitting PPN file: %1', $exc->getMessage());
        }

        return $result;
    }

    /**
     * Get file path for the PPN file
     *
     * @param string $fileName
     * @return string
     */
    protected function getFilePath($fileName)
    {
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $dirPath = 'sigepweb/plp';
        
        return $mediaDirectory->getAbsolutePath($dirPath . '/' . $fileName);
    }
}
