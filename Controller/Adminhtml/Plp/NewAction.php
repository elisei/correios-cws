<?php
namespace O2TI\SigepWebCarrier\Controller\Adminhtml\Plp;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;

class NewAction extends Action
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
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param PlpSession $plpSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        PlpSession $plpSession
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->plpSession = $plpSession;
        parent::__construct($context);
    }

    /**
     * Create new PLP
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // Limpa o ID da PLP atual da sessão
        $this->plpSession->setCurrentPlpId(null);

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('New PLP'));
        return $resultPage;
    }

    /**
     * Check admin permissions for this controller
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('O2TI_SigepWebCarrier::plp_manage');
    }
}
