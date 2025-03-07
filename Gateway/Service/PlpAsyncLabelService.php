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

class PlpAsyncLabelService
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
     * Execute async label request for a single tracking code
     *
     * @param string $trackingCode
     * @return array
     */
    public function execute(string $trackingCode): array
    {
        $result = [
            'success' => true,
            'message' => __('Async label request submitted successfully'),
            'data' => []
        ];

        try {
            $request = [
                'codigosObjeto' => [$trackingCode],
                'idCorreios' => $this->config->getCorreiosId(),
                'numeroCartaoPostagem' => $this->config->getPostingCard(),
                'tipoRotulo' => $this->config->getLabelType(),
                'formatoRotulo' => $this->config->getLabelFormat(),
                'layoutImpressao' => $this->config->getPrintLayout()
            ];

            if ($this->config->hasDebug()) {
                $this->logger->debug('Async Label Request', ['request' => $request]);
            }

            $response = $this->apiClient->request(
                $this->config->getBaseUrl() . 'prepostagem/v1/prepostagens/rotulo/assincrono/pdf',
                $this->authService->getBearerHeader(),
                $request
            );
            
            if ($this->config->hasDebug()) {
                $this->logger->debug('Async Label API Response', ['response' => $response]);
            }
            
            $result['data'] = $response;

        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result['success'] = false;
            $result['message'] = __('Error requesting async label: %1', $e->getMessage());
        }

        return $result;
    }
}
