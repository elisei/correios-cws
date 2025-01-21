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

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Http\Client\ApiClient;
use O2TI\SigepWebCarrier\Model\Cache\AuthenticationCache;

/**
 * Class Authentication Service - Service for Correios API Authentication.
 */
class AuthenticationService
{
    /**
     * @var string
     */
    private const TOKEN_ENDPOINT = 'token/v1/autentica/cartaopostagem';

    /**
     * @var array
     */
    private $currentToken = [];

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ApiClient
     */
    private $apiClient;

    /**
     * @var AuthenticationCache
     */
    private $cache;

    /**
     * @param Config $config
     * @param ApiClient $apiClient
     * @param AuthenticationCache $cache
     */
    public function __construct(
        Config $config,
        ApiClient $apiClient,
        AuthenticationCache $cache
    ) {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->cache = $cache;
    }

    /**
     * Get Authentication Token.
     *
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getAuthToken(?int $storeId = null): array
    {
        $cacheKey = $this->getCacheKey($storeId);
        
        // Try to get from memory first
        if (!empty($this->currentToken[$cacheKey]) && !$this->isTokenExpired($this->currentToken[$cacheKey])) {
            return $this->currentToken[$cacheKey];
        }

        // Try to get from cache
        $cachedToken = $this->cache->get($cacheKey);
        if ($cachedToken && !$this->isTokenExpired($cachedToken)) {
            $this->currentToken[$cacheKey] = $cachedToken;
            return $cachedToken;
        }

        // Generate new token
        $token = $this->generateNewToken($storeId);
        
        // Save in memory and cache
        $this->currentToken[$cacheKey] = $token;
        $this->cache->save($cacheKey, $token);

        return $token;
    }

    /**
     * Generate New Token.
     *
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    private function generateNewToken(?int $storeId): array
    {
        $authData = $this->config->getAuthData($storeId);
        $baseUrl = $this->config->getBaseUrl($storeId);

        try {
            $response = $this->apiClient->request(
                $baseUrl . self::TOKEN_ENDPOINT,
                $this->getBasicAuthHeader($authData),
                ['numero' => $authData['posting_card']]
            );

            $this->validateAuthResponse($response);
            return $this->formatAuthResponse($response);
            
        } catch (\Exception $e) {
            $this->clearAuthCache($storeId);
            throw new LocalizedException(
                __('Failed to authenticate with Correios API: %1', $e->getMessage())
            );
        }
    }

    /**
     * Get Basic Auth Header.
     *
     * @param array $authData
     * @return array
     */
    private function getBasicAuthHeader(array $authData): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$authData['username']}:{$authData['password']}"),
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Validate Auth Response.
     *
     * @param array $response
     * @throws LocalizedException
     */
    private function validateAuthResponse(array $response): void
    {
        $requiredFields = [
            'token' => __('Token'),
            'expiraEm' => __('Expiration Time')
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($response[$field])) {
                throw new LocalizedException(
                    __('Invalid authentication response: missing %1', $label)
                );
            }
        }

        if (!isset($response['cartaoPostagem']['contrato']) || !isset($response['cartaoPostagem']['dr'])) {
            throw new LocalizedException(
                __('Invalid authentication response: missing contract data')
            );
        }
    }

    /**
     * Format Auth Response.
     *
     * @param array $response
     * @return array
     */
    private function formatAuthResponse(array $response): array
    {
        $expiresAt = new DateTime("{$response['expiraEm']}{$response['zoneOffset']}");
        $expiresAt->setTimezone(new DateTimeZone('+0000'));

        return [
            'token' => $response['token'],
            'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
            'environment' => $response['ambiente'],
            'contract' => $response['cartaoPostagem']['contrato'],
            'posting_card' => $response['cartaoPostagem']['numero'],
            'direction' => $response['cartaoPostagem']['dr'],
            'cnpj' => $response['cnpj'],
            'correios_id' => $response['id']
        ];
    }

    /**
     * Check if Token is Expired.
     *
     * @param array $auth
     * @return bool
     */
    private function isTokenExpired(array $auth): bool
    {
        if (!isset($auth['expires_at'])) {
            return true;
        }

        try {
            $expiresAt = new DateTime($auth['expires_at']);
            $now = new DateTime('now', new DateTimeZone('+0000'));
            
            // Add buffer time of 4 hours before actual expiration
            $now->modify('+4 hours');
            
            return $expiresAt <= $now;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get Bearer Header for API Requests.
     *
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getBearerHeader(?int $storeId = null): array
    {
        $auth = $this->getAuthToken($storeId);
        return ['Authorization' => 'Bearer ' . $auth['token']];
    }

    /**
     * Clear Authentication Cache.
     *
     * @param int|null $storeId
     * @return void
     */
    public function clearAuthCache(?int $storeId = null): void
    {
        if ($storeId !== null) {
            $cacheKey = $this->getCacheKey($storeId);
            unset($this->currentToken[$cacheKey]);
            $this->cache->remove($cacheKey);
            return;
        }
        
        $this->currentToken = [];
        $this->cache->clean();
    }

    /**
     * Generate Cache Key for Store.
     *
     * @param int|null $storeId
     * @return string
     */
    private function getCacheKey(?int $storeId): string
    {
        return 'auth_token_store_' . ($storeId ?? 'default');
    }
}
