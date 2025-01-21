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

class CorreiosService
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
     * Get services list
     *
     * @return array
     */
    public function getServices(): array
    {
        $response = $this->apiClient->request(
            $this->config->getBaseUrl() .
            sprintf(
                'meucontrato/v1/empresas/%s/contratos/%s/cartoes/%s/servicos',
                $this->config->getCnpj(),
                $this->config->getContract(),
                $this->config->getPostingCard()
            ),
            $this->authService->getBearerHeader(),
            [],
            'GET'
        );

        return $response['itens'] ?? [];
    }

    /**
     * Get services additional
     *
     * @param string $code
     * @return array
     */
    public function getServicesAdditional($code): array
    {
        $response = $this->apiClient->request(
            $this->config->getBaseUrl() .
            sprintf(
                'preco/v1/servicos-adicionais/%s',
                $code
            ),
            $this->authService->getBearerHeader(),
            [],
            'GET'
        );

        return $response;
    }
}
