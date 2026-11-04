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
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
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
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * @var Config
     */
    protected $config;

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
     * @param EventManagerInterface $eventManager
     * @param Config $config
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
        EventManagerInterface $eventManager,
        Config $config
    ) {
        $this->orderRepository = $orderRepository;
        $this->sigepDataFormatter = $sigepDataFormatter;
        $this->storeInformation = $storeInformation;
        $this->eventManager = $eventManager;
        $this->config = $config;

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
        $this->operationName = 'PPN data collection';

        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_OPENED;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_COLLECTING_DATA;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_OPENED;

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
        return __('No pending orders found for processing in PPN %1', $plpId);
    }

    /**
     * Process individual PPN order
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
                'Error collecting data for order %1 in PPN %2: %3',
                $plpOrder->getOrderId(),
                $plpOrder->getPlpId(),
                $exc->getMessage()
            ));

            $plpOrder->setErrorMessage($exc->getMessage());
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->failureOrderStatus
            );

            $this->dispatchOrderErrorEvent($plpOrder, $exc->getMessage());

            return false;
        }
    }

    /**
     * Dispatch event when an order fails during data collection.
     *
     * @param object $plpOrder
     * @param string $errorMessage
     * @return void
     */
    private function dispatchOrderErrorEvent($plpOrder, $errorMessage)
    {
        try {
            $this->eventManager->dispatch('sigepweb_plp_order_process_error', [
                'plp_id'        => (int) $plpOrder->getPlpId(),
                'order_id'      => $plpOrder->getOrderId(),
                'error_message' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(__(
                'Error dispatching sigepweb_plp_order_process_error event for order %1: %2',
                $plpOrder->getOrderId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Update final PPN status based on processing results
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
                'order_id'             => $order->getEntityId(),
                'subtotal'             => $order->getBaseSubTotal(),
                'increment_id'         => $order->getIncrementId(),
                'invoice_increment_id' => $this->getInvoiceIncrementId($order),
                'nfe'                  => $this->getNfeData($order),
                'created_at'           => $order->getCreatedAt(),
                'customer_email'       => $order->getCustomerEmail(),
                'customer_firstname'   => $order->getCustomerFirstname(),
                'customer_lastname'    => $order->getCustomerLastname(),
                'shipping_method'      => $order->getShippingMethod(),
                'shipping_description' => $order->getShippingDescription(),
                'total_weight'         => $order->getWeight()
                    ? $order->getWeight()
                    : $this->calculateTotalWeight($order),
            ],
            'shipping_address' => [
                'firstname'  => $shippingAddress->getFirstname(),
                'lastname'   => $shippingAddress->getLastname(),
                'company'    => $shippingAddress->getCompany(),
                'street'     => $shippingAddress->getStreet(),
                'city'       => $shippingAddress->getCity(),
                'region'     => $shippingAddress->getRegion(),
                'region_code' => $shippingAddress->getRegionCode(),
                'postcode'   => $shippingAddress->getPostcode(),
                'country_id' => $shippingAddress->getCountryId(),
                'telephone'  => $shippingAddress->getTelephone(),
                'vat_id'     => $shippingAddress->getVatId(),
            ],
            'items' => []
        ];

        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $collectedData['items'][] = [
                'sku'       => $item->getSku(),
                'name'      => $item->getName(),
                'qty'       => $item->getQtyOrdered(),
                'weight'    => $item->getWeight(),
                'price'     => $item->getPrice(),
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
     * Get invoice increment ID from order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return string
     */
    protected function getInvoiceIncrementId($order)
    {
        $invoices = $order->getInvoiceCollection();

        if ($invoices->getSize() > 0) {
            $invoice = $invoices->getFirstItem();
            return $invoice->getIncrementId();
        }

        return '';
    }

    /**
     * Get NF-e data from the most recent comment that carries a valid access key
     *
     * Reads the invoice comments, or the order comments (status history) when the
     * declaration type is "NF-e (order comment)".
     *
     * Expected comment lines:
     *     NF-e: 33
     *     Chave: 35261160963974000100550020000000331178139575
     *
     * The number comes from the "NF-e:" line; when it is missing or does not match
     * the number embedded in the key (nNF, positions 26-34), the key wins.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array{number: string, access_key: string}|null
     */
    protected function getNfeData($order)
    {
        $found = null;
        $foundSortKey = null;

        foreach ($this->getNfeComments($order) as $comment) {
            $text = (string)$comment->getComment();
            if (!preg_match(
                '/^\s*chave(?:\s+de\s+acesso)?\s*[:=]\s*([\d\s.]{44,60})\s*$/imu',
                $text,
                $matches
            )) {
                continue;
            }

            $key = preg_replace('/\D/', '', $matches[1]);
            if (!$this->isValidNfeAccessKey($key)) {
                $this->logger->warning(__(
                    'Invalid NF-e access key in comment %1 of order %2',
                    $comment->getId(),
                    $order->getIncrementId()
                ));
                continue;
            }

            $sortKey = [(string)$comment->getCreatedAt(), (int)$comment->getId()];
            if ($foundSortKey === null || $sortKey > $foundSortKey) {
                $found = [
                    'number' => $this->resolveNfeNumber($text, $key, (string)$order->getIncrementId()),
                    'access_key' => $key,
                ];
                $foundSortKey = $sortKey;
            }
        }

        return $found;
    }

    /**
     * Comments that may carry the NF-e, according to the declaration type
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return iterable
     */
    protected function getNfeComments($order)
    {
        if ($this->config->isEmiteDceEnabled()) {
            return [];
        }

        if ($this->config->isNfeFromOrderComment()) {
            return $order->getStatusHistoryCollection();
        }

        $comments = [];
        foreach ($order->getInvoiceCollection() as $invoice) {
            foreach ($invoice->getCommentsCollection() as $comment) {
                $comments[] = $comment;
            }
        }

        return $comments;
    }

    /**
     * NF-e number from the "NF-e:" comment line, checked against the key
     *
     * @param string $text
     * @param string $key
     * @param string $orderIncrementId
     * @return string
     */
    protected function resolveNfeNumber(string $text, string $key, string $orderIncrementId): string
    {
        $keyNumber = ltrim(substr($key, 25, 9), '0');

        if (!preg_match(
            '/^\s*(?:nf-?e|nota(?:\s+fiscal)?|n[uú]mero)\s*[:=]\s*(\d{1,12})\s*$/imu',
            $text,
            $matches
        )) {
            return $keyNumber;
        }

        $number = ltrim($matches[1], '0');
        if ($number !== $keyNumber) {
            $this->logger->warning(__(
                'NF-e number %1 does not match access key number %2 for order %3; using the key',
                $number,
                $keyNumber,
                $orderIncrementId
            ));
            return $keyNumber;
        }

        return $number;
    }

    /**
     * Check NF-e access key: 44 digits and modulo 11 check digit
     *
     * @param string $key
     * @return bool
     */
    protected function isValidNfeAccessKey(string $key): bool
    {
        if (!preg_match('/^\d{44}$/', $key)) {
            return false;
        }

        $sum = 0;
        $weight = 2;
        for ($i = 42; $i >= 0; $i--) {
            $sum += (int)$key[$i] * $weight;
            $weight = $weight === 9 ? 2 : $weight + 1;
        }
        $rest = $sum % 11;
        $digit = $rest < 2 ? 0 : 11 - $rest;

        return $digit === (int)$key[43];
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

        return $weight ?: 0.5;
    }

    /**
     * Check if all orders in a PPN have been processed
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
