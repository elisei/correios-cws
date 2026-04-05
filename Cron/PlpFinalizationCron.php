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
use O2TI\SigepWebCarrier\Model\Plp\PlpDaceDownload;
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
     * @var PlpDaceDownload
     */
    protected $plpDaceDownload;

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
        'dace_downloads' => ['success' => 0, 'errors' => 0],
        'shipment_creation' => ['success' => 0, 'errors' => 0]
    ];

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param PlpLabelDownload $plpLabelDownload
     * @param PlpDaceDownload $plpDaceDownload
     * @param PlpOrderShipmentCreator $plpOrdShipCreator
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpCollectionFactory $plpCollectionFactory
     */
    public function __construct(
        LoggerInterface $logger,
        PlpLabelDownload $plpLabelDownload,
        PlpDaceDownload $plpDaceDownload,
        PlpOrderShipmentCreator $plpOrdShipCreator,
        PlpRepositoryInterface $plpRepository,
        PlpCollectionFactory $plpCollectionFactory
    ) {
        $this->logger = $logger;
        $this->plpLabelDownload = $plpLabelDownload;
        $this->plpDaceDownload = $plpDaceDownload;
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
        try {
            $plps = $this->getEligiblePlps();

            if ($plps->getSize() === 0) {
                return;
            }

            $this->processStats['total_plps'] = $plps->getSize();

            foreach ($plps as $plp) {
                try {
                    if (!$plp->getCanSendToCws()) {
                        continue;
                    }

                    $this->processPLP($plp);

                } catch (\Exception $e) {
                    $this->logger->error(__('Error finalizing PPN ID %1: %2', $plp->getId(), $e->getMessage()));
                    $this->processStats['failed_plps']++;
                }
            }

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
                PlpStatus::STATUS_PLP_AWAITING_DACE,
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

        // Step 1: DACE Download (if DC-e enabled, runs before label)
        if ($this->shouldDownloadDace($plp)) {
            $result = $this->runDaceDownload($plp);
            if (!$result['success']) {
                return false;
            }

            $plp = $this->plpRepository->getById($plpId);
        }

        // Step 2: Label Download (if needed)
        if ($this->shouldDownloadLabels($plp)) {
            $result = $this->runLabelDownload($plp);
            if (!$result['success']) {
                return false;
            }

            $plp = $this->plpRepository->getById($plpId);
        }

        // Step 3: Shipment Creation (if needed)
        if ($this->shouldCreateShipments($plp)) {
            $result = $this->runShipmentCreation($plp);
            if (!$result['success']) {
                return false;
            }

            $plp = $this->plpRepository->getById($plpId);
            if ($plp->getStatus() === PlpStatus::STATUS_PLP_COMPLETED) {
                $this->processStats['completed_plps']++;
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
        return in_array($plp->getStatus(), [
            PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION,
            PlpStatus::STATUS_PLP_AWAITING_DACE
        ]);
    }

    /**
     * Run label downloads for a PPN
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runLabelDownload($plp)
    {
        $result = $this->plpLabelDownload->execute($plp->getId());

        if ($result['success']) {
            $this->processStats['label_downloads']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['label_downloads']['errors'] += $result['errors'];
        }

        return $result;
    }

    /**
     * Check if PPN needs DACE downloads
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldDownloadDace($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
    }

    /**
     * Run DACE downloads for a PPN
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runDaceDownload($plp)
    {
        $result = $this->plpDaceDownload->execute($plp->getId());

        if ($result['success']) {
            $this->processStats['dace_downloads']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['dace_downloads']['errors'] += $result['errors'];
        }

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
        $result = $this->plpOrdShipCreator->execute($plp->getId());

        if ($result['success']) {
            $this->processStats['shipment_creation']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['shipment_creation']['errors'] += $result['errors'];
        }

        return $result;
    }
}
