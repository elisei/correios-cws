<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Plp;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Model\Plp\StoreInformation;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use Zend_Pdf;
use Zend_Pdf_Font;
use Zend_Pdf_Page;
use Zend_Pdf_Color_Rgb;

/**
 * Pdf Report Generator - Generate data
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class PdfReportGenerator
{
    /**
     * @var Filesystem
     */
    protected $filesystem;
    
    /**
     * @var DriverFile
     */
    protected $driver;
    
    /**
     * @var File
     */
    protected $fileIo;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreInformation
     */
    protected $storeInformation;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Filesystem $filesystem
     * @param DriverFile $driver
     * @param File $fileIo
     * @param LoggerInterface $logger
     * @param StoreInformation $storeInformation
     * @param Config $config
     */
    public function __construct(
        Filesystem $filesystem,
        DriverFile $driver,
        File $fileIo,
        LoggerInterface $logger,
        StoreInformation $storeInformation,
        Config $config
    ) {
        $this->filesystem = $filesystem;
        $this->driver = $driver;
        $this->fileIo = $fileIo;
        $this->logger = $logger;
        $this->storeInformation = $storeInformation;
        $this->config = $config;
    }

    /**
     * Generate PDF report
     *
     * @param array $reportData
     * @param int $plpId
     * @return array
     * @throws LocalizedException
     */
    public function generate(array $reportData, int $plpId): array
    {
        try {
            $pdf = new Zend_Pdf();
            
            // Calculate total number of pages first
            $itemsPerPage = 20; // Approximate number of items that fit on a page
            $totalPages = ceil(count($reportData) / $itemsPerPage);
            
            $page = $this->createPdfPage($pdf);
            
            // Add header first
            $this->addReportHeader($page, $plpId, 1, $totalPages);
            
            // Add sender information below the header
            $this->addSenderInformation($page);
            
            // Add column headers
            $this->addColumnHeaders($page);
            
            // Add data rows
            $currentY = 600; // Adjusted for the new layout with sender info below title
            $rowHeight = 25;
            $pageIndex = 0;
            
            foreach ($reportData as $index => $data) {
                // Check if we need a new page
                if ($currentY <= 100) {
                    $pageIndex++;
                    $page = $this->createPdfPage($pdf);
                    $this->addReportHeader($page, $plpId, $pageIndex + 1, $totalPages);
                    $this->addSenderInformation($page);
                    $this->addColumnHeaders($page);
                    $currentY = 600; // Adjusted for new pages too
                }
                
                $this->addDataRow($page, $data, $currentY, $index + 1);
                $currentY -= $rowHeight;
            }
            
            // Save PDF to file
            $filename = $this->generateReportFilename($plpId);
            $filePath = $this->getReportFilePath($filename);
            
            // Save the PDF
            $pdfData = $pdf->render();
            $this->driver->filePutContents($filePath, $pdfData);
            
            return [
                'success' => true,
                'message' => __('Shipping report generated successfully'),
                'filename' => $filename,
                'filepath' => $filePath
            ];
            
        } catch (\Exception $e) {
            $this->logger->critical('Error generating shipping report PDF: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Failed to generate shipping report: %1', $e->getMessage())
            ];
        }
    }

    /**
     * Create a new PDF page
     *
     * @param Zend_Pdf $pdf
     * @return Zend_Pdf_Page
     */
    protected function createPdfPage(Zend_Pdf $pdf)
    {
        $page = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
        $pdf->pages[] = $page;
        
        // Set default font with smaller size
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 8);
        
        return $page;
    }

    /**
     * Add sender information to the report
     *
     * @param Zend_Pdf_Page $page
     */
    protected function addSenderInformation(Zend_Pdf_Page $page)
    {
        $senderData = $this->storeInformation->getSenderData();
        
        // Get contract info from config
        $contract = $this->config->getContract();
        $postingCard = $this->config->getPostingCard();
        $correiosId = $this->config->getCorreiosId();
        
        // Heading
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD), 10);
        $page->drawText(__('Sender Information'), 30, 710, 'UTF-8');
        
        // Set font for details
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 8);
        
        // Left column - Contract info
        $leftCol = 30;
        $page->drawText(__('Contract Number: %1', $contract), $leftCol, 695, 'UTF-8');
        $page->drawText(__('Card Number: %1', $postingCard), $leftCol, 685, 'UTF-8');
        $page->drawText(__('Administrative Code: %1', $correiosId), $leftCol, 675, 'UTF-8');
        
        // Center column - Sender info
        $centerCol = 220;
        $page->drawText(__('Sender: %1', $senderData['name']), $centerCol, 695, 'UTF-8');
        $page->drawText(__('CNPJ: %1', $senderData['cpf_cnpj']), $centerCol, 685, 'UTF-8');
        $page->drawText(__('Phone: %1', $senderData['telephone']), $centerCol, 675, 'UTF-8');
        
        // Right column - Email
        $rightCol = 410;
        $page->drawText(__('Email: %1', $senderData['email']), $rightCol, 695, 'UTF-8');
        
        // Address (full width)
        $address = sprintf(
            '%s, %s %s, %s - %s/%s - CEP: %s',
            $senderData['street'][0],
            $senderData['street'][1],
            $senderData['street'][2],
            $senderData['street'][3],
            $senderData['city'],
            $senderData['region_code'],
            $senderData['postcode']
        );
        
        $page->drawText(__('Address: %1', $address), $leftCol, 660, 'UTF-8');
        
        // Divider line
        $page->drawLine(30, 650, 565, 650);
    }

    /**
     * Add report header
     *
     * @param Zend_Pdf_Page $page
     * @param int $plpId
     * @param int $pageNumber
     * @param int $totalPages
     */
    protected function addReportHeader(Zend_Pdf_Page $page, int $plpId, int $pageNumber = 1, int $totalPages = 1)
    {
        // Title
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD), 14);
        $page->drawText(__('Shipping Report - PLP #%1', $plpId), 30, 780, 'UTF-8');
        
        // Date
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 8);
        $page->drawText(__('Generated on: %1', date('Y-m-d H:i:s')), 30, 760, 'UTF-8');
        
        // Page number with total pages
        $page->drawText(__('Page %1 of %2', $pageNumber, $totalPages), 500, 760, 'UTF-8');
        
        // Line
        $page->drawLine(30, 730, 565, 730);
    }

    /**
     * Add column headers
     *
     * @param Zend_Pdf_Page $page
     */
    protected function addColumnHeaders(Zend_Pdf_Page $page)
    {
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD), 8);
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0.8, 0.8, 0.8));
        
        // Header background with adjusted margins
        $page->drawRectangle(30, 630, 565, 610);
        
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
        
        // Column headers with adjusted positions
        $page->drawText('#', 40, 617, 'UTF-8');
        $page->drawText(__('Tracking'), 60, 617, 'UTF-8');
        $page->drawText(__('CEP'), 160, 617, 'UTF-8');
        $page->drawText(__('Weight'), 215, 617, 'UTF-8');
        $page->drawText(__('NFe'), 270, 617, 'UTF-8');
        $page->drawText(__('Serv. Code'), 350, 617, 'UTF-8');
        $page->drawText(__('Customer Name'), 430, 617, 'UTF-8');
    }

    /**
     * Add data row
     *
     * @param Zend_Pdf_Page $page
     * @param array $data
     * @param int $axisY
     * @param int $rowNumber
     */
    protected function addDataRow(Zend_Pdf_Page $page, array $data, int $axisY, int $rowNumber)
    {
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 8);
        
        if ($rowNumber % 2 == 0) {
            $page->setFillColor(new Zend_Pdf_Color_Rgb(0.95, 0.95, 0.95));
            $page->drawRectangle(30, $axisY, 565, $axisY - 25);
        }
        
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
        
        $customerName = $this->truncateText($data['customer_name'], 20);
        $customerZip = $data['customer_zip'] ?? 'N/A';
        $declaredWeight = $data['declared_weight'] ?? 'N/A';
        $serviceCode = $data['service_code'] ?? 'N/A';
        $tracking = $this->truncateText($data['tracking'], 20);
        $nfeNumber = $data['nfe'] ?? 'N/A';

        $axisY = $axisY - 15; // Center text vertically in the row
        
        $page->drawText($rowNumber, 40, $axisY, 'UTF-8');
        $page->drawText($tracking, 60, $axisY, 'UTF-8');
        $page->drawText($customerZip, 160, $axisY, 'UTF-8');
        $page->drawText($declaredWeight, 215, $axisY, 'UTF-8');
        $page->drawText($nfeNumber, 270, $axisY, 'UTF-8');
        $page->drawText($serviceCode, 350, $axisY, 'UTF-8');
        $page->drawText($customerName, 430, $axisY, 'UTF-8');
    }

    /**
     * Truncate text to fit in column
     *
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    protected function truncateText($text, $maxLength)
    {
        if (mb_strlen($text) > $maxLength) {
            return mb_substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }

    /**
     * Generate report filename
     *
     * @param int $plpId
     * @return string
     */
    protected function generateReportFilename($plpId)
    {
        $timestamp = date('YmdHis');
        return 'sigepweb_shipping_report_plp_' . $plpId . '_' . $timestamp . '.pdf';
    }

    /**
     * Get report file path
     *
     * @param string $filename
     * @return string
     */
    protected function getReportFilePath($filename)
    {
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $dirPath = 'sigepweb/reports';
        
        if (!$this->driver->isDirectory($mediaDirectory->getAbsolutePath($dirPath))) {
            $this->fileIo->mkdir($mediaDirectory->getAbsolutePath($dirPath), 0775);
        }
        
        return $mediaDirectory->getAbsolutePath($dirPath . '/' . $filename);
    }
}
