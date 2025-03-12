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
use O2TI\SigepWebCarrier\Model\Plp\ShippingReportTotalsProcessor as TotalsProcessor;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

/**
 * Class ShippingReportTotals
 * Controller for downloading shipping report totals in PDF format.
 */
class ShippingReportTotals extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'O2TI_SigepWebCarrier::plp';

    /**
     * @var TotalsProcessor
     */
    private $totalsProcessor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DriverFile
     */
    private $driverFile;

    /**
     * @var PlpSession
     */
    private $plpSession;

    /**
     * @var PlpRepositoryInterface
     */
    private $plpRepository;

    /**
     * @param Context $context
     * @param TotalsProcessor $totalsProcessor
     * @param LoggerInterface $logger
     * @param DriverFile $driverFile
     * @param PlpSession $plpSession
     * @param PlpRepositoryInterface $plpRepository
     */
    public function __construct(
        Context $context,
        TotalsProcessor $totalsProcessor,
        LoggerInterface $logger,
        DriverFile $driverFile,
        PlpSession $plpSession,
        PlpRepositoryInterface $plpRepository
    ) {
        parent::__construct($context);
        $this->totalsProcessor = $totalsProcessor;
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
            if (!$plp || $plp->getStatus() !== PlpStatus::STATUS_PLP_COMPLETED) {
                $this->messageManager->addErrorMessage(
                    __('Shipping report totals are only available for completed PLPs')
                );
                return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
            }
            
            $reportResult = $this->totalsProcessor->processPreShippingList($plpId);
            
            if (!$reportResult['success']) {
                throw new LocalizedException(
                    __('Failed to generate shipping report totals: %1', $reportResult['message'] ?? '')
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
            
            throw new LocalizedException(__('Shipping report totals file not found'));
            
        } catch (LocalizedException $exc) {
            $this->messageManager->addErrorMessage($exc->getMessage());
            return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
        } catch (\Exception $exc) {
            $this->logger->critical($exc);
            $this->messageManager->addErrorMessage(
                __('An error occurred while generating the shipping report totals. Please check the logs for details.')
            );
            return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
        }
    }
}
