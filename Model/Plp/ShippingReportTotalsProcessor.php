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
use O2TI\SigepWebCarrier\Model\Plp\PdfReportTotalsGenerator;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

class ShippingReportTotalsProcessor
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
     * @var PdfReportTotalsGenerator
     */
    protected $pdfReportTotals;

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
     * @param PdfReportTotalsGenerator $pdfReportTotals
     * @param LoggerInterface $logger
     * @param Json $json
     */
    public function __construct(
        PlpRepositoryInterface $plpRepository,
        PlpOrderCollectionFactory $plpOrderCollec,
        PdfReportTotalsGenerator $pdfReportTotals,
        LoggerInterface $logger,
        Json $json
    ) {
        $this->plpRepository = $plpRepository;
        $this->plpOrderCollec = $plpOrderCollec;
        $this->pdfReportTotals = $pdfReportTotals;
        $this->logger = $logger;
        $this->json = $json;
    }

    /**
     * Process pre-shipping list for a PPN
     *
     * @param int $plpId PPN ID
     * @return array
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function processPreShippingList(int $plpId): array
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
                    
                    if (isset($collectedDataArray['codigoServico'])) {
                        $dataEntry['service_code'] = $collectedDataArray['codigoServico'];
                    }
                    
                    if (isset($collectedDataArray['numeroNotaFiscal'])) {
                        $dataEntry['nfe'] = $collectedDataArray['numeroNotaFiscal'];
                    }
                    
                    if (isset($collectedDataArray['destinatario']) && is_array($collectedDataArray['destinatario'])) {
                        if (isset($collectedDataArray['destinatario']['nome'])) {
                            $dataEntry['customer_name'] = $collectedDataArray['destinatario']['nome'];
                        }
                        
                        if (isset($collectedDataArray['destinatario']['endereco']['cep'])) {
                            $dataEntry['customer_zip'] = $collectedDataArray['destinatario']['endereco']['cep'];
                        }
                    }
                }
                
                // Extract data from processing_data
                if (!empty($processingData)) {
                    $processingDataArray = $this->json->unserialize($processingData);
                    
                    if (isset($processingDataArray['id'])) {
                        $dataEntry['id'] = $processingDataArray['id'];
                    }
                    
                    if (isset($processingDataArray['tracking'])) {
                        $dataEntry['tracking'] = $processingDataArray['tracking'];
                    }
                }
                
                $reportData[] = $dataEntry;
            }

            if (empty($reportData)) {
                throw new LocalizedException(__('No valid data found for pre-shipping list in PPN %1', $plpId));
            }

            $result = $this->pdfReportTotals->generatePreShippingList($reportData, $plpId);

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
            throw new LocalizedException(__('Error processing pre-shipping list: %1', $exc->getMessage()));
        }
    }
}
