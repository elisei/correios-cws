<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;

class AddOrderStatuses implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();

        $requiredStatuses = [
            'sigewep_in_transit' => [
                'label' => 'Correios In Transit',
                'state' => Order::STATE_COMPLETE,
                'is_default' => 0,
                'visible_on_front' => 1
            ],
            'sigewep_on_delivery_route' => [
                'label' => 'Correios On Delivery Route',
                'state' => Order::STATE_COMPLETE,
                'is_default' => 0,
                'visible_on_front' => 1
            ],
            'sigewep_delivered' => [
                'label' => 'Correios Delivered',
                'state' => Order::STATE_COMPLETE,
                'is_default' => 0,
                'visible_on_front' => 1
            ],
            'sigewep_created' => [
                'label' => 'Correios Created',
                'state' => Order::STATE_COMPLETE,
                'is_default' => 0,
                'visible_on_front' => 1
            ],
            'sigewep_delivery_failed' => [
                'label' => 'Correios Delivery Failed',
                'state' => Order::STATE_COMPLETE,
                'is_default' => 0,
                'visible_on_front' => 1
            ]
        ];

        $statusTable = $this->moduleDataSetup->getTable('sales_order_status');
        $statusStateTable = $this->moduleDataSetup->getTable('sales_order_status_state');

        foreach ($requiredStatuses as $statusCode => $statusInfo) {
            $statusExists = $connection->fetchOne(
                $connection->select()
                    ->from($statusTable, ['COUNT(*)'])
                    ->where('status = ?', $statusCode)
            );

            if (!$statusExists) {
                $connection->insert(
                    $statusTable,
                    [
                        'status' => $statusCode,
                        'label' => $statusInfo['label']
                    ]
                );
            }

            $stateExists = $connection->fetchOne(
                $connection->select()
                    ->from($statusStateTable, ['COUNT(*)'])
                    ->where('status = ?', $statusCode)
                    ->where('state = ?', $statusInfo['state'])
            );

            if (!$stateExists) {
                $connection->insert(
                    $statusStateTable,
                    [
                        'status' => $statusCode,
                        'state' => $statusInfo['state'],
                        'is_default' => $statusInfo['is_default'],
                        'visible_on_front' => $statusInfo['visible_on_front']
                    ]
                );
            }
        }

        $this->moduleDataSetup->endSetup();
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
