<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
class MassPrintDace extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::shipment';

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param CollectionFactory $collectionFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        CollectionFactory $collectionFactory,
        Filesystem $filesystem
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
        parent::__construct($context, $filter);
    }

    /**
     * Mass download DACE files for selected shipments
     *
     * @param AbstractCollection $collection
     * @return ResponseInterface|ResultInterface
     */
    protected function massAction(AbstractCollection $collection)
    {
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $daceFiles = [];

        foreach ($collection as $shipment) {
            $dacePath = $shipment->getData('sigepweb_dace_path');
            if ($dacePath) {
                $relativeFilePath = 'sigepweb/labels/' . $dacePath;
                if ($mediaDirectory->isExist($relativeFilePath)) {
                    $daceFiles[] = [
                        'path' => $relativeFilePath,
                        'name' => $dacePath,
                    ];
                }
            }
        }

        if (empty($daceFiles)) {
            $this->messageManager->addErrorMessage(
                __('There are no DACE files related to selected shipments.')
            );
            return $this->resultRedirectFactory->create()->setPath('sales/shipment/');
        }

        if (count($daceFiles) === 1) {
            $file = $daceFiles[0];
            $content = $mediaDirectory->readFile($file['path']);
            $contentType = $this->getContentType($file['name']);

            return $this->fileFactory->create(
                $file['name'],
                $content,
                DirectoryList::VAR_DIR,
                $contentType
            );
        }

        return $this->createZipDownload($daceFiles, $mediaDirectory);
    }

    /**
     * Create ZIP file with multiple DACE files
     *
     * @param array $daceFiles
     * @param \Magento\Framework\Filesystem\Directory\ReadInterface $mediaDirectory
     * @return ResponseInterface
     */
    private function createZipDownload($daceFiles, $mediaDirectory)
    {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $zipFileName = 'dace_files_' . date('YmdHis') . '.zip';
        $zipFilePath = $varDirectory->getAbsolutePath($zipFileName);

        $zip = new \ZipArchive();
        $zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($daceFiles as $file) {
            $content = $mediaDirectory->readFile($file['path']);
            $zip->addFromString($file['name'], $content);
        }

        $zip->close();

        $content = $varDirectory->readFile($zipFileName);
        $varDirectory->delete($zipFileName);

        return $this->fileFactory->create(
            $zipFileName,
            $content,
            DirectoryList::VAR_DIR,
            'application/zip'
        );
    }

    /**
     * Get content type based on file extension
     *
     * @param string $fileName
     * @return string
     */
    private function getContentType($fileName)
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'txt') {
            return 'text/plain';
        }

        return 'application/pdf';
    }
}
