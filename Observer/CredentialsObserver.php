<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

declare(strict_types=1);

namespace O2TI\SigepWebCarrier\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;

/**
 * Observer responsável por gerenciar o status das credenciais do SigepWeb
 */
class CredentialsObserver implements ObserverInterface
{
    private const CONFIG_PATH_USERNAME = 'carriers/sigep_web_carrier/username';
    private const CONFIG_PATH_PASSWORD = 'carriers/sigep_web_carrier/password';
    private const CONFIG_PATH_HAS_CREDENTIALS = 'carriers/sigep_web_carrier/has_credentials';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList
    ) {
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        $username = $this->getConfigValue(self::CONFIG_PATH_USERNAME);
        $password = $this->getConfigValue(self::CONFIG_PATH_PASSWORD);

        $hasCredentials = $this->validateCredentials($username, $password);

        $this->saveCredentialsStatus($hasCredentials);
        $this->cleanConfigCache();
    }

    /**
     * Obtém valor da configuração
     *
     * @param string $path
     * @return string|null
     */
    private function getConfigValue(string $path): ?string
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Valida se as credenciais estão preenchidas
     *
     * @param string|null $username
     * @param string|null $password
     * @return bool
     */
    private function validateCredentials(?string $username, ?string $password): bool
    {
        return !empty($username) && !empty($password);
    }

    /**
     * Salva o status das credenciais
     *
     * @param bool $hasCredentials
     * @return void
     */
    private function saveCredentialsStatus(bool $hasCredentials): void
    {
        $this->configWriter->save(
            self::CONFIG_PATH_HAS_CREDENTIALS,
            (int) $hasCredentials,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );
    }

    /**
     * Limpa o cache de configuração
     *
     * @return void
     */
    private function cleanConfigCache(): void
    {
        $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);
    }
}
