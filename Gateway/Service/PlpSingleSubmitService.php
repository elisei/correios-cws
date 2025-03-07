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

class PlpSingleSubmitService
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
     * Execute PLP single submit with Correios API
     *
     * @param array $request
     * @return array
     */
    public function execute(array $request): array
    {
        $result = [
            'success' => true,
            'message' => __('PLP single submit successful'),
            'data' => []
        ];

        try {
            $response = $this->apiClient->request(
                $this->config->getBaseUrl() . 'prepostagem/v1/prepostagens',
                $this->authService->getBearerHeader(),
                $request
            );
            
            if ($this->config->hasDebug()) {
                $this->logger->debug('PLP Sync API Response', ['response' => $response]);
            }
            
            $result['data'] = $response;

        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result['success'] = false;
            $result['message'] = __('Error syncing PLP: %1', $e->getMessage());
        }

        return $result;
    }
}
