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

class PlpLabelDownloadService
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
     * @param ApiClient $apiClient
     * @param LoggerInterface $logger
     * @param Json $json
     * @param Config $config
     * @param AuthenticationService $authService
     */
    public function __construct(
        ApiClient $apiClient,
        LoggerInterface $logger,
        Json $json,
        Config $config,
        AuthenticationService $authService
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->json = $json;
        $this->config = $config;
        $this->authService = $authService;
    }

    /**
     * Execute label download request for a receipt ID
     *
     * @param string $receiptId
     * @return array
     */
    public function execute(string $receiptId): array
    {
        $result = [
            'success' => true,
            'message' => __('Label download request successful'),
            'data' => []
        ];

        try {
            $url = $this->config->getBaseUrl() . 'prepostagem/v1/prepostagens/rotulo/download/assincrono/' . $receiptId;
            
            $response = $this->apiClient->request(
                $url,
                $this->authService->getBearerHeader(),
                [],
                'GET'
            );
            
            if (isset($response['mensagem']) &&
                strpos($response['mensagem'], 'PPN-291') !== false &&
                strpos($response['mensagem'], 'Recibo em sincronização') !== false) {
                $result['success'] = false;
                $result['message'] = __('Receipt is still synchronizing. Please try again later.');
                $result['data'] = [
                    'status' => 'synchronizing',
                    'original_response' => $response
                ];
                return $result;
            }

            if (isset($response['msgs'])) {
                $result = [
                    'success' => false,
                    'message' => __(
                        implode('; ' , $response['msgs'])
                    )
                ];
            }
            
            $result['data'] = $response;

        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result['success'] = false;
            $result['message'] = __('Error downloading label: %1', $e->getMessage());
        }

        return $result;
    }
}
