<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Plp\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * Status constants
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_COLLECTING = 'collecting';
    public const STATUS_FORMED = 'formed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_CREATING_SHIPMENT = 'creating_shipment';
    public const STATUS_CLOSED = 'closed';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::STATUS_OPEN, 'label' => __('Open')],
            ['value' => self::STATUS_COLLECTING, 'label' => __('Collecting')],
            ['value' => self::STATUS_FORMED, 'label' => __('Formed')],
            ['value' => self::STATUS_PROCESSING, 'label' => __('Processing')],
            ['value' => self::STATUS_PROCESSED, 'label' => __('Processed')],
            ['value' => self::STATUS_CREATING_SHIPMENT, 'label' => __('Creating Shipment')],
            ['value' => self::STATUS_CLOSED, 'label' => __('Closed')]
        ];
    }

    /**
     * Get action permissions by status
     *
     * @param string $status
     * @return array
     */
    public function getActionPermissions($status)
    {
        $permissions = [
            self::STATUS_OPEN => [
                'can_add_orders' => true,
                'can_remove_orders' => true,
                'can_close' => false
            ],
            self::STATUS_COLLECTING => [
                'can_add_orders' => false,
                'can_remove_orders' => false,
                'can_close' => false
            ],
            self::STATUS_FORMED => [
                'can_add_orders' => false,
                'can_remove_orders' => false,
                'can_close' => true
            ],
            self::STATUS_PROCESSING => [
                'can_add_orders' => false,
                'can_remove_orders' => false,
                'can_close' => false
            ],
            self::STATUS_PROCESSED => [
                'can_add_orders' => false,
                'can_remove_orders' => false,
                'can_close' => false
            ],
            self::STATUS_CREATING_SHIPMENT => [
                'can_add_orders' => false,
                'can_remove_orders' => false,
                'can_close' => false
            ],
            self::STATUS_CLOSED => [
                'can_add_orders' => false,
                'can_remove_orders' => true,
                'can_close' => false
            ]
        ];

        return $permissions[$status] ?? [
            'can_add_orders' => false,
            'can_remove_orders' => false,
            'can_close' => false
        ];
    }
}
