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
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;

class AddOrders extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var PlpRepositoryInterface
     */
    private $plpRepository;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param PlpRepositoryInterface $plpRepository
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PlpRepositoryInterface $plpRepository
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->plpRepository = $plpRepository;
        parent::__construct($context);
    }

    /**
     * Add orders to PLP
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $plpId = $this->getRequest()->getParam('plp_id');
        $plp = $this->plpRepository->getById($plpId);
        $orderIds = $this->getRequest()->getParam('order_ids');
        
        if (!$plp->getId()) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('PLP ID is required.')
            ]);
        }
        
        if (!$orderIds) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('No orders selected.')
            ]);
        }
        
        try {
            
            if (!is_array($orderIds)) {
                $orderIds = explode(',', $orderIds);
            }
            
            $this->plpRepository->addOrderToPlp($plpId, $orderIds);
            
            return $resultJson->setData([
                'success' => true,
                'message' => __('Orders were successfully added to the PLP.')
            ]);
        } catch (LocalizedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Something went wrong while adding orders to the PLP.')
            ]);
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
        return $this->_authorization->isAllowed('O2TI_SigepWebCarrier::plp_manage');
    }
}
