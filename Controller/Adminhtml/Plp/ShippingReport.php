<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Controller\Adminhtml\Plp;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Model\Plp\ShippingReportProcessor;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

/**
 * Class ShippingReport
 * Controller for downloading shipping report in PDF format.
 */
class ShippingReport extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'O2TI_SigepWebCarrier::plp';

    /**
     * @var ShippingReportProcessor
     */
    protected $shipReportProcessor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DriverFile
     */
    protected $driverFile;

    /**
     * @var PlpSession
     */
    protected $plpSession;

    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @param Context $context
     * @param ShippingReportProcessor $shipReportProcessor
     * @param LoggerInterface $logger
     * @param DriverFile $driverFile
     * @param PlpSession $plpSession
     * @param PlpRepositoryInterface $plpRepository
     */
    public function __construct(
        Context $context,
        ShippingReportProcessor $shipReportProcessor,
        LoggerInterface $logger,
        DriverFile $driverFile,
        PlpSession $plpSession,
        PlpRepositoryInterface $plpRepository
    ) {
        parent::__construct($context);
        $this->shipReportProcessor = $shipReportProcessor;
        $this->logger = $logger;
        $this->driverFile = $driverFile;
        $this->plpSession = $plpSession;
        $this->plpRepository = $plpRepository;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface|mixed
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        
        $plpId = $this->plpSession->getCurrentPlpId();
        
        if (!$plpId) {
            $this->messageManager->addErrorMessage(__('PLP ID is required'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $plp = $this->plpRepository->getById($plpId);
            if (!$plp || !in_array($plp->getStatus(), [
                PlpStatus::STATUS_PLP_COMPLETED,
                PlpStatus::STATUS_PLP_AWAITING_SHIPMENT,
                PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION
            ])) {
                $this->messageManager->addErrorMessage(
                    __('Shipping report is only available for completed or in-process PLPs')
                );
                return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
            }
            
            $reportResult = $this->shipReportProcessor->processReport($plpId);
            
            if (!$reportResult['success']) {
                throw new LocalizedException(
                    __('Failed to generate shipping report: %1', $reportResult['message'] ?? '')
                );
            }
            
            if (isset($reportResult['filepath']) && $this->driverFile->isExists($reportResult['filepath'])) {
                $filePath = $reportResult['filepath'];
                $content = $this->driverFile->fileGetContents($filePath);
                $filename = $reportResult['filename'];
                
                $response = $this->getResponse();
                $response->setHeader('Content-Type', 'application/pdf');
                $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
                $response->setBody($content);
                
                return $response;
            }
            
            throw new LocalizedException(__('Report file not found'));
            
        } catch (LocalizedException $exc) {
            $this->messageManager->addErrorMessage($exc->getMessage());
            return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
        } catch (\Exception $exc) {
            $this->logger->critical($exc);
            $this->messageManager->addErrorMessage(
                __('An error occurred while generating the shipping report. Please check the logs for details.')
            );
            return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
        }
    }
}
