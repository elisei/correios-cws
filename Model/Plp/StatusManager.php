<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Plp;

use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

class StatusManager
{
    /**
     * @var PlpStatus
     */
    private $status;

    /**
     * @param PlpStatus $status
     */
    public function __construct(
        PlpStatus $status
    ) {
        $this->status = $status;
    }

    /**
     * Get status transitions map
     *
     * @return array
     */
    public function getStatusTransitionMap()
    {
        return [
            PlpStatus::STATUS_OPEN => [
                PlpStatus::STATUS_COLLECTING
            ],
            PlpStatus::STATUS_COLLECTING => [
                PlpStatus::STATUS_FORMED
            ],
            PlpStatus::STATUS_FORMED => [
                PlpStatus::STATUS_PROCESSING
            ],
            PlpStatus::STATUS_PROCESSING => [
                PlpStatus::STATUS_PROCESSED
            ],
            PlpStatus::STATUS_PROCESSED => [
                PlpStatus::STATUS_CREATING_SHIPMENT
            ],
            PlpStatus::STATUS_CREATING_SHIPMENT => [
                PlpStatus::STATUS_CLOSED
            ],
            PlpStatus::STATUS_CLOSED => []
        ];
    }

    /**
     * Check if status transition is valid
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    public function isValidTransition($currentStatus, $newStatus)
    {
        $transitionMap = $this->getStatusTransitionMap();
        return isset($transitionMap[$currentStatus]) && 
               in_array($newStatus, $transitionMap[$currentStatus]);
    }

    /**
     * Get flags for status
     *
     * @param string $status
     * @return array
     */
    public function getStatusFlags($status)
    {
        return [
            'can_add_orders' => $this->status->canAddOrders($status),
            'can_remove_orders' => $this->status->canRemoveOrders($status),
            'can_request_closing' => $this->status->canRequestClosing($status)
        ];
    }

    /**
     * Update PLP flags based on status
     *
     * @param \O2TI\SigepWebCarrier\Model\Plp $plp
     * @return void
     */
    public function updatePlpFlags($plp)
    {
        $flags = $this->getStatusFlags($plp->getStatus());
        
        $plp->setData('can_add_orders', $flags['can_add_orders']);
        $plp->setData('can_remove_orders', $flags['can_remove_orders']);
        $plp->setData('can_request_closing', $flags['can_request_closing']);
    }
}
