<?php
/**
 * O2TI Sigep Web Carrier - Store Information.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Plp;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\Region;

class StoreInformation
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Region
     */
    protected $region;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Region $region
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Region $region
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->region = $region;
    }

    /**
     * Parse street address into components
     *
     * @param string $fullAddress
     * @return array
     */
    protected function parseStreetAddress($fullAddress)
    {
        $fullAddress = trim($fullAddress);

        $streetName = $fullAddress;
        $number = '';
        $complement = '';

        if (preg_match('/^(.*?),\s*(\d+)(?:\s*,\s*(.+))?$/u', $fullAddress, $matches)) {
            $streetName = trim($matches[1]);
            $number = $matches[2];
            $complement = isset($matches[3]) ? trim($matches[3]) : '';
        }

        return [
            $streetName,
            $number ?: 'S/N',
            $complement ?: ''
        ];
    }

    /**
     * Get sender data from store information
     *
     * @return array
     */
    public function getSenderData()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $regionId = $this->getStoreConfig('shipping/origin/region_id', $storeId);
        $region = $this->region->load($regionId);
        $parsedStreet = $this->parseStreetAddress($this->getStoreConfig('shipping/origin/street_line1', $storeId));
        $data = [
            'name' => $this->getStoreConfig('general/store_information/name', $storeId),
            'telephone' => $this->getStoreConfig('general/store_information/phone', $storeId),
            'email' => $this->getStoreConfig('trans_email/ident_general/email', $storeId),
            'cpf_cnpj' => $this->getStoreConfig('general/store_information/merchant_vat_number', $storeId),
            'street' => [
                $parsedStreet[0],
                $parsedStreet[1],
                $parsedStreet[2],
                $this->getStoreConfig('shipping/origin/street_line2', $storeId) ?: 'BAIRRO',
            ],
            'city' => $this->getStoreConfig('shipping/origin/city', $storeId),
            'region_code' => $region->getCode(),
            'postcode' => $this->getStoreConfig('shipping/origin/postcode', $storeId)
        ];
        
        return $data;
    }

    /**
     * Get store config value
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    protected function getStoreConfig($path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
