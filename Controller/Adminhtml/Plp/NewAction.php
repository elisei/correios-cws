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
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\PlpFactory;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;

class NewAction extends Action
{
    /**
     * @var PlpFactory
     */
    protected $plpFactory;

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
     * @param PlpFactory $plpFactory
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpSession $plpSession
     */
    public function __construct(
        Context $context,
        PlpFactory $plpFactory,
        PlpRepositoryInterface $plpRepository,
        PlpSession $plpSession
    ) {
        $this->plpFactory = $plpFactory;
        $this->plpRepository = $plpRepository;
        $this->plpSession = $plpSession;
        parent::__construct($context);
    }

    /**
     * Create new PPN and redirect to edit page
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $this->plpSession->setCurrentPlpId(null);

        try {
            $plp = $this->plpFactory->create();
            $plp->setStatus('opened');
            $plp->setStoreId(1);

            $this->plpRepository->save($plp);
            $this->plpSession->setCurrentPlpId($plp->getId());
            $this->messageManager->addSuccessMessage(__('New PPN has been created.'));
            
            return $this->resultRedirectFactory->create()->setPath(
                '*/*/edit',
                ['id' => $plp->getId(), '_current' => true]
            );
            
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while creating the PPN.')
            );
        }
        
        return $this->resultRedirectFactory->create()->setPath('*/*/');
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
