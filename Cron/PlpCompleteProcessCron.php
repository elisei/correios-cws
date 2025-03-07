<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Cron;

use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Model\Plp\PlpDataCollector;
use O2TI\SigepWebCarrier\Model\Plp\PlpSingleSubmit;
use O2TI\SigepWebCarrier\Model\Plp\PlpLabelRequest;
use O2TI\SigepWebCarrier\Model\Plp\PlpLabelDownload;
use O2TI\SigepWebCarrier\Model\Plp\PlpOrderShipmentCreator;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

class PlpCompleteProcessCron
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PlpDataCollector
     */
    protected $plpDataCollector;

    /**
     * @var PlpSingleSubmit
     */
    protected $plpSingleSubmit;

    /**
     * @var PlpLabelRequest
     */
    protected $plpLabelRequest;

    /**
     * @var PlpLabelDownload
     */
    protected $plpLabelDownload;

    /**
     * @var PlpOrderShipmentCreator
     */
    protected $plpOrdShipCreator;

    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @var PlpCollectionFactory
     */
    protected $plpCollectionFactory;

    /**
     * @var array
     */
    protected $processStats = [
        'total_plps' => 0,
        'completed_plps' => 0,
        'failed_plps' => 0,
        'data_collection' => ['success' => 0, 'errors' => 0],
        'submission' => ['success' => 0, 'errors' => 0],
        'label_requests' => ['success' => 0, 'errors' => 0],
        'label_downloads' => ['success' => 0, 'errors' => 0],
        'shipment_creation' => ['success' => 0, 'errors' => 0]
    ];

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param PlpDataCollector $plpDataCollector
     * @param PlpSingleSubmit $plpSingleSubmit
     * @param PlpLabelRequest $plpLabelRequest
     * @param PlpLabelDownload $plpLabelDownload
     * @param PlpOrderShipmentCreator $plpOrdShipCreator
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpCollectionFactory $plpCollectionFactory
     */
    public function __construct(
        LoggerInterface $logger,
        PlpDataCollector $plpDataCollector,
        PlpSingleSubmit $plpSingleSubmit,
        PlpLabelRequest $plpLabelRequest,
        PlpLabelDownload $plpLabelDownload,
        PlpOrderShipmentCreator $plpOrdShipCreator,
        PlpRepositoryInterface $plpRepository,
        PlpCollectionFactory $plpCollectionFactory
    ) {
        $this->logger = $logger;
        $this->plpDataCollector = $plpDataCollector;
        $this->plpSingleSubmit = $plpSingleSubmit;
        $this->plpLabelRequest = $plpLabelRequest;
        $this->plpLabelDownload = $plpLabelDownload;
        $this->plpOrdShipCreator = $plpOrdShipCreator;
        $this->plpRepository = $plpRepository;
        $this->plpCollectionFactory = $plpCollectionFactory;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info(__('Starting Complete PLP Process cron job'));
        
        try {
            // Get all enabled PLPs that are not completed
            $plps = $this->getEligiblePlps();
            
            if ($plps->getSize() === 0) {
                $this->logger->info(__('No eligible PLPs found to process'));
                return;
            }
            
            $this->processStats['total_plps'] = $plps->getSize();
            
            foreach ($plps as $plp) {
                try {
                    if (!$plp->getCanSendToCws()) {
                        $this->logger->info(__('PLP ID %1 is not enabled for processing, skipping', $plp->getId()));
                        continue;
                    }
                    
                    $this->processPLP($plp);
                    
                } catch (\Exception $e) {
                    $this->logger->error(__('Error processing PLP ID %1: %2', $plp->getId(), $e->getMessage()));
                    $this->processStats['failed_plps']++;
                }
            }
            
            $this->logCompletionSummary();
            
        } catch (\Exception $e) {
            $this->logger->critical(__('Complete PLP Process cron job failed: %1', $e->getMessage()));
        }
    }

    /**
     * Get eligible PLPs for processing
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    protected function getEligiblePlps()
    {
        $collection = $this->plpCollectionFactory->create();
        $collection->addFieldToFilter('status', [
            'in' => [
                PlpStatus::STATUS_PLP_OPENED,
                PlpStatus::STATUS_PLP_COLLECTING_DATA,
                PlpStatus::STATUS_PLP_REQUESTING_RECEIPT,
                PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION,
                PlpStatus::STATUS_PLP_AWAITING_SHIPMENT,
            ]
        ]);
        
        return $collection;
    }

    /**
     * Process a single PLP through all stages
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function processPLP($plp)
    {
        $plpId = $plp->getId();
        $this->logger->info(
            __('Beginning complete processing for PLP ID: %1 [Current Status: %2]', $plpId, $plp->getStatus())
        );
        
        // Step 1: Data Collection (if needed)
        if ($this->shouldCollectData($plp)) {
            $result = $this->runDataCollection($plp);
            if (!$result['success']) {
                return false;
            }
            
            // Reload PLP to get updated status
            $plp = $this->plpRepository->getById($plpId);
        }
        
        // Step 2: Submission (if needed)
        if ($this->shouldSubmit($plp)) {
            $result = $this->runSubmission($plp);
            if (!$result['success']) {
                return false;
            }
            
            // Reload PLP to get updated status
            $plp = $this->plpRepository->getById($plpId);
        }
        
        // Step 3: Label Request (if needed)
        if ($this->shouldRequestLabels($plp)) {
            $result = $this->runLabelRequest($plp);
            if (!$result['success']) {
                return false;
            }
            
            // Reload PLP to get updated status
            $plp = $this->plpRepository->getById($plpId);
        }
        
        // Step 4: Label Download (if needed)
        if ($this->shouldDownloadLabels($plp)) {
            $result = $this->runLabelDownload($plp);
            if (!$result['success']) {
                return false;
            }
            
            // Reload PLP to get updated status
            $plp = $this->plpRepository->getById($plpId);
        }
        
        // Step 5: Shipment Creation (if needed)
        if ($this->shouldCreateShipments($plp)) {
            $result = $this->runShipmentCreation($plp);
            if (!$result['success']) {
                return false;
            }
            
            // Final check
            $plp = $this->plpRepository->getById($plpId);
            if ($plp->getStatus() === PlpStatus::STATUS_PLP_COMPLETED) {
                $this->processStats['completed_plps']++;
                $this->logger->info(__('PLP ID %1 completed successfully', $plpId));
            }
        }
        
        return true;
    }

    /**
     * Check if PLP needs data collection
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldCollectData($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_OPENED;
    }

    /**
     * Run data collection for a PLP
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runDataCollection($plp)
    {
        $this->logger->info(__('Running data collection for PLP %1', $plp->getId()));
        $result = $this->plpDataCollector->execute($plp->getId());
        
        if ($result['success']) {
            $this->processStats['data_collection']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['data_collection']['errors'] += $result['errors'];
        }
        
        $this->logger->info(__(
            'Data collection for PLP %1: %2 (Processed: %3, Errors: %4)',
            $plp->getId(),
            $result['message'],
            $result['processed'],
            $result['errors']
        ));
        
        return $result;
    }

    /**
     * Check if PLP needs submission
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldSubmit($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_COLLECTING_DATA;
    }

    /**
     * Run submission for a PLP
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runSubmission($plp)
    {
        $this->logger->info(__('Running submission for PLP %1', $plp->getId()));
        $result = $this->plpSingleSubmit->execute($plp->getId());
        
        if ($result['success']) {
            $this->processStats['submission']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['submission']['errors'] += $result['errors'];
        }
        
        $this->logger->info(__(
            'Submission for PLP %1: %2 (Processed: %3, Errors: %4)',
            $plp->getId(),
            $result['message'],
            $result['processed'],
            $result['errors']
        ));
        
        return $result;
    }

    /**
     * Check if PLP needs label requests
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldRequestLabels($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_REQUESTING_RECEIPT;
    }

    /**
     * Run label requests for a PLP
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runLabelRequest($plp)
    {
        $this->logger->info(__('Running label requests for PLP %1', $plp->getId()));
        $result = $this->plpLabelRequest->execute($plp->getId());
        
        if ($result['success']) {
            $this->processStats['label_requests']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['label_requests']['errors'] += $result['errors'];
        }
        
        $this->logger->info(__(
            'Label requests for PLP %1: %2 (Processed: %3, Errors: %4)',
            $plp->getId(),
            $result['message'],
            $result['processed'],
            $result['errors']
        ));
        
        return $result;
    }

    /**
     * Check if PLP needs label downloads
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldDownloadLabels($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
    }

    /**
     * Run label downloads for a PLP
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runLabelDownload($plp)
    {
        $this->logger->info(__('Running label downloads for PLP %1', $plp->getId()));
        $result = $this->plpLabelDownload->execute($plp->getId());
        
        if ($result['success']) {
            $this->processStats['label_downloads']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['label_downloads']['errors'] += $result['errors'];
        }
        
        $this->logger->info(__(
            'Label downloads for PLP %1: %2 (Processed: %3, Errors: %4)',
            $plp->getId(),
            $result['message'],
            $result['processed'],
            $result['errors']
        ));
        
        return $result;
    }

    /**
     * Check if PLP needs shipment creation
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldCreateShipments($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_AWAITING_SHIPMENT;
    }

    /**
     * Run shipment creation for a PLP
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runShipmentCreation($plp)
    {
        $this->logger->info(__('Running shipment creation for PLP %1', $plp->getId()));
        $result = $this->plpOrdShipCreator->execute($plp->getId());
        
        if ($result['success']) {
            $this->processStats['shipment_creation']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['shipment_creation']['errors'] += $result['errors'];
        }
        
        $this->logger->info(__(
            'Shipment creation for PLP %1: %2 (Processed: %3, Errors: %4)',
            $plp->getId(),
            $result['message'],
            $result['processed'],
            $result['errors']
        ));
        
        return $result;
    }

    /**
     * Log completion summary
     */
    protected function logCompletionSummary()
    {
        $this->logger->info(__('Complete PLP Process cron job Summary:'));
        $this->logger->info(__(
            'Total PLPs: %1, Completed: %2, Failed: %3',
            $this->processStats['total_plps'],
            $this->processStats['completed_plps'],
            $this->processStats['failed_plps']
        ));
        
        $this->logger->info(__(
            'Data Collection: Success: %1, Errors: %2',
            $this->processStats['data_collection']['success'],
            $this->processStats['data_collection']['errors']
        ));
        
        $this->logger->info(__(
            'Submission: Success: %1, Errors: %2',
            $this->processStats['submission']['success'],
            $this->processStats['submission']['errors']
        ));
        
        $this->logger->info(__(
            'Label Requests: Success: %1, Errors: %2',
            $this->processStats['label_requests']['success'],
            $this->processStats['label_requests']['errors']
        ));
        
        $this->logger->info(__(
            'Label Downloads: Success: %1, Errors: %2',
            $this->processStats['label_downloads']['success'],
            $this->processStats['label_downloads']['errors']
        ));
        
        $this->logger->info(__(
            'Shipment Creation: Success: %1, Errors: %2',
            $this->processStats['shipment_creation']['success'],
            $this->processStats['shipment_creation']['errors']
        ));
    }
}
