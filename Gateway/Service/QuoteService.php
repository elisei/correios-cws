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
use O2TI\SigepWebCarrier\Api\SigepWebServicesRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class QuoteService
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
     * @var SigepWebServicesRepositoryInterface
     */
    private $servicesRepository;

    /**
     * @param ApiClient $apiClient
     * @param LoggerInterface $logger
     * @param Json $json
     * @param Config $config
     * @param AuthenticationService $authService
     * @param SigepWebServicesRepositoryInterface $servicesRepository
     */
    public function __construct(
        ApiClient $apiClient,
        LoggerInterface $logger,
        Json $json,
        Config $config,
        AuthenticationService $authService,
        SigepWebServicesRepositoryInterface $servicesRepository
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->json = $json;
        $this->config = $config;
        $this->authService = $authService;
        $this->servicesRepository = $servicesRepository;
    }

    /**
     * Calculate shipping price
     *
     * @param string $sourcePostcode
     * @param string $destPostcode
     * @param array $services
     * @param int $weight
     * @param array $package
     * @param float|null $declaredValue
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function price(
        string $sourcePostcode,
        string $destPostcode,
        array $services,
        int $weight,
        array $package,
        ?float $declaredValue = null
    ): array {
        $request = [
            'idLote' => '123',
            'parametrosProduto' => [],
        ];
        $avisorecebimento = $this->config->getMaoPropria();
        $maopropria = $this->config->getAvisoDeRecebimento();

        foreach ($services as $service => $serviceQuote) {
            $item = [
                'nuContrato' => $this->config->getContract(),
                'nuDR' => $this->config->getDirection(),
                'nuRequisicao' => (string)$service,
                'coProduto' => $service,
                'cepOrigem' => $sourcePostcode,
                'cepDestino' => $destPostcode,
                'psObjeto' => $weight,
                'tpObjeto' => (int)$package['type'],
                'comprimento' => $package['length'],
                'largura' => $package['width'],
                'altura' => $package['height'],
                'diametro' => $package['diameter'],
                'servicosAdicionais' => [],
            ];

            $item = $this->processAdditionalServices($item, $service, $avisorecebimento, $maopropria, $declaredValue);

            $request['parametrosProduto'][] = $item;
        }

        return $this->apiClient->request(
            $this->config->getBaseUrl() . 'preco/v1/nacional',
            $this->authService->getBearerHeader(),
            $request
        );
    }

    /**
     * Process additional services for shipping
     *
     * @param array $item
     * @param string $service
     * @param bool $avisorecebimento
     * @param bool $maopropria
     * @param float|null $declaredValue
     * @return array
     */
    private function processAdditionalServices(
        array $item,
        string $service,
        bool $avisorecebimento,
        bool $maopropria,
        ?float $declaredValue = null
    ): array {
        try {
            $additionalServices = $this->servicesRepository->getByCode($service);
            
            if ($declaredValue && $additionalServices->getHasVd()) {
                $declaredValue = max($declaredValue, $additionalServices->getDeclaredMinValue());
                $declaredValue = min($declaredValue, $additionalServices->getDeclaredMaxValue());
                $codeServAdicional = '019';
                if ($service === '03298') {
                    $codeServAdicional = '064';
                }
                $item['vlDeclarado'] = $declaredValue;
                $item['servicosAdicionais'][] = ['coServAdicional' => $codeServAdicional];
            }

            if ($avisorecebimento && $additionalServices->getHasAr()) {
                $item['servicosAdicionais'][] = ['coServAdicional' => '001'];
            }

            if ($maopropria && $additionalServices->getHasMp()) {
                $item['servicosAdicionais'][] = ['coServAdicional' => '002'];
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error(sprintf('Service %s not found: %s', $service, $e->getMessage()));
        }

        return $item;
    }

    /**
     * Get shipping deadline
     *
     * @param string $sourcePostcode
     * @param string $destPostcode
     * @param array $serviceCodes
     * @return array
     */
    public function deadline(
        string $sourcePostcode,
        string $destPostcode,
        array $serviceCodes
    ): array {
        $request = [
            'idLote' => '123',
            'parametrosPrazo' => [],
        ];

        foreach ($serviceCodes as $service) {
            $request['parametrosPrazo'][] = [
                'nuRequisicao' => (string)count($request['parametrosPrazo']),
                'coProduto' => $service,
                'cepOrigem' => $sourcePostcode,
                'cepDestino' => $destPostcode,
            ];
        }

        return $this->apiClient->request(
            $this->config->getBaseUrl() . 'prazo/v1/nacional',
            $this->authService->getBearerHeader(),
            $request,
            'POST'
        );
    }
}
