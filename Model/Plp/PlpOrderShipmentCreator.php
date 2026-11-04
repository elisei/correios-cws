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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Shipping\Model\ShipmentNotifier;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;
use O2TI\SigepWebCarrier\Model\Plp\Source\StatusItem as PlpStatusItem;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\Plp\CollectionFactory as PlpCollectionFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlpOrderShipmentCreator extends AbstractPlpOperation
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ShipmentRepositoryInterface
     */
    protected $shipmentRepository;

    /**
     * @var ConvertOrder
     */
    protected $convertOrder;

    /**
     * @var ShipmentNotifier
     */
    protected $shipmentNotifier;

    /**
     * @var Filesystem
     */
    protected $filesystem;
    
    /**
     * @var TrackFactory
     */
    protected $trackFactory;
    
    /**
     * @var bool
     */
    protected $sendEmail;
    
    /**
     * @var DriverFile
     */
    protected $driver;

    /**
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * @var array
     */
    protected $result;

    /**
     * Constructor
     *
     * @param Json $json
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ConvertOrder $convertOrder
     * @param ShipmentNotifier $shipmentNotifier
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpOrderCollectionFactory $plpOrdCollection
     * @param PlpCollectionFactory $plpCollectionFactory
     * @param Filesystem $filesystem
     * @param TrackFactory $trackFactory
     * @param DriverFile $driver
     * @param EventManagerInterface $eventManager
     * @param bool $sendEmail
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function __construct(
        Json $json,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        ShipmentRepositoryInterface $shipmentRepository,
        ConvertOrder $convertOrder,
        ShipmentNotifier $shipmentNotifier,
        PlpRepositoryInterface $plpRepository,
        PlpOrderCollectionFactory $plpOrdCollection,
        PlpCollectionFactory $plpCollectionFactory,
        Filesystem $filesystem,
        TrackFactory $trackFactory,
        DriverFile $driver,
        EventManagerInterface $eventManager,
        $sendEmail = false
    ) {
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->filesystem = $filesystem;
        $this->trackFactory = $trackFactory;
        $this->driver = $driver;
        $this->eventManager = $eventManager;
        $this->sendEmail = $sendEmail;

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
        $this->operationName = 'shipment creation';
        
        // Define PPN statuses
        $this->expectedPlpStatus = PlpStatus::STATUS_PLP_AWAITING_SHIPMENT;
        $this->inProgressPlpStatus = PlpStatus::STATUS_PLP_REQUESTING_SHIPMENT_CREATION;
        $this->successPlpStatus = PlpStatus::STATUS_PLP_COMPLETED;
        $this->failurePlpStatus = PlpStatus::STATUS_PLP_AWAITING_SHIPMENT;
        
        // Define order statuses
        $this->expectedTypeFilter = 'status';
        $this->expectedOrderStatus = [
            PlpStatusItem::STATUS_ITEM_DOWNLOAD_COMPLETED,
            PlpStatusItem::STATUS_ITEM_DACE_COMPLETED
        ];
        $this->inProgressOrdStatus = PlpStatusItem::STATUS_ITEM_PROCESSING_SHIP_CREATE;
        $this->successOrderStatus = PlpStatusItem::STATUS_ITEM_SHIP_CREATED;
        $this->failureOrderStatus = PlpStatusItem::STATUS_ITEM_SHIP_CREATE_ERROR;
    }
    
    /**
     * Create initial result structure
     *
     * @return array
     */
    protected function createInitialResult()
    {
        $this->result = $this->createSuccessResponse(
            __('Shipments created successfully'),
            [
                'processed' => 0,
                'errors' => 0,
                'data' => [
                    'success_count' => 0,
                    'error_count' => 0,
                    'shipments' => []
                ]
            ]
        );
        
        return $this->result;
    }
    
    /**
     * Get message for when no eligible orders are found
     *
     * @param int $plpId
     * @return \Magento\Framework\Phrase
     */
    protected function getNoOrdersMessage($plpId)
    {
        return __('No PPN orders with labels found in PPN %1', $plpId);
    }

    /**
     * Execute shipment creation for orders in a PPN with custom email flag
     *
     * @param int $plpId
     * @param bool $sendEmail Overrides the class-level setting
     * @return array
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function execute($plpId, $sendEmail = true)
    {
        $this->sendEmail = $sendEmail !== null ? (bool)$sendEmail : $this->sendEmail;
        return parent::execute($plpId);
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

            if ($plpOrder->getShipmentId()) {
                $this->logger->info(__(
                    'Shipment already exists for order %1',
                    $orderId
                ));
                
                $result['data']['shipments'][] = [
                    'plp_order_id' => $plpOrder->getId(),
                    'order_id' => $orderId,
                    'shipment_id' => $plpOrder->getShipmentId(),
                    'status' => 'already_exists'
                ];
                
                $result['data']['success_count']++;
                return true;
            }
            
            $processingData = $this->json->unserialize($plpOrder->getProcessingData() ?: '{}');
            
            if (!isset($processingData['tracking']) || empty($processingData['tracking'])) {
                throw new LocalizedException(__('No tracking code found for order %1', $orderId));
            }
            
            if (!isset($processingData['labelFileName']) || empty($processingData['labelFileName'])) {
                throw new LocalizedException(__('No label file found for order %1', $orderId));
            }
            
            $order = $this->orderRepository->get($orderId);
            
            if (!$order->canShip()) {
                throw new LocalizedException(__('Order %1 cannot be shipped', $orderId));
            }
            
            $shipment = $this->createShipment($order, $processingData, $this->sendEmail);
            
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->successOrderStatus,
                $processingData,
                null,
                $shipment->getId()
            );
            
            $result['data']['shipments'][] = [
                'plp_order_id' => $plpOrder->getId(),
                'order_id' => $orderId,
                'shipment_id' => $shipment->getId(),
                'tracking_code' => $processingData['tracking'],
                'status' => 'created'
            ];
            
            $result['data']['success_count']++;
            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->error(__(
                'Error creating shipment for order %1: %2',
                $plpOrder->getOrderId(),
                $errorMessage
            ));

            $plpOrder->setErrorMessage($errorMessage);
            $this->updatePlpOrderStatus(
                $plpOrder,
                $this->failureOrderStatus
            );

            $this->dispatchOrderErrorEvent($plpOrder, $errorMessage);

            $result['data']['error_count']++;
            return false;
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
        if ($successCount > 0) {
            // At least one shipment created — PPN is done; per-order errors remain visible at item level
            $plp->setStatus($this->successPlpStatus);
        } elseif ($errorCount > 0) {
            // No shipments created — keep at AWAITING_SHIPMENT for manual intervention
            $plp->setStatus($this->failurePlpStatus);
        }

        $this->plpRepository->save($plp);
        $this->generateShipmentResultMessage($plp->getId());

        if ($plp->getStatus() === PlpStatus::STATUS_PLP_COMPLETED) {
            $this->dispatchCompletedEvent($plp);
        }
    }

    /**
     * Generate appropriate result message for shipment creation
     *
     * @param int $plpId
     */
    private function generateShipmentResultMessage($plpId)
    {
        $successCount = $this->result['data']['success_count'];
        $errorCount = $this->result['data']['error_count'];
        
        if ($successCount > 0 && $errorCount == 0) {
            $this->result['message'] = __('Successfully created %1 shipments for PPN %2', $successCount, $plpId);
        } elseif ($successCount > 0 && $errorCount > 0) {
            $this->result['message'] = __(
                'Created %1 shipments for PPN %2 with %3 errors',
                $successCount,
                $plpId,
                $errorCount
            );
        } elseif ($successCount == 0 && $errorCount > 0) {
            $this->result['success'] = false;
            $this->result['message'] = __('Failed to create any shipments for PPN %1 (%2 errors)', $plpId, $errorCount);
        }
        
        $this->result['processed'] = $successCount;
        $this->result['errors'] = $errorCount;
    }
    
    /**
     * Create shipment for order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param array $processingData
     * @param bool $sendEmail
     * @return \Magento\Sales\Api\Data\ShipmentInterface
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    private function createShipment($order, $processingData, $sendEmail = false)
    {
        $shipment = $this->convertOrder->toShipment($order);
        
        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }
            
            $qtyShipped = $orderItem->getQtyToShip();
            $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
            $shipment->addItem($shipmentItem);
        }
        
        $carrierCode = 'sigep_web_carrier';
        $carrierTitle = 'Correios';
        $trackingNumber = $processingData['tracking'];
        
        if (isset($processingData['serviceName']) && !empty($processingData['serviceName'])) {
            $carrierTitle = 'Correios ' . $processingData['serviceName'];
        }
        
        $track = $this->trackFactory->create();
        $track->setCarrierCode($carrierCode);
        $track->setTitle($carrierTitle);
        $track->setTrackNumber($trackingNumber);
        
        $shipment->addTrack($track);
        
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $relativeFilePath = 'sigepweb/labels/' . $processingData['labelFileName'];

        if ($mediaDirectory->isExist($relativeFilePath)) {
            $labelContent = $mediaDirectory->readFile($relativeFilePath);
            $shipment->setShippingLabel($labelContent);
        }

        if (!empty($processingData['receiptFileName'])) {
            $shipment->setData('sigepweb_dace_path', $processingData['receiptFileName']);
        }
        
        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);
        
        $this->shipmentRepository->save($shipment);
        $this->orderRepository->save($order);
        
        if ($sendEmail) {
            try {
                $this->shipmentNotifier->notify($shipment);
                $this->logger->info(__(
                    'Email notification sent for shipment %1 (order %2)',
                    $shipment->getId(),
                    $order->getIncrementId()
                ));
            } catch (\Exception $e) {
                $this->logger->warning(__(
                    'Failed to send email notification for shipment %1: %2',
                    $shipment->getId(),
                    $e->getMessage()
                ));
            }
        }
        
        return $shipment;
    }

    /**
     * Get PLPs with labels
     *
     * @return \O2TI\SigepWebCarrier\Model\ResourceModel\Plp\Collection
     */
    public function getPlpsWithLabels()
    {
        return $this->getPlpsByStatus(PlpStatus::STATUS_PLP_AWAITING_SHIPMENT);
    }

    /**
     * Dispatch event when PLP is completed — all orders succeeded.
     *
     * Each order carries label and DACE file blobs with their MD5 hashes.
     *
     * @param object $plp
     * @return void
     */
    private function dispatchCompletedEvent($plp)
    {
        try {
            $plpId = (int) $plp->getId();
            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $ordersData = [];

            $allOrders = $this->plpOrdCollection->create();
            $allOrders->addFieldToFilter('plp_id', $plpId);
            $allOrders->addFieldToFilter('status', PlpStatusItem::STATUS_ITEM_SHIP_CREATED);

            foreach ($allOrders as $plpOrder) {
                $processingData = $this->json->unserialize($plpOrder->getProcessingData() ?: '{}');

                $labelBlob = null;
                $labelMd5 = null;
                $labelType = null;
                if (!empty($processingData['labelFileName'])) {
                    $labelPath = 'sigepweb/labels/' . $processingData['labelFileName'];
                    if ($mediaDir->isExist($labelPath)) {
                        $labelBlob = $mediaDir->readFile($labelPath);
                        $labelMd5 = md5($labelBlob);
                        $labelType = pathinfo($processingData['labelFileName'], PATHINFO_EXTENSION) ?: 'pdf';
                    }
                }

                $daceBlob = null;
                $daceMd5 = null;
                $daceType = null;
                if (!empty($processingData['receiptFileName'])) {
                    $dacePath = 'sigepweb/labels/' . $processingData['receiptFileName'];
                    if ($mediaDir->isExist($dacePath)) {
                        $daceBlob = $mediaDir->readFile($dacePath);
                        $daceMd5 = md5($daceBlob);
                        $daceType = pathinfo($processingData['receiptFileName'], PATHINFO_EXTENSION) ?: 'pdf';
                    }
                }

                $ordersData[] = [
                    'order_id'   => $plpOrder->getOrderId(),
                    'tracking'   => $processingData['tracking'] ?? null,
                    'label_blob' => $labelBlob,
                    'label_md5'  => $labelMd5,
                    'label_type' => $labelType,
                    'dace_blob'  => $daceBlob,
                    'dace_md5'   => $daceMd5,
                    'dace_type'  => $daceType,
                ];
            }

            $this->eventManager->dispatch('sigepweb_plp_completed', [
                'plp'    => $plp,
                'orders' => $ordersData,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(__(
                'Error dispatching sigepweb_plp_completed event for PPN %1: %2',
                $plp->getId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Dispatch event immediately when an order fails during processing.
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
}
