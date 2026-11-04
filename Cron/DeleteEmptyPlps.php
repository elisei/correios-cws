<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

declare(strict_types=1);

namespace O2TI\SigepWebCarrier\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;

/**
 * Delete PPNs that have no orders attached.
 */
class DeleteEmptyPlps
{
    private const PLP_TABLE = 'sales_shipment_correios_plp';
    private const PLP_ORDER_TABLE = 'sales_shipment_correios_plp_order';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var PlpRepositoryInterface
     */
    private $plpRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ResourceConnection $resource
     * @param PlpRepositoryInterface $plpRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resource,
        PlpRepositoryInterface $plpRepository,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->plpRepository = $plpRepository;
        $this->logger = $logger;
    }

    /**
     * Execute cron job.
     *
     * @return void
     */
    public function execute(): void
    {
        $emptyPlpIds = $this->findEmptyPlpIds();

        if (empty($emptyPlpIds)) {
            return;
        }

        $deleted = 0;
        foreach ($emptyPlpIds as $plpId) {
            try {
                $this->plpRepository->deleteById((int) $plpId);
                $deleted++;
            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    'DeleteEmptyPlps: skipped PPN %d: %s',
                    $plpId,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf(
            'DeleteEmptyPlps: removed %d empty PPN(s) out of %d candidate(s).',
            $deleted,
            count($emptyPlpIds)
        ));
    }

    /**
     * Find PPN ids that have no rows in the order table.
     *
     * @return array<int>
     */
    private function findEmptyPlpIds(): array
    {
        $connection = $this->resource->getConnection();
        $plpTable = $this->resource->getTableName(self::PLP_TABLE);
        $plpOrderTable = $this->resource->getTableName(self::PLP_ORDER_TABLE);

        $select = $connection->select()
            ->from(['p' => $plpTable], ['entity_id'])
            ->joinLeft(
                ['po' => $plpOrderTable],
                'po.plp_id = p.entity_id',
                []
            )
            ->where('po.entity_id IS NULL');

        return array_map('intval', $connection->fetchCol($select));
    }
}
