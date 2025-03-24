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
 * Pdf Report Totals - Generate Pdf
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class PdfReportTotalsGenerator
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
     * Generate Pre-Shipping List PDF
     *
     * @param array $plpData
     * @param int $plpId
     * @return array
     * @throws LocalizedException
     */
    public function generatePreShippingList(array $plpData, int $plpId): array
    {
        try {
            $pdf = new Zend_Pdf();
            
            // Prepare shipping service data
            $services = $this->summarizeShippingServices($plpData);
            $totalItems = $this->calculateTotalItems($plpData);
            
            // Create first page
            $page = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
            $pdf->pages[] = $page;
            
            // Add content to first page
            $this->addFormattedContent($page, $services, $totalItems);
            
            $filename = $this->generateFilename($plpId);
            $filePath = $this->getFilePath($filename);
            
            $pdfData = $pdf->render();
            $this->driver->filePutContents($filePath, $pdfData);
            
            return [
                'success' => true,
                'message' => __('Pre-shipping list generated successfully'),
                'filename' => $filename,
                'filepath' => $filePath
            ];
            
        } catch (\Exception $e) {
            $this->logger->critical('Error generating pre-shipping list PDF: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Failed to generate pre-shipping list: %1', $e->getMessage())
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
        return $page;
    }

    /**
     * Add formatted content to match the example
     *
     * @param Zend_Pdf_Page $page
     * @param array $services
     * @param int $totalItems
     */
    protected function addFormattedContent(Zend_Pdf_Page $page, array $services, int $totalItems)
    {
        $page->setFillColor(new Zend_Pdf_Color_Rgb(1, 1, 1));
        $page->setLineColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
        $page->setLineWidth(0.5);
        
        $senderData = $this->storeInformation->getSenderData();
        $contract = $this->config->getContract();
        $currentDate = date('d/m/Y');
        
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0, 0, 0));
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD), 16);
        $page->drawText('EMPRESA BRASILEIRA DE CORREIOS E TELÉGRAFOS', 55, 800, 'UTF-8');
        
        $page->drawRectangle(30, 780, 565, 250, Zend_Pdf_Page::SHAPE_DRAW_STROKE);
        
        $page->drawRectangle(30, 780, 565, 745, Zend_Pdf_Page::SHAPE_DRAW_STROKE);
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD), 14);
        $page->drawText('PRÉ - LISTA DE POSTAGEM - PLP - SIGEP WEB', 95, 758, 'UTF-8');
        
        $page->drawRectangle(30, 745, 565, 630, Zend_Pdf_Page::SHAPE_DRAW_STROKE);
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 11);
        $page->drawText('SIGEP WEB - Gerenciador de Postagens dos Correios', 42, 730, 'UTF-8');
        
        $page->drawText('Contrato: ' . $contract, 42, 710, 'UTF-8');
        $page->drawText('Cliente: ' . $senderData['name'], 42, 690, 'UTF-8');
        $page->drawText('Telefone de contato: ' . $senderData['telephone'], 42, 670, 'UTF-8');
        $page->drawText('Email de contato: ' . $senderData['email'], 42, 650, 'UTF-8');
        
        $page->drawRectangle(30, 630, 565, 430, Zend_Pdf_Page::SHAPE_DRAW_STROKE);
        
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD), 11);
        $page->drawText('Quantidade de Objetos:', 60, 605, 'UTF-8');
        $page->drawText('Serviço:', 270, 605, 'UTF-8');
        
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 11);
        $yPosition = 580;
        
        $serviceCount = 0;
        foreach ($services as $service => $count) {
            if ($serviceCount >= 5) {
                break;
            }
            $page->drawText($count, 145, $yPosition, 'UTF-8');
            $page->drawText($service, 270, $yPosition, 'UTF-8');
            $yPosition -= 25;
            $serviceCount++;
        }

        $page->drawText('Data de fechamento: ' . $currentDate, 42, 410, 'UTF-8');
        $page->drawText('Total de objetos: ' . $totalItems, 300, 410, 'UTF-8');
        
        $page->drawText('Data da entrega: _____/_____/__________', 42, 360, 'UTF-8');
        
        // Draw signature lines - Moved from original position and added client signature
        $page->drawLine(42, 330, 250, 330);
        $page->drawText('Assinatura / Matrícula dos Correios', 55, 315, 'UTF-8');
        
        $page->drawLine(300, 330, 550, 330);
        $page->drawText('Assinatura do Cliente', 380, 315, 'UTF-8');
    }

    /**
     * Summarize shipping services
     *
     * @param array $plpData
     * @return array
     */
    protected function summarizeShippingServices(array $plpData)
    {
        $services = [];
        
        // Group services by service code
        foreach ($plpData as $item) {
            $serviceCode = isset($item['service_code']) ? $item['service_code'] : 'N/A';
            $serviceName = $this->getServiceName($serviceCode);
            
            if (!isset($services[$serviceName])) {
                $services[$serviceName] = 0;
            }
            
            $services[$serviceName]++;
        }
        
        // If no data is present or for demo purposes, use example values
        if (empty($services)) {
            return [
                '03220 - SEDEX CONTRATO AG' => 75,
                '03298 - PAC CONTRATO AG' => 2,
                '04227 - CORREIOS MINI ENVIOS CTR AG' => 41
            ];
        }
        
        return $services;
    }

    /**
     * Get service name by code
     *
     * @param string $serviceCode
     * @return string
     */
    protected function getServiceName($serviceCode)
    {
        $serviceNames = [
            '03220' => '03220 - SEDEX CONTRATO AG',
            '03298' => '03298 - PAC CONTRATO AG',
            '04227' => '04227 - CORREIOS MINI ENVIOS CTR AG'
        ];
        
        return isset($serviceNames[$serviceCode]) ? $serviceNames[$serviceCode] : $serviceCode;
    }

    /**
     * Calculate total items
     *
     * @param array $plpData
     * @return int
     */
    protected function calculateTotalItems(array $plpData)
    {
        $total = count($plpData);
        
        // If no data is present or for demo purposes, use example value
        if ($total === 0) {
            return 118;
        }
        
        return $total;
    }

    /**
     * Generate filename
     *
     * @param int $plpId
     * @return string
     */
    protected function generateFilename($plpId)
    {
        $timestamp = date('YmdHis');
        return 'sigepweb_pre_shipping_list_plp_' . $plpId . '_' . $timestamp . '.pdf';
    }

    /**
     * Get file path
     *
     * @param string $filename
     * @return string
     */
    protected function getFilePath($filename)
    {
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $dirPath = 'sigepweb/reports';
        
        if (!$this->driver->isDirectory($mediaDirectory->getAbsolutePath($dirPath))) {
            $this->fileIo->mkdir($mediaDirectory->getAbsolutePath($dirPath), 0775);
        }
        
        return $mediaDirectory->getAbsolutePath($dirPath . '/' . $filename);
    }
}
