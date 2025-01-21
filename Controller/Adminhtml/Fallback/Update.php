<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

/**
 * Fallback table.
 */
namespace O2TI\SigepWebCarrier\Controller\Adminhtml\Fallback;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use O2TI\SigepWebCarrier\Model\FallbackServiceUpdater;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Update extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var FallbackServiceUpdater
     */
    protected $fallbackUpdater;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var Json
     */
    protected $json;

    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param FallbackServiceUpdater $fallbackUpdater
     * @param WriterInterface $configWriter
     * @param Json $json
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FallbackServiceUpdater $fallbackUpdater,
        WriterInterface $configWriter,
        Json $json
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->fallbackUpdater = $fallbackUpdater;
        $this->configWriter = $configWriter;
        $this->json = $json;
    }

    /**
     * Execute update action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $updatedRules = $this->fallbackUpdater->updateServiceRules();
            
            $this->configWriter->save(
                'carriers/sigep_web_carrier/fallback/service_rules',
                $this->json->serialize($updatedRules)
            );

            return $result->setData([
                'success' => true,
                'message' => __('Service rules have been updated successfully.')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error updating service rules: %1', $e->getMessage())
            ]);
        }
    }

    /**
     * Check admin permissions for this controller
     *
     * @return boolean
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('O2TI_SigepWebCarrier::fallback_update');
    }
}
