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
     * PLP Status Constants
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
     * Check if can add orders
     *
     * @param string $status
     * @return bool
     */
    public function canAddOrders($status)
    {
        return $status === self::STATUS_OPEN;
    }

    /**
     * Check if can remove orders
     *
     * @param string $status
     * @return bool
     */
    public function canRemoveOrders($status)
    {
        return in_array($status, [self::STATUS_OPEN, self::STATUS_CLOSED]);
    }

    /**
     * Check if can request closing
     *
     * @param string $status
     * @return bool
     */
    public function canRequestClosing($status)
    {
        return !in_array($status, [self::STATUS_COLLECTING, self::STATUS_PROCESSING, 
            self::STATUS_CREATING_SHIPMENT, self::STATUS_CLOSED]);
    }
}
