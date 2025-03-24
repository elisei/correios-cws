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
     * Get sender data from store information
     *
     * @return array
     */
    public function getSenderData()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $regionId = $this->getStoreConfig('carriers/sigep_web_carrier/sender_region_id', $storeId);
        $region = $this->region->load($regionId);
        $data = [
            'name' => $this->getStoreConfig('carriers/sigep_web_carrier/sender_name', $storeId),
            'telephone' => $this->getStoreConfig('carriers/sigep_web_carrier/sender_cellphone', $storeId),
            'email' => $this->getStoreConfig('carriers/sigep_web_carrier/sender_email', $storeId),
            'cpf_cnpj' => $this->getStoreConfig('carriers/sigep_web_carrier/sender_cpf_cnpj', $storeId),
            'street' => [
                $this->getStoreConfig('carriers/sigep_web_carrier/sender_street_1', $storeId),
                $this->getStoreConfig('carriers/sigep_web_carrier/sender_street_2', $storeId),
                $this->getStoreConfig('carriers/sigep_web_carrier/sender_street_3', $storeId),
                $this->getStoreConfig('carriers/sigep_web_carrier/sender_street_4', $storeId),
            ],
            'city' => $this->getStoreConfig('carriers/sigep_web_carrier/sender_city', $storeId),
            'region_code' => $region->getCode(),
            'postcode' => $this->getStoreConfig('carriers/sigep_web_carrier/sender_postcode', $storeId)
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
