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
use O2TI\SigepWebCarrier\Model\PlpDataCollector;
use Magento\Framework\App\Config\ScopeConfigInterface;

class PlpDataCollectCron
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
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param PlpDataCollector $plpDataCollector
     */
    public function __construct(
        LoggerInterface $logger,
        PlpDataCollector $plpDataCollector
    ) {
        $this->logger = $logger;
        $this->plpDataCollector = $plpDataCollector;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info('Starting PLP data collection cron job');
        
        try {
            $plps = $this->plpDataCollector->getOpenPlpsWithPendingOrders();
            
            if ($plps->getSize() === 0) {
                $this->logger->info('No open PLPs with pending orders found');
                return;
            }
            
            $totalProcessed = 0;
            $totalErrors = 0;
            
            foreach ($plps as $plp) {
                $this->logger->info(sprintf('Processing PLP ID: %s', $plp->getId()));
                
                $result = $this->plpDataCollector->execute($plp->getId());
                
                $totalProcessed += $result['processed'];
                $totalErrors += $result['errors'];
                
                if ($result['success']) {
                    $this->logger->info(
                        sprintf(
                            'PLP %s: %s. Processed %d orders with %d errors.',
                            $plp->getId(),
                            $result['message'],
                            $result['processed'],
                            $result['errors']
                        )
                    );
                } else {
                    $this->logger->error(
                        sprintf(
                            'PLP %s: %s',
                            $plp->getId(),
                            $result['message']
                        )
                    );
                }
            }
            
            $this->logger->info(
                sprintf(
                    'PLP data collection completed. Processed %d orders with %d errors across all PLPs.',
                    $totalProcessed,
                    $totalErrors
                )
            );
            
        } catch (\Exception $e) {
            $this->logger->critical('PLP data collection cron job failed: ' . $e->getMessage());
        }
    }
}
