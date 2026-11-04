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

class PlpDaceDownloadService
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
     * Download DACE for given tracking codes
     *
     * @param array $trackingCodes
     * @param string $tipoDace T(Térmica), R(Resumida) or C(Completa)
     * @return array
     */
    public function execute(array $trackingCodes, string $tipoDace = 'R'): array
    {
        $result = [
            'success' => true,
            'message' => __('DACE download request successful'),
            'data' => []
        ];

        try {
            $url = $this->config->getBaseUrl() . 'prepostagem/v1/prepostagens/dce/dace/impressao';

            $request = [
                'tipoDace' => $tipoDace,
                'codigosObjetos' => $trackingCodes
            ];

            $response = $this->apiClient->request(
                $url,
                $this->authService->getBearerHeader(),
                $request
            );

            if ($this->config->hasDebug()) {
                $this->logger->debug('DACE Download API Response', [
                    'tracking_codes' => $trackingCodes,
                    'response_type' => gettype($response)
                ]);
            }

            if (isset($response['msgs'])) {
                $result['success'] = false;
                $result['message'] = __(implode('; ', $response['msgs']));
                return $result;
            }

            if (isset($response['mensagem'])) {
                $result['success'] = false;
                $result['message'] = __($response['mensagem']);
                return $result;
            }

            $result['data'] = $response;

        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result['success'] = false;
            $result['message'] = __('Error downloading DACE: %1', $e->getMessage());
        }

        return $result;
    }
}
