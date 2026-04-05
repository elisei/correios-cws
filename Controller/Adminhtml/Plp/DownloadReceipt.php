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
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

class DownloadReceipt extends Action
{
    public const ADMIN_RESOURCE = 'O2TI_SigepWebCarrier::download_receipt';

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var PlpOrderCollectionFactory
     */
    private $plpOrderCollectionFactory;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param Json $json
     * @param PlpOrderCollectionFactory $plpOrderCollectionFactory
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        Json $json,
        PlpOrderCollectionFactory $plpOrderCollectionFactory
    ) {
        $this->fileFactory = $fileFactory;
        $this->json = $json;
        $this->plpOrderCollectionFactory = $plpOrderCollectionFactory;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $plpOrderId = (int) $this->getRequest()->getParam('plp_order_id');

        try {
            $collection = $this->plpOrderCollectionFactory->create();
            $plpOrder = $collection
                ->addFieldToFilter('entity_id', $plpOrderId)
                ->getFirstItem();

            if (!$plpOrder->getId()) {
                throw new LocalizedException(__('PPN Order not found.'));
            }

            $rawData = $plpOrder->getProcessingData();
            if (empty($rawData)) {
                throw new LocalizedException(__('No processing data found for this PPN Order.'));
            }

            $processingData = $this->json->unserialize($rawData);

            if (empty($processingData['receiptFileName'])) {
                throw new LocalizedException(__('No DACE file available for this PPN Order.'));
            }

            $fileName = $processingData['receiptFileName'];
            $filePath = 'sigepweb/labels/' . $fileName;

            $contentType = str_ends_with($fileName, '.pdf') ? 'application/pdf' : 'text/plain';

            return $this->fileFactory->create(
                $fileName,
                ['type' => 'filename', 'value' => $filePath],
                DirectoryList::MEDIA,
                $contentType
            );
        } catch (LocalizedException $exc) {
            $this->messageManager->addErrorMessage($exc->getMessage());
            return $this->resultRedirectFactory->create()
                ->setRefererUrl();
        }
    }
}
