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
use O2TI\SigepWebCarrier\Model\Plp\PlpLabelDownload;
use O2TI\SigepWebCarrier\Model\Plp\PlpOrderShipmentCreator;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

class PlpFinalizationCron
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

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
        'label_downloads' => ['success' => 0, 'errors' => 0],
        'shipment_creation' => ['success' => 0, 'errors' => 0]
    ];

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param PlpLabelDownload $plpLabelDownload
     * @param PlpOrderShipmentCreator $plpOrdShipCreator
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpCollectionFactory $plpCollectionFactory
     */
    public function __construct(
        LoggerInterface $logger,
        PlpLabelDownload $plpLabelDownload,
        PlpOrderShipmentCreator $plpOrdShipCreator,
        PlpRepositoryInterface $plpRepository,
        PlpCollectionFactory $plpCollectionFactory
    ) {
        $this->logger = $logger;
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
        $this->logger->info(__('Starting PPN Finalization cron job'));
        
        try {
            $plps = $this->getEligiblePlps();
            
            if ($plps->getSize() === 0) {
                $this->logger->info(__('No eligible PLPs found for finalization'));
                return;
            }
            
            $this->processStats['total_plps'] = $plps->getSize();
            
            foreach ($plps as $plp) {
                try {
                    if (!$plp->getCanSendToCws()) {
                        $this->logger->info(__('PPN ID %1 is not enabled for finalization, skipping', $plp->getId()));
                        continue;
                    }
                    
                    $this->processPLP($plp);
                    
                } catch (\Exception $e) {
                    $this->logger->error(__('Error finalizing PPN ID %1: %2', $plp->getId(), $e->getMessage()));
                    $this->processStats['failed_plps']++;
                }
            }
            
            $this->logCompletionSummary();
            
        } catch (\Exception $e) {
            $this->logger->critical(__('PPN Finalization cron job failed: %1', $e->getMessage()));
        }
    }

    /**
     * Get eligible PLPs for finalization
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    protected function getEligiblePlps()
    {
        $collection = $this->plpCollectionFactory->create();
        $collection->addFieldToFilter('status', [
            'in' => [
                PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION,
                PlpStatus::STATUS_PLP_AWAITING_SHIPMENT
            ]
        ]);
        
        return $collection;
    }

    /**
     * Process a single PPN through finalization stages
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function processPLP($plp)
    {
        $plpId = $plp->getId();
        $this->logger->info(
            __('Beginning finalization for PPN ID: %1 [Current Status: %2]', $plpId, $plp->getStatus())
        );
        
        // Step 1: Label Download (if needed)
        if ($this->shouldDownloadLabels($plp)) {
            $result = $this->runLabelDownload($plp);
            if (!$result['success']) {
                return false;
            }
            
            $plp = $this->plpRepository->getById($plpId);
        }
        
        // Step 2: Shipment Creation (if needed)
        if ($this->shouldCreateShipments($plp)) {
            $result = $this->runShipmentCreation($plp);
            if (!$result['success']) {
                return false;
            }
            
            $plp = $this->plpRepository->getById($plpId);
            if ($plp->getStatus() === PlpStatus::STATUS_PLP_COMPLETED) {
                $this->processStats['completed_plps']++;
                $this->logger->info(__('PPN ID %1 completed successfully', $plpId));
            }
        }
        
        return true;
    }

    /**
     * Check if PPN needs label downloads
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldDownloadLabels($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
    }

    /**
     * Run label downloads for a PPN
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runLabelDownload($plp)
    {
        $this->logger->info(__('Running label downloads for PPN %1', $plp->getId()));
        $result = $this->plpLabelDownload->execute($plp->getId());
        
        if ($result['success']) {
            $this->processStats['label_downloads']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['label_downloads']['errors'] += $result['errors'];
        }
        
        $this->logger->info(__(
            'Label downloads for PPN %1: %2 (Processed: %3, Errors: %4)',
            $plp->getId(),
            $result['message'],
            $result['processed'],
            $result['errors']
        ));
        
        return $result;
    }

    /**
     * Check if PPN needs shipment creation
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldCreateShipments($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_AWAITING_SHIPMENT;
    }

    /**
     * Run shipment creation for a PPN
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runShipmentCreation($plp)
    {
        $this->logger->info(__('Running shipment creation for PPN %1', $plp->getId()));
        $result = $this->plpOrdShipCreator->execute($plp->getId());
        
        if ($result['success']) {
            $this->processStats['shipment_creation']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['shipment_creation']['errors'] += $result['errors'];
        }
        
        $this->logger->info(__(
            'Shipment creation for PPN %1: %2 (Processed: %3, Errors: %4)',
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
        $this->logger->info(__('PPN Finalization cron job Summary:'));
        $this->logger->info(__(
            'Total PLPs: %1, Completed: %2, Failed: %3',
            $this->processStats['total_plps'],
            $this->processStats['completed_plps'],
            $this->processStats['failed_plps']
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