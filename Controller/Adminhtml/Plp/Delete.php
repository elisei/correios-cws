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
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Delete extends Action
{
    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @param Context $context
     * @param PlpRepositoryInterface $plpRepository
     */
    public function __construct(
        Context $context,
        PlpRepositoryInterface $plpRepository
    ) {
        parent::__construct($context);
        $this->plpRepository = $plpRepository;
    }

    /**
     * Delete PLP action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        
        $plpId = $this->getRequest()->getParam('id');
        if ($plpId) {
            try {
                $this->plpRepository->deleteById($plpId);
                $this->messageManager->addSuccessMessage(__('The PLP has been deleted.'));
                
                return $resultRedirect->setPath('*/*/');
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This PLP no longer exists.'));
                
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                
                return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Could not delete the PLP: %1', $e->getMessage())
                );
                
                return $resultRedirect->setPath('*/*/edit', ['id' => $plpId]);
            }
        }
        
        $this->messageManager->addErrorMessage(__('We can\'t find a PLP to delete.'));
        
        return $resultRedirect->setPath('*/*/');
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
