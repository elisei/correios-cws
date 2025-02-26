<?php
/**
 * O2TI Sigep Web Carrier Data Formatter.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CountryFactory;

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
     * @param StoreManagerInterface $storeManager
     * @param CountryFactory $countryFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CountryFactory $countryFactory
    ) {
        $this->storeManager = $storeManager;
        $this->countryFactory = $countryFactory;
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
        $formattedData = [
            'sequencial' => $collectedData['order_info']['increment_id'],
            'remetente' => $this->formatSenderData($senderData),
            'destinatario' => $this->formatRecipientData($collectedData),
            'codigoServico' => $this->getServiceCode($collectedData['order_info']['shipping_method']),
            'numeroNotaFiscal' => '',
            'numeroCartaoPostagem' => '0071481672', // herdar do config
            'itensDeclaracaoConteudo' => $this->formatItemsDeclaration($collectedData['items']),
            'pesoInformado' => $weight,
            'codigoFormatoObjetoInformado' => '2',  // herdar do config de acordo com formato da caixa
            'cienteObjetoNaoProibido' => 1,
            'solicitarColeta' => 'N',
            'observacao' => 'Pedido número ' . $collectedData['order_info']['increment_id'],
            'comprimentoInformado' => '10', // herdar do config de acordo com formato da caixa
            'alturaInformada' => '2',  // herdar do config de acordo com formato da caixa
            'larguraInformada' => '10',  // herdar do config de acordo com formato da caixa
        ];
        
        return $formattedData;
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
            'cpfCnpj' => $this->formatDocument($senderData['cpf_cnpj'] ?? ''),
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
            'ddiCelular' => '55',
            'celular' => $this->extractPhone($shippingAddress['telephone'] ?? ''),
            'email' => $collectedData['order_info']['customer_email'] ?? '',
            'cpfCnpj' => $this->formatDocument($shippingAddress['vat_id']),
            'endereco' => [
                'cep' => $this->formatPostcode($shippingAddress['postcode'] ?? ''),
                'logradouro' => isset($street[0]) ? $street[0] : '',
                'numero' => isset($street[1]) ? $street[1] : '',
                'complemento' => isset($street[2]) ? $street[2] : '',
                'bairro' => isset($street[3]) ? $street[3] : $street[2],
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
                'valor' => (float) number_format($item['price'], 2, '.', '')
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
        $methodMap = [
            'sigep_web_carrier_04162' => '04162',
            'sigep_web_carrier_04669' => '04669',
        ];
        
        if (isset($methodMap[$shippingMethod])) {
            return $methodMap[$shippingMethod];
        }
        
        $parts = explode('_', $shippingMethod);
        $lastPart = end($parts);
        
        if (is_numeric($lastPart) && strlen($lastPart) == 5) {
            return $lastPart;
        }
        
        return '04669';
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
