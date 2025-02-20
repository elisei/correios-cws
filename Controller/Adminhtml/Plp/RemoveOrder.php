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
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder as PlpOrderResource;
use O2TI\SigepWebCarrier\Model\PlpOrderFactory;

class RemoveOrder extends Action
{
    /**
     * @var PlpOrderResource
     */
    protected $plpOrderResource;

    /**
     * @var PlpOrderFactory
     */
    protected $plpOrderFactory;

    /**
     * @param Context $context
     * @param PlpOrderResource $plpOrderResource
     * @param PlpOrderFactory $plpOrderFactory
     */
    public function __construct(
        Context $context,
        PlpOrderResource $plpOrderResource,
        PlpOrderFactory $plpOrderFactory
    ) {
        parent::__construct($context);
        $this->plpOrderResource = $plpOrderResource;
        $this->plpOrderFactory = $plpOrderFactory;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $plpId = $this->getRequest()->getParam('plp_id');
        $orderId = $this->getRequest()->getParam('order_id');

        try {
            $plpOrder = $this->plpOrderFactory->create();
            $this->plpOrderResource->loadByPlpAndOrder($plpOrder, $plpId, $orderId);

            if (!$plpOrder->getId()) {
                $this->messageManager->addErrorMessage(__('Order was not found in PLP.'));
            }

            if ($plpOrder->getId()) {
                $this->plpOrderResource->delete($plpOrder);
                $this->messageManager->addSuccessMessage(__('Order has been removed from PLP.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while removing the order: %1',
                    $e->getMessage()
                )
            );
        }

        return $resultRedirect->setPath('*/*/view', ['id' => $plpId]);
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
