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
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

class PlpProcessingCron
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
        'processed_plps' => 0,
        'failed_plps' => 0,
        'data_collection' => ['success' => 0, 'errors' => 0],
        'submission' => ['success' => 0, 'errors' => 0],
        'label_requests' => ['success' => 0, 'errors' => 0]
    ];

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param PlpDataCollector $plpDataCollector
     * @param PlpSingleSubmit $plpSingleSubmit
     * @param PlpLabelRequest $plpLabelRequest
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpCollectionFactory $plpCollectionFactory
     */
    public function __construct(
        LoggerInterface $logger,
        PlpDataCollector $plpDataCollector,
        PlpSingleSubmit $plpSingleSubmit,
        PlpLabelRequest $plpLabelRequest,
        PlpRepositoryInterface $plpRepository,
        PlpCollectionFactory $plpCollectionFactory
    ) {
        $this->logger = $logger;
        $this->plpDataCollector = $plpDataCollector;
        $this->plpSingleSubmit = $plpSingleSubmit;
        $this->plpLabelRequest = $plpLabelRequest;
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
                    $this->logger->error(__('Error processing PPN ID %1: %2', $plp->getId(), $e->getMessage()));
                    $this->processStats['failed_plps']++;
                }
            }

        } catch (\Exception $e) {
            $this->logger->critical(__('PPN Processing cron job failed: %1', $e->getMessage()));
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
                PlpStatus::STATUS_PLP_REQUESTING_RECEIPT
            ]
        ]);

        return $collection;
    }

    /**
     * Process a single PPN through initial stages
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function processPLP($plp)
    {
        $plpId = $plp->getId();

        // Step 1: Data Collection (if needed)
        if ($this->shouldCollectData($plp)) {
            $result = $this->runDataCollection($plp);
            if (!$result['success']) {
                return false;
            }

            $plp = $this->plpRepository->getById($plpId);
        }

        // Step 2: Submission (if needed)
        if ($this->shouldSubmit($plp)) {
            $result = $this->runSubmission($plp);
            if (!$result['success']) {
                return false;
            }

            $plp = $this->plpRepository->getById($plpId);
        }

        // Step 3: Label Request (if needed)
        if ($this->shouldRequestLabels($plp)) {
            $result = $this->runLabelRequest($plp);
            if (!$result['success']) {
                return false;
            }

            $this->processStats['processed_plps']++;
        }

        return true;
    }

    /**
     * Check if PPN needs data collection
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldCollectData($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_OPENED;
    }

    /**
     * Run data collection for a PPN
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runDataCollection($plp)
    {
        $result = $this->plpDataCollector->execute($plp->getId());

        if ($result['success']) {
            $this->processStats['data_collection']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['data_collection']['errors'] += $result['errors'];
        }

        return $result;
    }

    /**
     * Check if PPN needs submission
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldSubmit($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_COLLECTING_DATA;
    }

    /**
     * Run submission for a PPN
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runSubmission($plp)
    {
        $result = $this->plpSingleSubmit->execute($plp->getId());

        if ($result['success']) {
            $this->processStats['submission']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['submission']['errors'] += $result['errors'];
        }

        return $result;
    }

    /**
     * Check if PPN needs label requests
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return bool
     */
    protected function shouldRequestLabels($plp)
    {
        return $plp->getStatus() === PlpStatus::STATUS_PLP_REQUESTING_RECEIPT;
    }

    /**
     * Run label requests for a PPN
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return array
     */
    protected function runLabelRequest($plp)
    {
        $result = $this->plpLabelRequest->execute($plp->getId());

        if ($result['success']) {
            $this->processStats['label_requests']['success'] += $result['processed'];
        }

        if ($result['errors']) {
            $this->processStats['label_requests']['errors'] += $result['errors'];
        }

        return $result;
    }
}
