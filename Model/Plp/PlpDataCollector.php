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

use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\Plp\Source\StatusItem as PlpStatusItem;
use O2TI\SigepWebCarrier\Model\Plp\SigepWebDataFormatter;
use O2TI\SigepWebCarrier\Model\Plp\StoreInformation;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlpDataCollector extends AbstractPlpOperation
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var SigepWebDataFormatter
     */
    protected $sigepDataFormatter;
    
    /**
     * @var StoreInformation
     */
    protected $storeInformation;
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Json $json
     * @param PlpRepositoryInterface $plpRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param PlpOrderCollectionFactory $plpOrdCollection
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param SigepWebDataFormatter $sigepDataFormatter
     * @param StoreInformation $storeInformation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        LoggerInterface $logger,
        Json $json,
        PlpRepositoryInterface $plpRepository,
        OrderRepositoryInterface $orderRepository,
        PlpOrderCollectionFactory $plpOrdCollection,
        PlpCollectionFactory $plpCollectionFactory,
        SigepWebDataFormatter $sigepDataFormatter,
        StoreInformation $storeInformation,
        StoreManagerInterface $storeManager = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->sigepDataFormatter = $sigepDataFormatter;
        $this->storeInformation = $storeInformation;
        $this->storeManager = $storeManager;
        
        parent::__construct(
            $logger,
            $plpRepository,
            $json,
            $plpOrdCollection,
            $plpCollectionFactory
        );
    }
    
    /**
     * Initialize configuration
     */
    protected function initialize()
    {
        $this->operationName = 'PLP data collection';
        
        // Define PLP statuses
        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_OPENED;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_OPENED;
        
        // Define order statuses
        $this->expectedTypeFilter = 'status';
        $this->expectedOrderStatus = [PlpStatusItem::STATUS_ITEM_PENDING_COLLECTION];
        $this->inProgressOrdStatus = PlpStatusItem::STATUS_ITEM_PROCESSING_COLLECTION;
        $this->successOrderStatus = PlpStatusItem::STATUS_ITEM_COLLECTION_COMPLETED;
        $this->failureOrderStatus = PlpStatusItem::STATUS_ITEM_ERROR;
    }

    /**
     * Get message for when no eligible orders are found
     *
     * @param int $plpId
     * @return \Magento\Framework\Phrase
     */
    protected function getNoOrdersMessage($plpId)
    {
        return __('No pending orders found for processing in PLP %1', $plpId);
    }

    /**
     * Process individual PLP order
     *
     * @param object $plpOrder
     * @param array $result
     * @return bool
     */
    protected function processPlpOrder($plpOrder, &$result)
    {
        try {
            $orderId = $plpOrder->getOrderId();
            $collectedData = $this->collectOrderData($orderId);
            
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->successOrderStatus,
                null,
                $collectedData
            );
            
            return true;
        } catch (LocalizedException $exc) {
            $this->logger->error(__(
                'Error collecting data for order %1 in PLP %2: %3',
                $plpOrder->getOrderId(),
                $plpOrder->getPlpId(),
                $exc->getMessage()
            ));
            
            $plpOrder->setErrorMessage($exc->getMessage());
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->failureOrderStatus
            );
            
            return false;
        }
    }
    
    /**
     * Update final PLP status based on processing results
     *
     * @param object $plp
     * @param int $successCount
     * @param int $errorCount
     */
    protected function updateFinalPlpStatus($plp, $successCount, $errorCount)
    {
        $allProcessed = $this->checkAllOrdersProcessed($plp->getId());
        
        if ($allProcessed && $errorCount === 0) {
            $plp->setStatus($this->successPlpStatus);
        } elseif ($errorCount) {
            $plp->setStatus($this->failurePlpStatus);
        }
        
        $this->plpRepository->save($plp);
    }

    /**
     * Collect data from order
     *
     * @param string $orderId
     * @return array
     */
    protected function collectOrderData($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        
        $shippingAddress = $order->getShippingAddress();
        
        if (!$shippingAddress) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new LocalizedException(__('Shipping address not found for order %1', $orderId));
        }

        $collectedData = [
            'order_info' => [
                'order_id' => $order->getEntityId(),
                'subtotal' => $order->getSubtotal(),
                'increment_id' => $order->getIncrementId(),
                'created_at' => $order->getCreatedAt(),
                'customer_email' => $order->getCustomerEmail(),
                'customer_firstname' => $order->getCustomerFirstname(),
                'customer_lastname' => $order->getCustomerLastname(),
                'shipping_method' => $order->getShippingMethod(),
                'shipping_description' => $order->getShippingDescription(),
                'total_weight' => $order->getWeight() ?: $this->calculateTotalWeight($order),
            ],
            'shipping_address' => [
                'firstname' => $shippingAddress->getFirstname(),
                'lastname' => $shippingAddress->getLastname(),
                'company' => $shippingAddress->getCompany(),
                'street' => $shippingAddress->getStreet(),
                'city' => $shippingAddress->getCity(),
                'region' => $shippingAddress->getRegion(),
                'region_code' => $shippingAddress->getRegionCode(),
                'postcode' => $shippingAddress->getPostcode(),
                'country_id' => $shippingAddress->getCountryId(),
                'telephone' => $shippingAddress->getTelephone(),
                'vat_id' => $shippingAddress->getVatId(),
            ],
            'items' => []
        ];

        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            
            $collectedData['items'][] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty' => $item->getQtyOrdered(),
                'weight' => $item->getWeight(),
                'price' => $item->getPrice(),
                'row_total' => $item->getRowTotal()
            ];
        }

        $senderData = $this->storeInformation->getSenderData();
        $collectedData = $this->sigepDataFormatter->formatOrderData(
            $collectedData,
            $senderData
        );

        return $collectedData;
    }

    /**
     * Calculate total weight from items if order weight is not set
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return float
     */
    protected function calculateTotalWeight($order)
    {
        $weight = 0;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            $weight += ($item->getWeight() * $item->getQtyOrdered());
        }
        
        return $weight ?: 0.5; // Default minimal weight if no weight is set
    }

    /**
     * Check if all orders in a PLP have been processed
     *
     * @param int $plpId
     * @return bool
     */
    protected function checkAllOrdersProcessed($plpId)
    {
        $collection = $this->plpOrdCollection->create();
        $collection->addFieldToFilter('plp_id', $plpId);
        $collection->addFieldToFilter('status', ['eq' => PlpStatusItem::STATUS_ITEM_PENDING_COLLECTION]);
        
        return ($collection->getSize() === 0);
    }

    /**
     * Get open PLPs with pending orders
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    public function getOpenPlpsWithPendingOrders()
    {
        return $this->getPlpsByStatus(PlpStatus::STATUS_PLP_OPENED);
    }
}
