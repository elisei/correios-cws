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

use O2TI\SigepWebCarrier\Gateway\Http\Client\ApiClient;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Service\AuthenticationService;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class TrackingService
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
     * Track object
     *
     * @param string $trackingNumber
     * @return array
     */
    public function trackObject(string $trackingNumber): array
    {
        return $this->apiClient->request(
            $this->config->getBaseUrl() . 'srorastro/v1/objetos/' . $trackingNumber,
            $this->authService->getBearerHeader(),
            [],
            'GET'
        );
    }
}
