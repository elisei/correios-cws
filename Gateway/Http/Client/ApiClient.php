<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Gateway\Http\Client;

use Laminas\Http\ClientFactory;
use Laminas\Http\Request;
use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\Header\Authorization;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;

/**
 * Class ApiClient
 * Handles API communication with Correios services.
 */
class ApiClient
{
    private const PROTECTED_KEYS = ['username', 'password', 'token', 'Authorization', 'posting_card'];
    private const CONTENT_TYPE_JSON = 'application/json';

    /**
     * @var ClientFactory
     */
    private $httpClientFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var array
     */
    private $auth;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string|null
     */
    private $rawResponse = null;

    /**
     * @param ClientFactory $httpClientFactory
     * @param LoggerInterface $logger
     * @param Json $json
     * @param Config $config
     * @param array $auth
     */
    public function __construct(
        ClientFactory $httpClientFactory,
        LoggerInterface $logger,
        Json $json,
        Config $config,
        array $auth = []
    ) {
        $this->httpClientFactory = $httpClientFactory;
        $this->logger = $logger;
        $this->json = $json;
        $this->config = $config;
        $this->auth = $auth;
    }

    /**
     * Set Auth Data
     *
     * @param array $auth
     * @return void
     */
    public function setAuth(array $auth): void
    {
        $this->auth = $auth;
    }

    /**
     * Get raw response
     *
     * @return string|null
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * Send API Request
     *
     * @param string $uri
     * @param array $headers
     * @param array $request
     * @param string $method
     * @return array
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function request(
        string $uri,
        array $headers,
        array $request = [],
        string $method = 'POST'
    ): array {
        try {
            $client = $this->httpClientFactory->create();
            $client->setUri($uri);

            $headers = $this->prepareHeaders($headers, $method);
            $client->setHeaders($headers);
            $client->setMethod($method);

            if ($method === Request::METHOD_POST) {
                $client->setRawBody($this->json->serialize($request));
            }

            $response = $client->send();
            $responseBody = $response->getBody();
            $this->rawResponse = $responseBody;

            $data = $this->json->unserialize($responseBody);

            $this->logApiInteraction($uri, $headers, $request, $data);

            return $data;
        } catch (InvalidArgumentException $exc) {
            $this->logApiError($uri, $headers, $request, $exc->getMessage());
            throw new LocalizedException(__('Invalid JSON was returned by the Correios'));
        }
    }

    /**
     * Prepare request headers
     *
     * @param array $headers
     * @param string $method
     * @return array
     */
    private function prepareHeaders(array $headers, string $method): array
    {
        if ($method === Request::METHOD_POST) {
            $headers = array_merge(
                $headers,
                [
                    'Content-Type' => self::CONTENT_TYPE_JSON,
                    'Accept' => self::CONTENT_TYPE_JSON
                ]
            );
        }
        return $headers;
    }

    /**
     * Log API interaction if debug is enabled
     *
     * @param string $uri
     * @param array $headers
     * @param array $request
     * @param array $response
     * @return void
     */
    private function logApiInteraction(string $uri, array $headers, array $request, array $response): void
    {
        if ($this->config->hasDebug()) {
            $this->logger->debug(
                'Correios API Request',
                [
                    'uri' => $uri,
                    'headers' => $this->filterDebugData($headers),
                    'request' => $this->filterDebugData($request),
                    'response' => $this->filterDebugData($response)
                ]
            );
        }
    }

    /**
     * Log API error if debug is enabled
     *
     * @param string $uri
     * @param array $headers
     * @param array $request
     * @param string $error
     * @return void
     */
    private function logApiError(string $uri, array $headers, array $request, string $error): void
    {
        if ($this->config->hasDebug()) {
            $this->logger->error(
                'Correios API Error',
                [
                    'url' => $uri,
                    'headers' => $this->filterDebugData($headers),
                    'request' => $this->filterDebugData($request),
                    'error' => $error
                ]
            );
        }
    }

    /**
     * Filter sensitive data for logging
     *
     * @param array $debugData
     * @return array
     */
    private function filterDebugData(array $debugData): array
    {
        foreach ($debugData as $key => $value) {
            if (in_array($key, self::PROTECTED_KEYS, true)) {
                $debugData[$key] = '*** protected ***';
            } elseif (is_array($value)) {
                $debugData[$key] = $this->filterDebugData($value);
            }
        }

        return $debugData;
    }
}
