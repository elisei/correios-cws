<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Service\QuoteService;
use Psr\Log\LoggerInterface;

class FallbackServiceUpdater
{
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var QuoteService
     */
    protected $quoteService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param QuoteService $quoteService
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $json,
        StoreManagerInterface $storeManager,
        QuoteService $quoteService,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
        $this->storeManager = $storeManager;
        $this->quoteService = $quoteService;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Update fallback service rules with current API rates
     *
     * @param string|null $specificService Only update a specific service code
     * @return array Updated service rules
     */
    public function updateServiceRules(?string $specificService = null): array
    {
        try {
            $currentRules = $this->getCurrentServiceRules();
            if (empty($currentRules)) {
                return [];
            }

            $updatedRules = $currentRules;
            $apiRatesData = $this->fetchBulkApiRates($currentRules, $specificService);

            foreach ($apiRatesData as $field => $rates) {
                if ($rates) {
                    $updatedRules[$field]['price'] = $rates['price'];
                    $updatedRules[$field]['delivery_time'] = $rates['delivery_time'];
                }
            }

            return $updatedRules;
        } catch (\Exception $exc) {
            $this->logger->error('Error updating fallback service rules: ' . $exc->getMessage());
            throw $exc;
        }
    }

    /**
     * Get current service rules from configuration
     *
     * @return array
     */
    private function getCurrentServiceRules(): array
    {
        $rulesJson = $this->scopeConfig->getValue(
            'carriers/sigep_web_carrier/fallback_service_rules',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$rulesJson) {
            return [];
        }

        try {
            return $this->json->unserialize($rulesJson);
        } catch (\Exception $e) {
            $this->logger->error('Error parsing current service rules: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch rates from Correios API in bulk
     *
     * @param array $rules Current rules configuration
     * @param string|null $specificService Only fetch specific service
     * @return array API responses with price and delivery time
     */
    private function fetchBulkApiRates(array $rules, ?string $specificService): array
    {
        $apiRatesData = [];
        $sourcePostcode = $this->getOriginPostcode();
        $package = $this->getDefaultPackage();
        $weightInKg = $this->config->getFallbackDefaultWeight();
        $weightInGrams = (int)($weightInKg * 1000);
        $declaredValue = false;

        try {
            foreach ($rules as $field => $rule) {
                if ($specificService && $rule['service'] !== $specificService) {
                    continue;
                }

                $destinationPostcode = $this->calculateDestinationZipCode($rule['zip_start']);
                $servicesConfig = [$rule['service'] => []];

                $priceResponse = $this->quoteService->price(
                    $sourcePostcode,
                    $destinationPostcode,
                    $servicesConfig,
                    $weightInGrams,
                    $package,
                    $declaredValue
                );

                $deadlineResponse = $this->quoteService->deadline(
                    $sourcePostcode,
                    $destinationPostcode,
                    [$rule['service']]
                );

                if (!empty($priceResponse[0]) && !empty($deadlineResponse[0])) {
                    $apiRatesData[$field] = [
                        'price' => number_format((float)($priceResponse[0]['pcFinal'] ?? $rule['price']), 2, '.', ''),
                        'delivery_time' => $deadlineResponse[0]['prazoEntrega'] ?? $rule['delivery_time']
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error fetching API rates: ' . $e->getMessage());
        }

        return $apiRatesData;
    }

    /**
     * Get origin postcode from configuration
     *
     * @return string
     */
    private function getOriginPostcode(): string
    {
        $postcode = $this->scopeConfig->getValue(
            'shipping/origin/postcode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return preg_replace('/[^0-9]/', '', $postcode);
    }

    /**
     * Calculate test destination postcode
     *
     * @param string $start Start zip code
     * @return string
     */
    private function calculateDestinationZipCode(string $start): string
    {
        $startNum = (int)$start;
        $destinationNum = $startNum + 1;
        return str_pad((string)$destinationNum, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Get default package configuration
     *
     * @return array
     */
    private function getDefaultPackage(): array
    {
        $packages = $this->config->getPackageRules();
        
        foreach ($packages as $key => $package) {
            if ($key === 0) {
                return [
                    'type' => (int)$package['type'],
                    'height' => (int)$package['height'],
                    'width' => (int)$package['width'],
                    'length' => (int)$package['length'],
                    'diameter' => (int)$package['diameter']
                ];
            }
        }

        // Default fallback package dimensions
        return [
            'type' => 2,
            'height' => 2,
            'width' => 11,
            'length' => 16,
            'diameter' => 0
        ];
    }
}
