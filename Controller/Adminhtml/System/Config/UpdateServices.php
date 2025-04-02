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
use O2TI\SigepWebCarrier\Gateway\Service\CorreiosService;
use O2TI\SigepWebCarrier\Api\SigepWebServicesRepositoryInterface;
use O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterfaceFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Config\Storage\WriterInterface;

class UpdateServices extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var CorreiosService
     */
    private $correiosService;

    /**
     * @var SigepWebServicesRepositoryInterface
     */
    private $servicesRepository;

    /**
     * @var SigepWebServicesInterfaceFactory
     */
    private $servicesFactory;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var Pool
     */
    private $cacheFrontendPool;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CorreiosService $correiosService
     * @param SigepWebServicesRepositoryInterface $servicesRepository
     * @param SigepWebServicesInterfaceFactory $servicesFactory
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     * @param WriterInterface $configWriter
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CorreiosService $correiosService,
        SigepWebServicesRepositoryInterface $servicesRepository,
        SigepWebServicesInterfaceFactory $servicesFactory,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        WriterInterface $configWriter
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->correiosService = $correiosService;
        $this->servicesRepository = $servicesRepository;
        $this->servicesFactory = $servicesFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->configWriter = $configWriter;
        parent::__construct($context);
    }

    /**
     * Update services from Correios API
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $services = $this->correiosService->getServices();
            
            if (empty($services)) {
                return $this->createErrorResponse($result, __('No services found in the API response.'));
            }

            $updateResult = $this->processServices($services);
            
                $this->setServiceDefinedConfig($updateResult['successCount']);
                $this->cleanCache();
            
            return $this->createSuccessResponse($result, $updateResult);

        } catch (\Exception $e) {
            return $this->createErrorResponse($result, __('Error updating services: %1', $e->getMessage()));
        }
    }

    /**
     * Set has_service_define config
     *
     * @param int|null $servicesDefine
     */
    private function setServiceDefinedConfig($servicesDefine): void
    {
        $this->configWriter->save(
            'carriers/sigep_web_carrier/has_service_define',
            $servicesDefine > 0 ? 1 : 0,
            'default',
            0
        );
    }

    /**
     * Clean relevant caches
     */
    private function cleanCache(): void
    {
        $this->cacheTypeList->cleanType('config');
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }

    /**
     * Process all services from the API
     *
     * @param array $services
     * @return array
     */
    private function processServices(array $services): array
    {
        $successCount = 0;
        $errors = [];

        foreach ($services as $service) {
            if (!$this->isValidService($service)) {
                continue;
            }

            try {
                $additionalServices = $this->correiosService->getServicesAdditional($service['codigo']);
                if (!empty($additionalServices)) {
                    $serviceData = $this->processServiceData($service, $additionalServices);
                    $this->saveService($serviceData);
                    $successCount++;
                }
            } catch (\Exception $e) {
                $errors[] = __('Error processing service %1: %2', $service['codigo'], $e->getMessage());
            }
        }

        return [
            'successCount' => $successCount,
            'errors' => $errors
        ];
    }

    /**
     * Check if service is valid for processing
     *
     * @param array $service
     * @return bool
     */
    private function isValidService(array $service): bool
    {
        return isset($service['descSegmento']) && $service['descSegmento'] === 'ENCOMENDA';
    }

    /**
     * Process service data with additional services
     *
     * @param array $service
     * @param array $additionalServices
     * @return array
     */
    private function processServiceData(array $service, array $additionalServices): array
    {
        $serviceData = [
            'code' => $service['codigo'],
            'name' => $service['descricao'],
            'category' => $service['descSegmento'],
            'status' => 1,
            'hasAr' => false,
            'hasMp' => false,
            'hasVd' => false,
            'declaredMinValue' => 0.00,
            'declaredMaxValue' => 0.00
        ];

        foreach ($additionalServices as $additional) {
            $this->processAdditionalService($additional, $serviceData);
        }

        return $serviceData;
    }

    /**
     * Process additional service data
     *
     * @param array $additional
     * @param array $serviceData
     * @return void
     */
    private function processAdditionalService(
        array $additional,
        array &$serviceData
    ): void {
        switch ($additional['sigla']) {
            case 'AR':
                $serviceData['hasAr'] = true;
                break;
            case 'MP':
                $serviceData['hasMp'] = true;
                break;
            case 'VD':
            case 'VDS':
                $serviceData['hasVd'] = true;
                $serviceData['declaredMinValue'] = $additional['vlMinDeclarado'] ?? 0.00;
                $serviceData['declaredMaxValue'] = $additional['vlMaxDeclarado'] ?? 0.00;
                break;
                
        }
    }

    /**
     * Save service to repository
     *
     * @param array $serviceData
     * @throws \Exception
     */
    private function saveService(array $serviceData): void
    {
        try {
            $sigepService = $this->servicesRepository->getByCode($serviceData['code']);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $sigepService = $this->servicesFactory->create();
        }

        $sigepService->setCode($serviceData['code']);
        $sigepService->setName($serviceData['name']);
        $sigepService->setCategory($serviceData['category']);
        $sigepService->setStatus($serviceData['status']);
        $sigepService->setDeclaredMinValue($serviceData['declaredMinValue']);
        $sigepService->setDeclaredMaxValue($serviceData['declaredMaxValue']);
        $sigepService->setHasMp($serviceData['hasMp']);
        $sigepService->setHasAr($serviceData['hasAr']);
        $sigepService->setHasVd($serviceData['hasVd']);
        
        $this->servicesRepository->save($sigepService);
    }

    /**
     * Create success response
     *
     * @param \Magento\Framework\Controller\Result\Json $result
     * @param array $updateResult
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function createSuccessResponse($result, array $updateResult): \Magento\Framework\Controller\Result\Json
    {
        $message = __('Services updated successfully. Total services saved: %1', $updateResult['successCount']);
        if (!empty($updateResult['errors'])) {
            $message .= "\n" . __('Errors occurred:') . "\n" . implode("\n", $updateResult['errors']);
        }

        return $result->setData([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Create error response
     *
     * @param \Magento\Framework\Controller\Result\Json $result
     * @param string $message
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function createErrorResponse($result, string $message): \Magento\Framework\Controller\Result\Json
    {
        return $result->setData([
            'success' => false,
            'message' => $message
        ]);
    }

    /**
     * Check admin permissions for this controller
     *
     * @return boolean
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('O2TI_SigepWebCarrier::config');
    }
}
