<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const CONFIG_PATH_PREFIX = 'carriers/sigep_web_carrier/';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param Json $json
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        Json $json
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->json = $json;
    }

    /**
     * Get Config Value
     *
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    private function getConfigValue(string $field, ?int $storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_PREFIX . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Environment
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEnvironment(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('environment', $storeId);
    }

    /**
     * Get Contract
     *
     * @param int|null $storeId
     * @return string
     */
    public function getContract(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('contract', $storeId);
    }

    /**
     * Get Direction
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getDirection(?int $storeId = null): ?string
    {
        return $this->getConfigValue('direction', $storeId);
    }

    /**
     * Get CNPJ
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCnpj(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('cnpj', $storeId);
    }

    /**
     * Get Correios ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCorreiosId(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('correios_id', $storeId);
    }

    /**
     * Get Mão Própria
     *
     * @param int|null $storeId
     * @return string
     */
    public function getMaoPropria(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('mao_propria', $storeId);
    }

    /**
     * Get Aviso de Recebimento
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAvisoDeRecebimento(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('aviso_de_recebimento', $storeId);
    }
 
    /**
     * Has Debug
     *
     * @param int|null $storeId
     * @return bool
     */
    public function hasDebug(?int $storeId = null): bool
    {
        return $this->getConfigValue('debug', $storeId);
    }

    /**
     * Get Posting Card
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPostingCard(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('posting_card', $storeId);
    }

    /**
     * Get Label Type
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLabelType(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('label_type', $storeId);
    }

    /**
     * Get Label Format
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLabelFormat(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('label_format', $storeId);
    }

    /**
     * Get Print Layout
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPrintLayout(?int $storeId = null): string
    {
        return (string)$this->getConfigValue('print_layout', $storeId);
    }

    /**
     * Get Base URL
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAllowedStatus(?int $storeId = null): array
    {
        $status = $this->getConfigValue('allowed_status', $storeId);

        return isset($status) ? explode(',', $status) : [];
    }

    /**
     * Get Default Weight
     *
     * @param int|null $storeId
     * @return float|null
     */
    public function getFallbackDefaultWeight(?int $storeId = null): ?float
    {
        return (float)$this->getConfigValue('fallback_default_weight', $storeId);
    }

    /**
     * Get Base URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getBaseUrl(?int $storeId = null): string
    {
        $environment = $this->getEnvironment($storeId);
        if ($environment === 'PRODUCAO') {
            return 'https://api.correios.com.br/';
        }
        return 'https://apihom.correios.com.br/';
    }

    /**
     * Get package rules from configuration
     *
     * @param int|null $storeId
     * @return array
     */
    public function getPackageRules(?int $storeId = null): array
    {
        $rulesJson = $this->getConfigValue('package_rules', $storeId);
        if (!$rulesJson) {
            return [];
        }

        $rules = $this->json->unserialize($rulesJson);
        return is_array($rules) ? $rules : [];
    }

    /**
     * Get discount rules from configuration
     *
     * @param int|null $storeId
     * @return array
     */
    public function getDiscountRules(?int $storeId = null): array
    {
        $rulesJson = $this->getConfigValue('discount_rules', $storeId);
        if (!$rulesJson) {
            return [];
        }

        $rules = $this->json->unserialize($rulesJson);
        return is_array($rules) ? $rules : [];
    }

    /**
     * Get Auth Data
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAuthData(?int $storeId = null): array
    {
        return [
            'username' => $this->getConfigValue('username', $storeId),
            'password' => $this->encryptor->decrypt($this->getConfigValue('password', $storeId)),
            'posting_card' => $this->getConfigValue('posting_card', $storeId)
        ];
    }
}
