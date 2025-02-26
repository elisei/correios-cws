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
use O2TI\SigepWebCarrier\Model\Session\PlpSession;

class Edit extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var PlpSession
     */
    protected $plpSession;

    /**
     * Contruct.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param PlpSession $plpSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        PlpSession $plpSession
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->plpSession = $plpSession;
    }

    /**
     * Edit PLP action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $plpId = $this->getRequest()->getParam('id');
        $resultPage = $this->resultPageFactory->create();

        $resultPage->getConfig()->getTitle()->prepend(__('New PLP'));
        if ($plpId) {
            $this->plpSession->setCurrentPlpId($plpId);
            $resultPage->getConfig()->getTitle()->prepend(__('Edit PLP #%1', $plpId));
        }
        
        return $resultPage;
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
        return $this->_authorization->isAllowed('O2TI_SigepWebCarrier::plp_manage');
    }
}
