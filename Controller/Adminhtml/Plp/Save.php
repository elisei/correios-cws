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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\PlpFactory;

class Save extends Action
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var PlpFactory
     */
    private $plpFactory;

    /**
     * @var PlpRepositoryInterface
     */
    private $plpRepository;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param PlpFactory $plpFactory
     * @param PlpRepositoryInterface $plpRepository
     */
    public function __construct(
        Context $context,
        DataPersistorInterface $dataPersistor,
        PlpFactory $plpFactory,
        PlpRepositoryInterface $plpRepository
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->plpFactory = $plpFactory;
        $this->plpRepository = $plpRepository;
        parent::__construct($context);
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            if (empty($data['entity_id'])) {
                $data['entity_id'] = null;
            }

            /** @var \O2TI\SigepWebCarrier\Model\Plp $model */
            $model = $this->plpFactory->create();

            $plpId = $this->getRequest()->getParam('entity_id');
            if ($plpId) {
                try {
                    $model = $this->plpRepository->getById($plpId);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This PLP no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            try {
                $this->plpRepository->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the PLP.'));
                $this->dataPersistor->clear('plp');

                if ($this->getRequest()->getParam('back') === 'edit'
                || $this->getRequest()->getParam('saveandcontinue')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => false]);
                }

                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the PLP.'));
            }

            $this->dataPersistor->set('plp', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
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
