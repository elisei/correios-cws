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
 * Detach orders from and delete PPNs created more than N days ago that never reached "completed".
 */
class DeleteStalePlps
{
    private const PLP_TABLE = 'sales_shipment_correios_plp';
    private const PLP_ORDER_TABLE = 'sales_shipment_correios_plp_order';
    private const STALE_DAYS = 3;
    private const COMPLETED_STATUS = 'completed';

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
        $stalePlpIds = $this->findStalePlpIds();

        if (empty($stalePlpIds)) {
            return;
        }

        $deleted = 0;
        $detached = 0;

        foreach ($stalePlpIds as $plpId) {
            try {
                foreach ($this->getOrderIdsForPlp($plpId) as $orderId) {
                    try {
                        $this->plpRepository->removeOrderFromPlp($plpId, $orderId);
                        $detached++;
                    } catch (\Exception $e) {
                        $this->logger->warning(sprintf(
                            'DeleteStalePlps: failed to detach order %s from PPN %d: %s',
                            $orderId,
                            $plpId,
                            $e->getMessage()
                        ));
                    }
                }

                $this->plpRepository->deleteById($plpId);
                $deleted++;
            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    'DeleteStalePlps: skipped PPN %d: %s',
                    $plpId,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf(
            'DeleteStalePlps: detached %d order(s), deleted %d PPN(s) out of %d candidate(s).',
            $detached,
            $deleted,
            count($stalePlpIds)
        ));
    }

    /**
     * Find PPN ids older than the stale threshold and not completed.
     *
     * @return array<int>
     */
    private function findStalePlpIds(): array
    {
        $threshold = (new \DateTime())
            ->modify('-' . self::STALE_DAYS . ' days')
            ->format('Y-m-d H:i:s');

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['p' => $this->resource->getTableName(self::PLP_TABLE)], ['entity_id'])
            ->where('p.created_at < ?', $threshold)
            ->where('p.status <> ?', self::COMPLETED_STATUS);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Get the sales order ids associated with a PPN.
     *
     * @param int $plpId
     * @return array
     */
    private function getOrderIdsForPlp(int $plpId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::PLP_ORDER_TABLE), ['order_id'])
            ->where('plp_id = ?', $plpId);

        return $connection->fetchCol($select);
    }
}
