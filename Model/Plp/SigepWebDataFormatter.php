<?php
/**
 * O2TI Sigep Web Carrier Data Formatter.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Plp;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CountryFactory;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Api\SigepWebServicesRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class SigepWebDataFormatter
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CountryFactory
     */
    protected $countryFactory;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var SigepWebServicesRepositoryInterface
     */
    protected $servicesRepository;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CountryFactory $countryFactory
     * @param Config $config
     * @param LoggerInterface $logger
     * @param SigepWebServicesRepositoryInterface $servicesRepository
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CountryFactory $countryFactory,
        Config $config,
        LoggerInterface $logger,
        SigepWebServicesRepositoryInterface $servicesRepository
    ) {
        $this->storeManager = $storeManager;
        $this->countryFactory = $countryFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->servicesRepository = $servicesRepository;
    }

    /**
     * Format order data for SigepWeb API
     *
     * @param string $collectedData
     * @param array $senderData
     * @return string
     */
    public function formatOrderData($collectedData, $senderData)
    {
        $weight = $this->formatWeight($collectedData['order_info']['total_weight']);
        $weight = $weight ?: '100';
        
        $package = $this->getPackageDimensions($weight);
        
        $serviceCode = $this->getServiceCode($collectedData['order_info']['shipping_method']);
        
        $formattedData = [
            'sequencial' => $collectedData['order_info']['order_id'],
            'remetente' => $this->formatSenderData($senderData),
            'destinatario' => $this->formatRecipientData($collectedData),
            'codigoServico' => $serviceCode,
            'numeroNotaFiscal' => $this->getInvoiceIncrementId($collectedData),
            'numeroCartaoPostagem' => $this->config->getPostingCard(),
            'itensDeclaracaoConteudo' => $this->formatItemsDeclaration($collectedData['items']),
            'pesoInformado' => $weight,
            'codigoFormatoObjetoInformado' => (string)$package['type'],
            'cienteObjetoNaoProibido' => 1,
            'solicitarColeta' => 'N',
            'observacao' => 'Pedido número ' . $collectedData['order_info']['increment_id'],
            'comprimentoInformado' => (string)$package['length'],
            'diametroInformado' => (string)$package['diameter'],
            'alturaInformada' => (string)$package['height'],
            'larguraInformada' => (string)$package['width'],
        ];
        
        $declaredValue = $collectedData['order_info']['subtotal'];
        $formattedData = $this->processAdditionalServices($formattedData, $serviceCode, $declaredValue);
        
        return $formattedData;
    }
    
    /**
     * Get Invoice Increment ID from order data
     *
     * @param array $collectedData
     * @return string
     */
    protected function getInvoiceIncrementId($collectedData)
    {
        if (isset($collectedData['order_info']['invoice_increment_id'])) {
            return $collectedData['order_info']['invoice_increment_id'];
        }
        
        return $collectedData['order_info']['increment_id'] ?? '';
    }

    /**
     * Process additional services for shipping
     *
     * @param array $formattedData
     * @param string $serviceCode
     * @param float|null $declaredValue
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function processAdditionalServices(
        array $formattedData,
        string $serviceCode,
        ?float $declaredValue = null
    ): array {
        $avisoRecebimento = $this->config->getAvisoDeRecebimento() === '1';
        $maoPropria = $this->config->getMaoPropria() === '1';
        $services = [];
        
        try {
            $additionalServices = $this->servicesRepository->getByCode($serviceCode);
            
            if ($declaredValue && $additionalServices->getHasVd()) {
                $declaredValueFloat = (float)$declaredValue;
                $minValue = $additionalServices->getDeclaredMinValue();
                $maxValue = $additionalServices->getDeclaredMaxValue();
                
                $declaredValueFloat = max($declaredValueFloat, $minValue);
                $declaredValueFloat = min($declaredValueFloat, $maxValue);

                $formattedValue = number_format((float)($declaredValueFloat + 0.01), 2, '.', '');
                unset($formattedData['valorDeclarado']);

                $services['valorDeclarado'] = (string)$formattedValue;
                $codeServAdicional = '019';
                if ($serviceCode === '03298') {
                    $codeServAdicional = '064';
                }
                $services['codigoServicoAdicional'] = $codeServAdicional;
            }

            if (!$additionalServices->getHasVd()) {
                unset($formattedData['valorDeclarado']);
            }

            if ($avisoRecebimento && $additionalServices->getHasAr()) {
                $services[] = '001';
            }

            if ($maoPropria && $additionalServices->getHasMp()) {
                $services[] = '002';
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error(__('Service %s not found: %1', $serviceCode, $e->getMessage()));
        }

        if (!empty($services)) {
            $formattedData['listaServicoAdicional'][] = $services;
        }

        return $formattedData;
    }

    /**
     * Get package dimensions based on weight
     *
     * @param float $weight Weight in grams
     * @return array Package dimensions
     */
    public function getPackageDimensions($weight)
    {
        $weightInKg = $weight / 1000;
        return $this->findPackageByWeight($weightInKg);
    }

    /**
     * Find appropriate package based on weight
     *
     * @param float $weightInKg Weight in kilograms
     * @return array Package dimensions with format, height, width, length and diameter
     */
    public function findPackageByWeight(float $weightInKg): array
    {
        $rules = $this->config->getPackageRules();
        
        $defaultPackage = [
            'type' => 2,
            'height' => 20,
            'width' => 110,
            'length' => 160,
            'diameter' => 0
        ];

        if (empty($rules)) {
            $this->logger->info(__('No package rules found, using default package'));
            return $defaultPackage;
        }

        $weightInGrams = (int)($weightInKg * 1000);

        uasort($rules, function ($ruleA, $ruleB) {
            $weightA = $this->convertRuleWeightToGrams($ruleA['max_weight']);
            $weightB = $this->convertRuleWeightToGrams($ruleB['max_weight']);
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

        $largestPackage = end($rules);
        $this->logger->warning(__(
            'Weight %1 grams exceeds all package rules, using largest package: %2',
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
     * Convert rule weight to grams
     *
     * @param string $ruleWeight Weight with unit (g or kg)
     * @return int Weight in grams
     */
    protected function convertRuleWeightToGrams(string $ruleWeight): int
    {
        if (stripos($ruleWeight, 'g') !== false && stripos($ruleWeight, 'kg') === false) {
            return (int)preg_replace('/[^0-9]/', '', $ruleWeight);
        }
        
        if (stripos($ruleWeight, 'kg') !== false) {
            $kgm = (float)preg_replace('/[^0-9\.]/', '', $ruleWeight);
            return (int)($kgm * 1000);
        }
        
        return (int)$ruleWeight;
    }

    /**
     * Format sender data
     *
     * @param array $senderData
     * @return array
     */
    protected function formatSenderData($senderData)
    {
        return [
            'nome' => $senderData['name'] ?? '',
            'dddCelular' => $this->extractDDD($senderData['telephone'] ?? ''),
            'celular' => $this->extractPhone($senderData['telephone'] ?? ''),
            'email' => $senderData['email'] ?? '',
            'cpfCnpj' => $this->formatDocument($senderData['cpf_cnpj'] ?? $this->config->getCnpj()),
            'endereco' => [
                'cep' => $this->formatPostcode($senderData['postcode'] ?? ''),
                'logradouro' => $senderData['street'][0] ?? '',
                'numero' => $senderData['street'][1] ?? '',
                'complemento' => $senderData['street'][2] ?? '',
                'bairro' => $senderData['street'][3] ?? '',
                'cidade' => $senderData['city'] ?? '',
                'uf' => $senderData['region_code'] ?? ''
            ]
        ];
    }

    /**
     * Format recipient data
     *
     * @param array $collectedData
     * @return array
     */
    protected function formatRecipientData($collectedData)
    {
        $shippingAddress = $collectedData['shipping_address'];
        $customerFullName = $shippingAddress['firstname'] . ' ' . $shippingAddress['lastname'];
        $street = $shippingAddress['street'];
        
        if (!is_array($street)) {
            $street = explode("\n", $street);
        }
        
        return [
            'nome' => $customerFullName,
            'dddCelular' => $this->extractDDD($shippingAddress['telephone'] ?? ''),
            'celular' => $this->extractPhone($shippingAddress['telephone'] ?? ''),
            'email' => $collectedData['order_info']['customer_email'] ?? '',
            'cpfCnpj' => $this->formatDocument($shippingAddress['vat_id'] ?? ''),
            'endereco' => [
                'cep' => $this->formatPostcode($shippingAddress['postcode'] ?? ''),
                'logradouro' => isset($street[0]) ? $street[0] : '',
                'numero' => isset($street[1]) ? $street[1] : '',
                'complemento' => isset($street[2]) ? $street[2] : '',
                'bairro' => isset($street[3]) ? $street[3] : $street[2] ?? '',
                'cidade' => $shippingAddress['city'] ?? '',
                'uf' => $shippingAddress['region_code'] ?? '',
                'regiao' => $shippingAddress['region'] ?? ''
            ]
        ];
    }

    /**
     * Format declaration items
     *
     * @param array $items
     * @return array
     */
    protected function formatItemsDeclaration($items)
    {
        $formattedItems = [];
        
        foreach ($items as $item) {
            $formattedItems[] = [
                'conteudo' => $item['name'],
                'quantidade' => (int)((int)$item['qty']),
                'valor' => (float)($item['price'] + 0.01)
            ];
        }
        
        return $formattedItems;
    }

    /**
     * Format weight in kilograms
     *
     * @param float $weight
     * @return string
     */
    protected function formatWeight($weight)
    {
        if (empty($weight) || (float)$weight <= 0) {
            $defaultWeight = $this->config->getFallbackDefaultWeight() * 1000;
            return number_format($defaultWeight, 0, '.', '');
        }
        
        return number_format($weight, 3, '.', '') * 100;
    }

    /**
     * Get service code from shipping method
     *
     * @param string $shippingMethod
     * @return string
     */
    protected function getServiceCode($shippingMethod)
    {
        $parts = explode('_', $shippingMethod);
        $lastPart = end($parts);
        
        if (is_numeric($lastPart)) {
            return $lastPart;
        }
        
        return '03298';
    }

    /**
     * Extract DDD from phone number
     *
     * @param string $phone
     * @return string
     */
    protected function extractDDD($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) >= 10) {
            return substr($phone, 0, 2);
        }
        
        return '';
    }

    /**
     * Extract phone number without DDD
     *
     * @param string $phone
     * @return string
     */
    protected function extractPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) >= 10) {
            return substr($phone, 2);
        }
        
        return $phone;
    }

    /**
     * Format postcode
     *
     * @param string $postcode
     * @return string
     */
    protected function formatPostcode($postcode)
    {
        return preg_replace('/[^0-9]/', '', $postcode);
    }

    /**
     * Format document (CPF/CNPJ)
     *
     * @param string $document
     * @return string
     */
    protected function formatDocument($document)
    {
        return preg_replace('/[^0-9]/', '', $document);
    }
}
