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
use Magento\Framework\View\Result\PageFactory;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Controller\ResultFactory;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;

class View extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @var PlpSession
     */
    protected $plpSession;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpSession $plpSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        PlpRepositoryInterface $plpRepository,
        PlpSession $plpSession
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->plpRepository = $plpRepository;
        $this->plpSession = $plpSession;
    }

    /**
     * View PLP action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $plpId = $this->getRequest()->getParam('id');
        try {
            $plp = $this->plpRepository->getById($plpId);

            $this->plpSession->setCurrentPlpId($plpId);
            
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->prepend(__('View PLP #%1', $plp->getEntityId()));
            
            return $resultPage;
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This PLP no longer exists.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('*/*/');
        }
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
        return $this->_authorization->isAllowed('O2TI_SigepWebCarrier::plp_view');
    }
}
