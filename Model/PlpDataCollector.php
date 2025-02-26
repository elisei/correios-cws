<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model;

use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\SigepWebDataFormatter;
use O2TI\SigepWebCarrier\Model\Config\Source\StoreInformation;

class PlpDataCollector
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var PlpCollectionFactory
     */
    protected $plpCollectionFactory;

    /**
     * @var PlpOrderCollectionFactory
     */
    protected $plpOrderCollectionFactory;

    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;
    
    /**
     * @var SigepWebDataFormatter
     */
    protected $sigepWebDataFormatter;
    
    /**
     * @var StoreInformation
     */
    protected $storeInformation;
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param LoggerInterface $logger
     * @param Json $json
     * @param OrderRepositoryInterface $orderRepository
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param PlpOrderCollectionFactory $plpOrderCollectionFactory
     * @param PlpRepositoryInterface $plpRepository
     * @param SigepWebDataFormatter $sigepWebDataFormatter
     * @param StoreInformation $storeInformation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        LoggerInterface $logger,
        Json $json,
        OrderRepositoryInterface $orderRepository,
        PlpCollectionFactory $plpCollectionFactory,
        PlpOrderCollectionFactory $plpOrderCollectionFactory,
        PlpRepositoryInterface $plpRepository,
        SigepWebDataFormatter $sigepWebDataFormatter,
        StoreInformation $storeInformation,
        StoreManagerInterface $storeManager = null
    ) {
        $this->logger = $logger;
        $this->json = $json;
        $this->orderRepository = $orderRepository;
        $this->plpCollectionFactory = $plpCollectionFactory;
        $this->plpOrderCollectionFactory = $plpOrderCollectionFactory;
        $this->plpRepository = $plpRepository;
        $this->sigepWebDataFormatter = $sigepWebDataFormatter;
        $this->storeInformation = $storeInformation;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute PLP data collection for a specific PLP
     *
     * @param int $plpId
     * @return array
     */
    public function execute($plpId)
    {
        $result = [
            'success' => true,
            'message' => __('PLP data collection completed'),
            'processed' => 0,
            'errors' => 0
        ];

        try {
            $plp = $this->plpRepository->getById($plpId);
            
            if (!$plp || $plp->getStatus() !== PlpStatus::STATUS_OPEN) {
                $result['success'] = false;
                $result['message'] = __('PLP is not in open status or does not exist');
                return $result;
            }

            $pendingOrders = $this->getPendingOrders($plpId);
            
            if ($pendingOrders->getSize() === 0) {
                $result['message'] = __('No pending orders found for processing in PLP %1', $plpId);
                return $result;
            }

            $plp->setStatus(PlpStatus::STATUS_COLLECTING);
            $this->plpRepository->save($plp);

            foreach ($pendingOrders as $plpOrder) {
                try {
                    $collectedData = $this->collectOrderData($plpOrder->getOrderId());
                    
                    $this->plpRepository->updateOrderCollectedData(
                        $plpId,
                        $plpOrder->getOrderId(),
                        $this->json->serialize($collectedData)
                    );
                    
                    $this->plpRepository->updateOrderCollectionStatus(
                        $plpId,
                        $plpOrder->getOrderId(),
                        'completed'
                    );
                    
                    $this->plpRepository->updateOrderStatus(
                        $plpId,
                        $plpOrder->getOrderId(),
                        'processing'
                    );
                    
                    $result['processed']++;
                } catch (\Exception $e) {
                    $this->logger->error(
                        __('Error collecting data for order %1: %2', $plpOrder->getOrderId(), $e->getMessage())
                    );
                    
                    $this->plpRepository->updateOrderCollectionStatus(
                        $plpId,
                        $plpOrder->getOrderId(),
                        'error'
                    );
                    
                    $this->plpRepository->updateOrderStatus(
                        $plpId,
                        $plpOrder->getOrderId(),
                        'error',
                        $e->getMessage()
                    );
                    
                    $result['errors']++;
                }
            }

            $allProcessed = $this->checkAllOrdersProcessed($plpId);
            if ($allProcessed) {
                $plp->setStatus(PlpStatus::STATUS_FORMED);
                $this->plpRepository->save($plp);
            } else {
                $plp->setStatus(PlpStatus::STATUS_OPEN);
                $this->plpRepository->save($plp);
            }

        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result['success'] = false;
            $result['message'] = __('Error processing PLP %1: %2', $plpId, $e->getMessage());
        }

        return $result;
    }

    /**
     * Get open PLPs with pending orders
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    public function getOpenPlpsWithPendingOrders()
    {
        $collection = $this->plpCollectionFactory->create();
        $collection->addFieldToFilter('status', PlpStatus::STATUS_OPEN);
        
        $plpsWithPendingOrders = [];
        foreach ($collection as $plp) {
            $pendingOrders = $this->getPendingOrders($plp->getId());
            if ($pendingOrders->getSize() > 0) {
                $plpsWithPendingOrders[] = $plp->getId();
            }
        }
        
        if (!empty($plpsWithPendingOrders)) {
            $collection->addFieldToFilter('entity_id', ['in' => $plpsWithPendingOrders]);
        } else {
            $collection->addFieldToFilter('entity_id', 0);
        }

        return $collection;
    }

    /**
     * Get pending orders for a specific PLP
     *
     * @param int $plpId
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\Collection
     */
    protected function getPendingOrders($plpId)
    {
        $collection = $this->plpOrderCollectionFactory->create();
        $collection->addFieldToFilter('plp_id', $plpId);
        $collection->addFieldToFilter('status', 'pending');
        $collection->addFieldToFilter(
            ['collection_status', 'collection_status'],
            [
                ['eq' => 'pending'],
                ['null' => true]
            ]
        );
        
        return $collection;
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
            throw new \Exception(__('Shipping address not found for order %1', $orderId));
        }

        $collectedData = [
            'order_info' => [
                'order_id' => $order->getEntityId(),
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
        $collectedData = $this->sigepWebDataFormatter->formatOrderData(
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
        $collection = $this->plpOrderCollectionFactory->create();
        $collection->addFieldToFilter('plp_id', $plpId);
        $collection->addFieldToFilter('collection_status', ['neq' => 'completed']);
        
        return ($collection->getSize() === 0);
    }
}
