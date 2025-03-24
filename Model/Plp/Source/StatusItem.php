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

class StatusItem implements OptionSourceInterface
{
    /**
     * Status constants
     */
    public const STATUS_ITEM_ERROR = 'error';
    public const STATUS_ITEM_PENDING_COLLECTION = 'pending_collection';
    public const STATUS_ITEM_PROCESSING_COLLECTION = 'processing_collection';
    public const STATUS_ITEM_COLLECTION_COMPLETED = 'collection_completed';
    public const STATUS_ITEM_PENDING_SUBMIT = 'pending_submit';
    public const STATUS_ITEM_PROCESSING_SUBMIT = 'processing_submit';
    public const STATUS_ITEM_SUBMIT_CREATED = 'submit_created';
    public const STATUS_ITEM_SUBMIT_ERROR = 'submit_error';
    public const STATUS_ITEM_PENDING_REQUEST_LABELS = 'pending_request_labels';
    public const STATUS_ITEM_PROCESSING_REQUEST_LABELS = 'processing_request_labels';
    public const STATUS_ITEM_RECEIPT_CREATED = 'receipt_created';
    public const STATUS_ITEM_RECEIPT_ERROR = 'receipt_creation_error';
    public const STATUS_ITEM_PENDING_DOWNLOAD = 'pending_download';
    public const STATUS_ITEM_PROCESSING_DOWNLOAD = 'processing_download';
    public const STATUS_ITEM_DOWNLOAD_COMPLETED = 'download_completed';
    public const STATUS_ITEM_DOWNLOAD_ERROR = 'download_error';
    public const STATUS_ITEM_PENDING_SHIP_CREATE = 'pending_ship_create';
    public const STATUS_ITEM_PROCESSING_SHIP_CREATE = 'processing_ship_create';
    public const STATUS_ITEM_SHIP_CREATED = 'ship_created';
    public const STATUS_ITEM_SHIP_CREATE_ERROR = 'ship_create_error';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::STATUS_ITEM_ERROR, 'label' => __('Error')],
            ['value' => self::STATUS_ITEM_PENDING_COLLECTION, 'label' => __('Pending Collection')],
            ['value' => self::STATUS_ITEM_PROCESSING_COLLECTION, 'label' => __('Processing Collection')],
            ['value' => self::STATUS_ITEM_COLLECTION_COMPLETED, 'label' => __('Collection Completed')],
            ['value' => self::STATUS_ITEM_PENDING_SUBMIT, 'label' => __('Pending Submit')],
            ['value' => self::STATUS_ITEM_PROCESSING_SUBMIT, 'label' => __('Processing Submit')],
            ['value' => self::STATUS_ITEM_SUBMIT_CREATED, 'label' => __('Submit Created')],
            ['value' => self::STATUS_ITEM_SUBMIT_ERROR, 'label' => __('Submit Error')],
            ['value' => self::STATUS_ITEM_PENDING_REQUEST_LABELS, 'label' => __('Pending Request Labels')],
            ['value' => self::STATUS_ITEM_PROCESSING_REQUEST_LABELS, 'label' => __('Processing Request Labels')],
            ['value' => self::STATUS_ITEM_RECEIPT_CREATED, 'label' => __('Receipt Created')],
            ['value' => self::STATUS_ITEM_RECEIPT_ERROR, 'label' => __('Receipt Creation Error')],
            ['value' => self::STATUS_ITEM_PENDING_DOWNLOAD, 'label' => __('Pending Download')],
            ['value' => self::STATUS_ITEM_PROCESSING_DOWNLOAD, 'label' => __('Processing Download')],
            ['value' => self::STATUS_ITEM_DOWNLOAD_COMPLETED, 'label' => __('Download Completed')],
            ['value' => self::STATUS_ITEM_DOWNLOAD_ERROR, 'label' => __('Download Error')],
            ['value' => self::STATUS_ITEM_PENDING_SHIP_CREATE, 'label' => __('Pending Ship Create')],
            ['value' => self::STATUS_ITEM_PROCESSING_SHIP_CREATE, 'label' => __('Processing Ship Create')],
            ['value' => self::STATUS_ITEM_SHIP_CREATED, 'label' => __('Ship Created')],
            ['value' => self::STATUS_ITEM_SHIP_CREATE_ERROR, 'label' => __('Ship Create Error')],
        ];
    }
}
