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
use O2TI\SigepWebCarrier\Model\Plp\DeclarationProcessor;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

/**
 * Class Declaration
 * Controller for downloading declaration content in HTML format.
 */
class Declaration extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'O2TI_SigepWebCarrier::plp';

    /**
     * @var DeclarationProcessor
     */
    protected $declarationProcessor;

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
     * @param DeclarationProcessor $declarationProcessor
     * @param LoggerInterface $logger
     * @param DriverFile $driverFile
     * @param PlpSession $plpSession
     * @param PlpRepositoryInterface $plpRepository
     */
    public function __construct(
        Context $context,
        DeclarationProcessor $declarationProcessor,
        LoggerInterface $logger,
        DriverFile $driverFile,
        PlpSession $plpSession,
        PlpRepositoryInterface $plpRepository
    ) {
        parent::__construct($context);
        $this->declarationProcessor = $declarationProcessor;
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
                    __('Declaration content is only available for completed PLPs')
                );
                return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
            }
            
            $declarationResult = $this->declarationProcessor->processDeclaration($plpId);
            
            if (!$declarationResult['success']) {
                throw new LocalizedException(
                    __('Failed to process declaration content: %1', $declarationResult['message'] ?? '')
                );
            }
            
            if (isset($declarationResult['filepath']) && $this->driverFile->isExists($declarationResult['filepath'])) {
                $filePath = $declarationResult['filepath'];
                $content = $this->driverFile->fileGetContents($filePath);
                $filename = $declarationResult['filename'];
                
                $response = $this->getResponse();
                $response->setHeader('Content-Type', 'text/html');
                $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
                $response->setBody($content);
                
                return $response;
            }
            
        } catch (LocalizedException $exc) {
            $this->messageManager->addErrorMessage($exc->getMessage());
            return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
        } catch (\Exception $exc) {
            $this->logger->critical($exc);
            $this->messageManager->addErrorMessage(
                __('An error occurred while downloading the declaration. Please check the logs for details.')
            );
            return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
        }
    }
}
