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

use O2TI\SigepWebCarrier\Model\FallbackServiceUpdater;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class UpdateFallbackRules
{
    /**
     * @var FallbackServiceUpdater
     */
    private $fallbackUpdater;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param FallbackServiceUpdater $fallbackUpdater
     * @param WriterInterface $configWriter
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        FallbackServiceUpdater $fallbackUpdater,
        WriterInterface $configWriter,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->fallbackUpdater = $fallbackUpdater;
        $this->configWriter = $configWriter;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        try {
            $this->logger->info('Starting monthly fallback rules update cron');
            
            $updatedRules = $this->fallbackUpdater->updateServiceRules();
            
            $this->configWriter->save(
                'carriers/sigep_web_carrier/fallback/service_rules',
                $this->json->serialize($updatedRules)
            );
            
            $this->logger->info('Monthly fallback rules update completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Error in fallback rules update cron: ' . $e->getMessage());
        }
    }
}
