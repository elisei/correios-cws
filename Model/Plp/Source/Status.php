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
    public const STATUS_PLP_OPENED = 'opened';
    public const STATUS_PLP_COLLECTING_DATA = 'collecting_data';
    public const STATUS_PLP_IN_COMMUNICATION = 'in_communication';
    public const STATUS_PLP_REQUESTING_RECEIPT = 'requesting_receipt';
    public const STATUS_PLP_REQUESTING_FILE_CREATION = 'requesting_file_creation';
    public const STATUS_PLP_REQUESTING_SHIPMENT_CREATION = 'requesting_shipment_creation';
    public const STATUS_PLP_AWAITING_SHIPMENT = 'awaiting_shipment';
    public const STATUS_PLP_COMPLETED = 'completed';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::STATUS_PLP_OPENED, 'label' => __('Opened')],
            ['value' => self::STATUS_PLP_COLLECTING_DATA, 'label' => __('Collecting Data')],
            ['value' => self::STATUS_PLP_IN_COMMUNICATION, 'label' => __('In Communication')],
            ['value' => self::STATUS_PLP_REQUESTING_RECEIPT, 'label' => __('Requesting Receipt')],
            ['value' => self::STATUS_PLP_REQUESTING_FILE_CREATION, 'label' => __('Requesting File Creation')],
            ['value' => self::STATUS_PLP_REQUESTING_SHIPMENT_CREATION, 'label' => __('Requesting Shipment Creation')],
            ['value' => self::STATUS_PLP_AWAITING_SHIPMENT, 'label' => __('Awaiting Shipment Creation')],
            ['value' => self::STATUS_PLP_COMPLETED, 'label' => __('Completed')]
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
            self::STATUS_PLP_OPENED => [
                'can_add_orders' => true,
                'can_send_to_cws' => true,
            ]
        ];

        return $permissions[$status] ?? [
            'can_add_orders' => false,
            'can_send_to_cws' => false,
        ];
    }
}
