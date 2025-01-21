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

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use O2TI\SigepWebCarrier\Model\TrackingStatus;
use Psr\Log\LoggerInterface;

/**
 * Class UpdateTrackingStatus
 * Responsible for updating tracking status of completed orders based on specific status
 */
class UpdateTrackingStatus
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var TrackingStatus
     */
    protected $trackingStatus;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $statusToProcess;

    /**
     * Constructor.
     *
     * @param CollectionFactory $collectionFactory Order collection factory
     * @param TrackingStatus $trackingStatus Tracking status processor
     * @param LoggerInterface $logger Logger interface
     * @param string $statusToProcess Status to be processed
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        TrackingStatus $trackingStatus,
        LoggerInterface $logger,
        string $statusToProcess
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->trackingStatus = $trackingStatus;
        $this->logger = $logger;
        $this->statusToProcess = $statusToProcess;
    }

    /**
     * Execute the tracking status update process for specific status
     *
     * @return void
     */
    public function execute()
    {
        try {
            $orders = $this->collectionFactory->create()
                ->addFieldToFilter('state', Order::STATE_COMPLETE)
                ->addFieldToFilter('status', $this->statusToProcess);

            foreach ($orders as $order) {
                try {
                    $this->trackingStatus->processOrder($order);
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Erro ao processar pedido %s com status %s: %s',
                        $order->getIncrementId(),
                        $this->statusToProcess,
                        $e->getMessage()
                    ));
                    continue;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Erro no cron de atualização de status %s: %s',
                $this->statusToProcess,
                $e->getMessage()
            ));
        }
    }
}
