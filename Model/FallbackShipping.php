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

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Psr\Log\LoggerInterface;

class FallbackShipping
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     * @param LoggerInterface $logger
     * @param MethodFactory $rateMethodFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $json,
        LoggerInterface $logger,
        MethodFactory $rateMethodFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
        $this->logger = $logger;
        $this->rateMethodFactory = $rateMethodFactory;
    }

    /**
     * Check if fallback mode is enabled
     *
     * @param string $path Base config path
     * @return bool
     */
    public function isFallbackEnabled(string $path): bool
    {
        return (bool)$this->scopeConfig->getValue($path . 'fallback/active');
    }

    /**
     * Get fallback service rules
     *
     * @param string $path Base config path
     * @return array
     */
    public function getFallbackServiceRules(string $path): array
    {
        $rulesJson = $this->scopeConfig->getValue($path . 'fallback/service_rules');
        if (!$rulesJson) {
            return [];
        }

        try {
            $rules = $this->json->unserialize($rulesJson);
            if (!is_array($rules)) {
                return [];
            }
            
            $formattedRules = [];
            foreach ($rules as $rule) {
                if (!isset(
                    $rule['service'],
                    $rule['zip_start'],
                    $rule['zip_end'],
                    $rule['delivery_time'],
                    $rule['price'],
                    $rule['max_weight']
                )) {
                    continue;
                }
                
                $formattedRules[] = [
                    'service' => $rule['service'],
                    'zip_range' => [
                        'start' => $rule['zip_start'],
                        'end' => $rule['zip_end']
                    ],
                    'delivery_time' => (int)$rule['delivery_time'],
                    'price' => (float)$rule['price'],
                    'max_weight' => (float)$rule['max_weight']
                ];
            }
            
            return $formattedRules;
        } catch (Exception $exc) {
            $this->logger->error('Error parsing fallback service rules: ' . $exc->getMessage());
            return [];
        }
    }

    /**
     * Check if zip code is within range
     *
     * @param string $zipCode
     * @param array $range
     * @return bool
     */
    public function isZipCodeInRange(string $zipCode, array $range): bool
    {
        $zipCode = (int)$zipCode;
        return $zipCode >= (int)$range['start'] && $zipCode <= (int)$range['end'];
    }

    /**
     * Get applicable fallback rules
     *
     * @param string $destinationZip
     * @param float $weight
     * @param string $path Base config path
     * @return array
     */
    public function getApplicableFallbackRules(string $destinationZip, float $weight, string $path): array
    {
        $rules = $this->getFallbackServiceRules($path);
        $applicableRules = [];
        
        foreach ($rules as $rule) {
            if ($this->isZipCodeInRange($destinationZip, $rule['zip_range']) &&
                 $weight <= $rule['max_weight']) {
                $applicableRules[] = $rule;
            }
        }
        
        return $applicableRules;
    }

    /**
     * Create fallback shipping methods
     *
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param string $destinationZip
     * @param float $weight
     * @param string $carrierCode
     * @param string $path Base config path
     * @param array $serviceNames Service name mapping
     * @return void
     */
    public function createFallbackMethods(
        $result,
        string $destinationZip,
        float $weight,
        string $carrierCode,
        string $path,
        array $serviceNames
    ): void {
        $applicableRules = $this->getApplicableFallbackRules($destinationZip, $weight, $path);
        
        foreach ($applicableRules as $rule) {
            $price = $rule['price'];
            $deadline = $rule['delivery_time'];
            
            $handlingFee = $this->scopeConfig->getValue($path . 'handling_fee');
            if ($handlingFee) {
                $price += (float)$handlingFee;
            }
            
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($carrierCode);
            $method->setCarrierTitle($this->scopeConfig->getValue($path . 'title'));
            $method->setMethod($rule['service']);
            
            $methodTitle = sprintf(
                '%s - %d %s [Prazo de entrega estimado]',
                $serviceNames[$rule['service']] ?? $rule['service'],
                $deadline,
                ($deadline > 1 ? __('dias úteis') : __('dia útil'))
            );
            
            $method->setMethodTitle($methodTitle);
            $method->setPrice($price);
            $method->setCost($price);
            
            $result->append($method);
        }
    }
}
