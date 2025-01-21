<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use O2TI\SigepWebCarrier\Gateway\Service\AuthenticationService;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

/**
 * Controller responsible for testing SigepWeb API credentials
 */
class TestCredentials extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var AuthenticationService
     */
    protected $authService;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Pool
     */
    protected $cacheFrontendPool;

    /**
     * Constructor
     *
     * @param Context $context The context object
     * @param JsonFactory $resultJsonFactory Factory for JSON results
     * @param AuthenticationService $authService Service for authentication
     * @param WriterInterface $configWriter Interface for writing configurations
     * @param TypeListInterface $cacheTypeList Interface for cache types
     * @param Pool $cacheFrontendPool Pool of cache frontends
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AuthenticationService $authService,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->authService = $authService;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Execute the credential test
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $response = $this->authService->getAuthToken();
            
            // Save values to configuration
            $this->configWriter->save('carriers/sigep_web_carrier/environment', $response['environment']);
            $this->configWriter->save('carriers/sigep_web_carrier/contract', $response['contract']);
            // $this->configWriter->save('carriers/sigep_web_carrier/posting_card', $response['numero']);
            $this->configWriter->save('carriers/sigep_web_carrier/direction', $response['direction']);
            $this->configWriter->save('carriers/sigep_web_carrier/cnpj', $response['cnpj']);
            $this->configWriter->save('carriers/sigep_web_carrier/correios_id', $response['correios_id']);

            // Clean config cache
            $this->cacheTypeList->cleanType('config');
            foreach ($this->cacheFrontendPool as $cacheFrontend) {
                $cacheFrontend->getBackend()->clean();
            }

            return $result->setData([
                'success' => true,
                'message' => __('Credentials validated and contract information saved successfully.')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if user is allowed to access this functionality
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('O2TI_SigepWebCarrier::config');
    }
}
