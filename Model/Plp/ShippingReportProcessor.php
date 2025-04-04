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

use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\RuntimeException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\PdfReportGenerator;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

/**
 * Shipping Report Processor - Processe data
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShippingReportProcessor
{
    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @var PlpOrderCollectionFactory
     */
    protected $plpOrderCollec;

    /**
     * @var PdfReportGenerator
     */
    protected $pdfReportGenerator;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpOrderCollectionFactory $plpOrderCollec
     * @param PdfReportGenerator $pdfReportGenerator
     * @param LoggerInterface $logger
     * @param Json $json
     */
    public function __construct(
        PlpRepositoryInterface $plpRepository,
        PlpOrderCollectionFactory $plpOrderCollec,
        PdfReportGenerator $pdfReportGenerator,
        LoggerInterface $logger,
        Json $json
    ) {
        $this->plpRepository = $plpRepository;
        $this->plpOrderCollec = $plpOrderCollec;
        $this->pdfReportGenerator = $pdfReportGenerator;
        $this->logger = $logger;
        $this->json = $json;
    }

    /**
     * Process shipping report for a PPN
     *
     * @param int $plpId PPN ID
     * @return array
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function processReport(int $plpId): array
    {
        try {
            $plp = $this->plpRepository->getById($plpId);
            if (!$plp) {
                throw new LocalizedException(__('PPN with ID %1 not found', $plpId));
            }

            $collection = $this->plpOrderCollec->create();
            $collection->addFieldToFilter('plp_id', $plpId);

            if ($collection->getSize() === 0) {
                throw new LocalizedException(__('No orders found in PPN %1', $plpId));
            }

            $reportData = [];
            foreach ($collection as $plpOrder) {
                $processingData = $plpOrder->getProcessingData();
                $collectedData = $plpOrder->getCollectedData();
                
                $dataEntry = [];
                
                // Extract data from collected_data
                if (!empty($collectedData)) {
                    $collectedDataArray = $this->json->unserialize($collectedData);
                    $dataEntry['nfe'] = $collectedDataArray['numeroNotaFiscal'] ?? 'N/A';

                    if (isset($collectedDataArray['destinatario']) && is_array($collectedDataArray['destinatario'])) {
                        $dataEntry['customer_name'] = $collectedDataArray['destinatario']['nome'] ?? 'N/A';
                        $dataEntry['customer_zip'] = $collectedDataArray['destinatario']['endereco']['cep'] ?? 'N/A';
                    }
                    
                    $dataEntry['declared_weight'] = $collectedDataArray['pesoInformado'] ?? 'N/A';
                    $dataEntry['service_code'] = $collectedDataArray['codigoServico'] ?? 'N/A';
                }
                
                if (!empty($processingData)) {
                    $processingDataArray = $this->json->unserialize($processingData);
                    $dataEntry['id'] = $processingDataArray['id'] ?? 'N/A';
                    $dataEntry['tracking'] = $processingDataArray['tracking'] ?? 'N/A';
                }
                
                $reportData[] = $dataEntry;
            }

            if (empty($reportData)) {
                throw new LocalizedException(__('No valid data found for shipping report in PPN %1', $plpId));
            }

            $result = $this->pdfReportGenerator->generate($reportData, $plpId);

            if (!$result['success']) {
                throw new LocalizedException($result['message']);
            }

            return [
                'success' => $result['success'],
                'filename' => $result['filename'],
                'filepath' => $result['filepath']
            ];

        } catch (RuntimeException $exc) {
            $this->logger->critical($exc);
            throw new LocalizedException(__('Error processing shipping report: %1', $exc->getMessage()));
        }
    }
}
