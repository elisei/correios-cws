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

use DateTime;
use Exception;
use O2TI\SigepWebCarrier\Gateway\Service\QuoteService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Shipping\Model\Tracking\Result;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Framework\Xml\Security;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Helper\Data;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Encryption\Encryptor;
use O2TI\SigepWebCarrier\Model\TrackingProcessor;
use O2TI\SigepWebCarrier\Model\FallbackShipping;
use O2TI\SigepWebCarrier\Model\Cache\ResponseCache;

/**
 * Correios Carrier Model
 *
 * This class implements the carrier functionality for Correios shipping methods.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Carrier extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    public const CODE = 'sigep_web_carrier';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * List of allowed Correios services with their codes and names
     *
     * @var array<string, string>
     */
    private $serviceNames = [
        '03298' => 'Pac',
        '03220' => 'Sedex',
        '03158' => 'Sedex 10',
        '03204' => 'Sedex Hoje',
        '04227' => 'Mini Envios',
        '03140' => 'Sedex 12',
        '03212' => 'Sedex Grandes Formatos',
        '03247' => 'Sedex',
        '03301' => 'Pac',
        '05991' => 'Sedex - Logística Reversa',
        '06637' => 'Pac - Logística Reversa',
    ];

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StatusFactory
     */
    private $trackStatusFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Encryptor
     */
    private $encryptor;

    /**
     * @var RateRequest
     */
    private $_request;

    /**
     * @var TrackingProcessor
     */
    protected $trackingProcessor;

    /**
     * @var FallbackShipping
     */
    protected $fallbackShipping;

    /**
     * @var QuoteService
     */
    protected $quoteService;

    /**
     * @var ResponseCache
     */
    private $responseCache;

    /**
     * Constructor for the Carrier class
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param ElementFactory $xmlElFactory
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param TrackingResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param StatusFactory $trackStatusFactory
     * @param RegionFactory $regionFactory
     * @param CountryFactory $countryFactory
     * @param CurrencyFactory $currencyFactory
     * @param Data $directoryData
     * @param StockRegistryInterface $stockRegistry
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     * @param Encryptor $encryptor
     * @param TrackingProcessor $trackingProcessor
     * @param FallbackShipping $fallbackShipping
     * @param QuoteService $quoteService
     * @param ResponseCache $responseCache
     * @param array $data Additional data for the carrier
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        TrackingResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        StatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        Data $directoryData,
        StockRegistryInterface $stockRegistry,
        StoreManagerInterface $storeManager,
        Json $json,
        Encryptor $encryptor,
        TrackingProcessor $trackingProcessor,
        FallbackShipping $fallbackShipping,
        QuoteService $quoteService,
        ResponseCache $responseCache,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->storeManager = $storeManager;
        $this->trackStatusFactory = $trackStatusFactory;
        $this->json = $json;
        $this->encryptor = $encryptor;
        $this->trackingProcessor = $trackingProcessor;
        $this->fallbackShipping = $fallbackShipping;
        $this->quoteService = $quoteService;
        $this->responseCache = $responseCache;

        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateResultFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    /**
     * Check if fallback mode is enabled
     *
     * @return bool
     */
    private function isFallbackEnabled(): bool
    {
        return (bool)$this->getConfigData('fallback_active');
    }

    /**
     * Create fallback shipping methods based on rules
     *
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param string $destinationZip
     * @param float $weight
     * @return void
     */
    private function createFallbackMethods($result, string $destinationZip, float $weight): void
    {
        $this->fallbackShipping->createFallbackMethods(
            $result,
            $destinationZip,
            $weight,
            $this->_code,
            'carriers/' . self::CODE . '/',
            $this->serviceNames
        );
    }

    /**
     * Modified fetchShippingData method to handle API failures
     *
     * @param string $sourcePostcode
     * @param string $destPostcode
     * @param array $servicesWithConfig
     * @param array $package
     * @param int $weightInGrams
     * @param float $declaredValue
     *
     * @return array
     * @throws Exception
     */
    private function fetchShippingData(
        string $sourcePostcode,
        string $destPostcode,
        array $servicesWithConfig,
        array $package,
        int $weightInGrams,
        float $declaredValue
    ): array {
        $requestParams = [
            'source' => $sourcePostcode,
            'destination' => $destPostcode,
            'services' => $servicesWithConfig,
            'weight' => $weightInGrams,
            'package' => $package,
            'value' => $declaredValue
        ];

        $cacheKey = $this->encryptor->hash($this->json->serialize($requestParams));
    
        $this->_logger->info($this->json->serialize(['request_params' => $requestParams]));

        $cachedData = $this->responseCache->get($cacheKey);
        if ($cachedData && !($cachedData['is_fallback'] ?? false)) {
            $this->_logger->debug('Using cached shipping rates', ['cache_key' => $cacheKey]);
            $this->_logger->info($this->json->serialize(['cache_hit' => $cachedData]));
            return $cachedData;
        }

        try {
            $priceResponse = $this->quoteService->price(
                $sourcePostcode,
                $destPostcode,
                $servicesWithConfig,
                $weightInGrams,
                $package,
                $declaredValue
            );
        
            $deadlineResponse = $this->quoteService->deadline(
                $sourcePostcode,
                $destPostcode,
                array_keys($servicesWithConfig)
            );

            $dataToCache = [
                'prices' => $priceResponse,
                'deadlines' => $deadlineResponse,
                'is_fallback' => false
            ];

            // Only cache non-fallback responses
            $this->responseCache->save($cacheKey, $dataToCache);
        
            return $dataToCache;
        } catch (Exception $exc) {
            $this->_logger->error('Correios API error: ' . $exc->getMessage());
            
            if ($this->isFallbackEnabled()) {
                return [
                    'prices' => [],
                    'deadlines' => [],
                    'is_fallback' => true
                ];
            }
            
            throw $exc;
        }
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool|Error
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExceptionHandling)
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $this->_request = $request;
        $result = $this->rateResultFactory->create();
        $this->_result = $result;

        // Extract essential data early for fallback use
        $destPostcode = preg_replace('/[^0-9]/', '', $request->getDestPostcode());
        $weight = $this->calculateTotalWeight($request);

        try {
            $sourcePostcode = $this->getOriginPostcode();

            if (!$this->validatePostcodes($sourcePostcode, $destPostcode)) {
                // phpcs:ignore Magento2.Exceptions.DirectThrow
                throw new Exception('Invalid postcode format');
            }

            $shippingData = $this->getShippingData($sourcePostcode, $destPostcode, $weight);

            if ($shippingData['is_fallback']) {
                $this->createFallbackMethods($result, $destPostcode, $weight);
            }

            if (!$shippingData['is_fallback']) {
                $this->processResponses($result, $shippingData['prices'], $shippingData['deadlines']);
            }

            if (empty($result->getAllRates())) {
                // phpcs:ignore Magento2.Exceptions.DirectThrow
                throw new Exception('No valid shipping methods available');
            }

            $this->getAddDiscount($request);

            if ($request->getFreeShipping()) {
                $this->_updateFreeMethodQuote($request);
            }

            return $result;
        // phpcs:ignore
        } catch (Exception $exc) {
            $this->_logger->error('Shipping calculation error: ' . $exc->getMessage());

            if ($this->isFallbackEnabled()) {
                $this->_logger->info('Using fallback shipping methods');
                $this->createFallbackMethods($result, $destPostcode, $weight);

                $this->getAddDiscount($request);

                if ($request->getFreeShipping()) {
                    $this->_updateFreeMethodQuote($request);
                }

                return $result;
            }
            
            if ($this->getConfigData('showmethod')) {
                $error = $this->_rateErrorFactory->create();
                $error->setCarrier($this->_code)
                    ->setCarrierTitle($this->getConfigData('title'))
                    ->setErrorMessage($this->getConfigData('specificerrmsg'));
                return $error;
            }
            
            return false;
        }
    }

    /**
     * Get shipping data from API or cache
     *
     * @param string $sourcePostcode
     * @param string $destPostcode
     * @param float $weight
     * @return array
     * @throws Exception
     */
    private function getShippingData(
        string $sourcePostcode,
        string $destPostcode,
        float $weight
    ): array {
        $servicesWithConfig = $this->getServicesWithConfig();
        if (empty($servicesWithConfig) && !$this->isFallbackEnabled()) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new Exception('No valid shipping services available');
        }

        $package = $this->getDefaultPackage();
        $declaredValue = $this->calculateDeclaredValue($this->_request);

        return $this->fetchShippingData(
            $sourcePostcode,
            $destPostcode,
            $servicesWithConfig,
            $package,
            (int)round($weight * 1000),
            $declaredValue
        );
    }

    /**
     * Get configured services with their settings
     *
     * @return array
     */
    private function getServicesWithConfig(): array
    {
        $selectedServices = $this->getSelectedServices();
        $servicesWithConfig = [];
        
        foreach ($selectedServices as $serviceCode) {
            if (isset($this->serviceNames[$serviceCode])) {
                $servicesWithConfig[$serviceCode] = [];
            }
        }
        
        return $servicesWithConfig;
    }

    /**
     * Get selected shipping services from configuration
     *
     * @return array
     */
    private function getSelectedServices(): array
    {
        $selectedServices = $this->getConfigData('servicos');
        return $selectedServices ? explode(',', $selectedServices) : [];
    }

    /**
     * Convert Brazilian decimal format to float
     *
     * @param string $price Price in Brazilian format (e.g., "17,38")
     * @return float Price as float (e.g., 17.38)
     */
    private function convertBrazilianPriceToFloat(string $price): float
    {
        $price = str_replace('.', '', $price);
        $price = str_replace(',', '.', $price);
        return (float)$price;
    }

    /**
     * Process batch responses and add shipping methods
     *
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param array $priceResponse
     * @param array $deadlineResponse
     */
    private function processResponses($result, array $priceResponse, array $deadlineResponse): void
    {
        $deadlineMap = [];
        foreach ($deadlineResponse as $deadline) {
            $naoEntrega = 0;

            if (!isset($deadline['entregaDomiciliar'])) {
                $naoEntrega = 0;
            } elseif ($deadline['entregaDomiciliar'] !== 'S') {
                $naoEntrega = 1;
            }

            if (!isset($deadline['txErro'])) {
                $deadlineMap[$deadline['coProduto']] = [
                    'prazoEntrega' => $deadline['prazoEntrega'],
                    'msgPrazo' => $deadline['msgPrazo'] ?? null,
                    'entregaDomiciliar' => $naoEntrega
                ];
            }
        }

        foreach ($priceResponse as $price) {
            if (isset($price['txErro'])) {
                $this->_logger->warning(sprintf(
                    'Price calculation failed for service %s: %s',
                    $price['coProduto'],
                    $price['txErro']
                ));
                continue;
            }

            $serviceCode = $price['coProduto'];
            if (!isset($deadlineMap[$serviceCode]) || !isset($this->serviceNames[$serviceCode])) {
                continue;
            }

            $finalPrice = $this->convertBrazilianPriceToFloat($price['pcFinal']);

            $this->addShippingMethod(
                $result,
                $serviceCode,
                $finalPrice,
                (int)$deadlineMap[$serviceCode]['prazoEntrega'],
                $deadlineMap[$serviceCode]['entregaDomiciliar']
            );
        }
    }

    /**
     * Get tracking
     *
     * @param string|string[] $trackings
     * @return Result|null
     */
    public function getTracking($trackings)
    {
        if (!is_array($trackings)) {
            $trackings = [$trackings];
        }

        $result = $this->_trackFactory->create();

        foreach ($trackings as $tracking) {
            $trackingInfo = $this->trackingProcessor->getTrackingInfo($tracking);
            if ($trackingInfo) {
                $track = $this->_trackStatusFactory->create();
                $track->setCarrier($this->_code)
                    ->setCarrierTitle($this->getConfigData('title'))
                    ->setTracking($tracking)
                    ->addData($trackingInfo);
                $result->append($track);
            }

            if (!$trackingInfo) {
                $error = $this->_trackErrorFactory->create();
                $error->setCarrier($this->_code)
                    ->setCarrierTitle($this->getConfigData('title'))
                    ->setTracking($tracking)
                    ->setErrorMessage(__('Unable to retrieve tracking information'));
                $result->append($error);
            }
        }

        return $result;
    }

    /**
     * Validate source and destination postcodes format
     *
     * @param string $source Source postcode
     * @param string $destination Destination postcode
     * @return bool True if both postcodes are valid
     */
    private function validatePostcodes(string $source, string $destination): bool
    {
        return strlen($source) === 8 && strlen($destination) === 8;
    }

    /**
     * Calculate declared value for shipping based on package value
     *
     * @param RateRequest $request Shipping rate request object
     * @return float Declared value for shipping
     */
    private function calculateDeclaredValue(RateRequest $request): float
    {
        if (!$this->getConfigData('auto_declared')) {
            return 0;
        }
        return (float)$request->getBaseSubtotalInclTax();
    }

    /**
     * Add shipping method to result with calculated price and delivery time
     *
     * @param \Magento\Shipping\Model\Rate\Result $result Rate result object
     * @param string $serviceCode Shipping service code
     * @param float $price Shipping price
     * @param int $deadline Delivery deadline in days
     * @param string|null $deadlineMessage Additional deadline message
     * @return void
     */
    private function addShippingMethod(
        $result,
        string $serviceCode,
        float $price,
        int $deadline,
        ?string $deadlineMessage = null
    ): void {
        $handlingFee = $this->getConfigData('handling_fee');
        if ($handlingFee) {
            $price += (float)$handlingFee;
        }

        $addDeadline = $this->getConfigData('add_deadline');
        if ($addDeadline) {
            $deadline += (int)$addDeadline;
        }

        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($serviceCode);

        $methodTitle = sprintf(
            '%s - %d %s após o envio.',
            $this->getServiceName($serviceCode),
            $deadline,
            ($deadline > 1 ? __('dias úteis') : __('dia útil'))
        );
        
        if ($deadlineMessage) {
            $methodTitle .= ' - ' . __('Entrega domiciliar indisponível. Retirar na Agência.');
        }

        $method->setMethodTitle($methodTitle);
        $method->setPrice($price);
        $method->setCost($price);
        $result->append($method);
    }

    /**
     * Get formatted origin postcode from request or configuration
     *
     * @return string Formatted origin postcode
     */
    private function getOriginPostcode(): string
    {
        $postcode = $this->_request->getOrigPostcode();
        if (!$postcode) {
            $postcode = $this->_scopeConfig->getValue(
                'shipping/origin/postcode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }
        return preg_replace('/[^0-9]/', '', $postcode);
    }

    /**
     * Calculate total weight of non-virtual items in request
     *
     * @param RateRequest $request Shipping rate request object
     * @return float Total weight in kilograms (minimum 0.1)
     */
    private function calculateTotalWeight(RateRequest $request): float
    {
        $weight = 0;
        foreach ($request->getAllItems() as $item) {
            if (!$item->getProduct()->isVirtual()) {
                $weight += (float)$item->getWeight() * (float)$item->getQty();
            }
        }
        return max($weight, 0.1);
    }

    /**
     * Get package rules from configuration
     *
     * @return array
     */
    private function getPackageRules(): array
    {
        $rulesJson = $this->getConfigData('package_rules');
        if (!$rulesJson) {
            return [];
        }

        try {
            $rules = $this->json->unserialize($rulesJson);
            return is_array($rules) ? $rules : [];
        } catch (Exception $e) {
            $this->_logger->error('Error parsing package rules: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Convert rule weight to grams
     *
     * @param string $weight Weight in format "0.500"
     * @return int Weight in grams
     */
    private function convertRuleWeightToGrams(string $weight): int
    {
        return (int)(((float)$weight) * 1000);
    }

    /**
     * Find appropriate package based on weight
     *
     * @param float $weightInKg Weight in kilograms
     * @return array Package dimensions with format, height, width, length and diameter
     */
    private function findPackageByWeight(float $weightInKg): array
    {
        $rules = $this->getPackageRules();
        
        // Default package in case no rules match
        $defaultPackage = [
            'type' => 2,
            'height' => 20,
            'width' => 110,
            'length' => 160,
            'diameter' => 0
        ];

        if (empty($rules)) {
            $this->_logger->info('No package rules found, using default package');
            return $defaultPackage;
        }

        // Convert weight to grams for comparison
        $weightInGrams = (int)($weightInKg * 1000);

        // Sort rules by max_weight ascending
        uasort($rules, function ($ruleWeigthA, $ruleWeigthB) {
            $weightA = $this->convertRuleWeightToGrams($ruleWeigthA['max_weight']);
            $weightB = $this->convertRuleWeightToGrams($ruleWeigthB['max_weight']);
            return $weightA <=> $weightB;
        });

        foreach ($rules as $rule) {
            $maxWeightInGrams = $this->convertRuleWeightToGrams($rule['max_weight']);

            if ($weightInGrams <= $maxWeightInGrams) {
                return [
                    'type' => (int)$rule['format'],
                    'height' => (int)$rule['height'],
                    'width' => (int)$rule['width'],
                    'length' => (int)$rule['length'],
                    'diameter' => (int)$rule['diameter']
                ];
            }
        }

        // If weight exceeds all rules, use the largest package
        $largestPackage = end($rules);
        $this->_logger->warning(sprintf(
            'Weight %d grams exceeds all package rules, using largest package: %s',
            $weightInGrams,
            $largestPackage['description']
        ));

        return [
            'type' => (int)$largestPackage['format'],
            'height' => (int)$largestPackage['height'],
            'width' => (int)$largestPackage['width'],
            'length' => (int)$largestPackage['length'],
            'diameter' => (int)$largestPackage['diameter']
        ];
    }

    /**
     * Get package dimensions based on order weight
     *
     * @return array Package dimensions
     */
    private function getDefaultPackage(): array
    {
        $weight = $this->calculateTotalWeight($this->_request);
        return $this->findPackageByWeight($weight);
    }

    /**
     * Get service name from code or return code if not found
     *
     * @param string $code Service code
     * @return string Service name or code if not found
     */
    private function getServiceName(string $code): string
    {
        return $this->serviceNames[$code] ?? $code;
    }

    /**
     * Apply free shipping to either the selected method or the lowest-cost method
     *
     * @param RateRequest $request
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _updateFreeMethodQuote($request): void
    {
        if (!$this->_result) {
            return;
        }

        $freeMethod = $this->getConfigData('free_method');

        if (!$freeMethod) {
            return;
        }

        if ($freeMethod === 'min_value_available') {
            $this->applyFreeShippingToLowestRate();
            return;
        }

        $this->applyFreeShippingToSpecificMethod($freeMethod);
    }

    /**
     * Apply discounts based on zipcode rules
     *
     * @param RateRequest $request
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getAddDiscount($request): void
    {
        if (!$this->_result) {
            return;
        }

        $destinationZip = preg_replace('/[^0-9]/', '', $request->getDestPostcode());
        $discountRules = $this->getConfigData('discount_rules');

        if (!$discountRules) {
            return;
        }

        try {
            $rules = $this->json->unserialize($discountRules);
            if (!is_array($rules)) {
                return;
            }

            foreach ($this->_result->getAllRates() as $method) {
                foreach ($rules as $rule) {
                    if (isset($rule['zip_start'], $rule['zip_end'], $rule['discount']) &&
                        $destinationZip >= $rule['zip_start'] &&
                        $destinationZip <= $rule['zip_end']
                    ) {
                        $originalPrice = $method->getPrice();
                        $discountAmount = ($originalPrice * (float)$rule['discount']) / 100;
                        $finalPrice = $originalPrice - $discountAmount;

                        $method->setPrice($finalPrice);
                        $method->setCost($finalPrice);

                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_logger->error('Error applying discount rules: ' . $e->getMessage());
        }
    }

    /**
     * Apply free shipping to the lowest rate available
     *
     * @return void
     */
    private function applyFreeShippingToLowestRate(): void
    {
        $lowestPrice = null;
        $lowestPriceMethod = null;

        foreach ($this->_result->getAllRates() as $method) {
            $currentPrice = $method->getPrice();
            if ($lowestPrice === null || $currentPrice < $lowestPrice) {
                $lowestPrice = $currentPrice;
                $lowestPriceMethod = $method;
            }
        }

        if ($lowestPriceMethod) {
            $this->applyFreeShipping($lowestPriceMethod);
        }
    }

    /**
     * Apply free shipping to a specific shipping method
     *
     * @param string $methodCode
     * @return void
     */
    private function applyFreeShippingToSpecificMethod(string $methodCode): void
    {
        foreach ($this->_result->getAllRates() as $method) {
            if ($method->getMethod() === $methodCode) {
                $this->applyFreeShipping($method);
                break;
            }
        }
    }

    /**
     * Apply free shipping to the given method
     *
     * @param Method $method
     * @return void
     */
    private function applyFreeShipping($method): void
    {
        $method->setPrice(0);
        $method->setCost(0);
        $currentTitle = $method->getMethodTitle();
        $method->setMethodTitle($currentTitle . ' [Grátis]');
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return [
            '03298' => $this->getConfigData('name') . ' - PAC',
            '03220' => $this->getConfigData('name') . ' - SEDEX',
            '03158' => $this->getConfigData('name') . ' - SEDEX 10',
            '03204' => $this->getConfigData('name') . ' - SEDEX Hoje',
            '04227' => $this->getConfigData('name') . ' - Mini Envios'
        ];
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return bool
     */
    public function isTrackingAvailable(): bool
    {
        return true;
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        $result = new DataObject();
        try {
            $result->setErrors(__('Shipping label generation not implemented yet'));
        } catch (Exception $exc) {
            $this->_logger->critical($exc);
            $result->setErrors($exc->getMessage());
        }
        return $result;
    }
}
