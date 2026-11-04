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

use O2TI\SigepWebCarrier\Model\Carrier as CorreiosCarrier;
use O2TI\SigepWebCarrier\Model\TrackingProcessor;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Order\Email\Sender\ShipmentCommentSender;
use Magento\Framework\Escaper;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Psr\Log\LoggerInterface;
use DateTime;

/**
 * Tracking Status for Cron and Model.
 */
class TrackingStatus
{
    /**
     * @var OrderRepository
     */
    protected $orderRepo;

    /**
     * @var TrackingProcessor
     */
    protected $trackingProcessor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ShipmentCommentSender
     */
    protected $commentSender;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var HistoryFactory
     */
    protected $historyFactory;

    /**
     * Constructor.
     *
     * @param OrderRepository $orderRepo
     * @param TrackingProcessor $trackingProcessor
     * @param LoggerInterface $logger
     * @param ShipmentCommentSender $commentSender
     * @param Escaper $escaper
     * @param HistoryFactory $historyFactory
     */
    public function __construct(
        OrderRepository $orderRepo,
        TrackingProcessor $trackingProcessor,
        LoggerInterface $logger,
        ShipmentCommentSender $commentSender,
        Escaper $escaper,
        HistoryFactory $historyFactory
    ) {
        $this->orderRepo = $orderRepo;
        $this->trackingProcessor = $trackingProcessor;
        $this->logger = $logger;
        $this->commentSender = $commentSender;
        $this->escaper = $escaper;
        $this->historyFactory = $historyFactory;
    }

    /**
     * Process tracking status for an order
     *
     * @param Order $order
     * @throws \Exception
     * @return void
     */
    public function processOrder(Order $order)
    {
        try {
            foreach ($order->getShipmentsCollection() as $shipment) {
                $this->processShipmentTracks($shipment);
            }
        } catch (\Exception $exc) {
            $this->logError('Error processing order tracking', $exc, ['order_id' => $order->getId()]);
            throw $exc;
        }
    }

    /**
     * Process tracking numbers for a shipment
     *
     * @param Shipment $shipment
     * @return void
     */
    protected function processShipmentTracks(Shipment $shipment)
    {
        foreach ($shipment->getAllTracks() as $track) {
            if ($track->getCarrierCode() === CorreiosCarrier::CODE) {
                $trackingInfo = $this->trackingProcessor->getTrackingInfo($track->getTrackNumber());
                if ($trackingInfo) {
                    $this->updateShipmentStatus($shipment, $track->getTrackNumber(), $trackingInfo);
                }
            }
        }
    }

    /**
     * Update shipment and order status
     *
     * @param Shipment $shipment
     * @param string $trackNumber
     * @param array $trackingInfo
     * @return void
     */
    protected function updateShipmentStatus(Shipment $shipment, string $trackNumber, array $trackingInfo)
    {
        $status = $trackingInfo['status'];
        $order = $shipment->getOrder();
        $currentStatus = $order->getStatus();
        $currentLocation = $trackingInfo['progressdetail'][0]['deliverylocation'] ?? '';
        
        $shouldAddComment = $this->shouldAddComment($shipment, $status, $currentStatus, $currentLocation);
        
        if ($shouldAddComment) {
            $comment = $this->formatStatusComment($trackNumber, $trackingInfo);
            $shouldNotifyCustomer = ($status !== 'sigewep_created') && $shouldAddComment;

            $this->updateOrderStatus($order, $comment, $status);
            $this->addShipmentComment($shipment, $comment, $shouldNotifyCustomer);
        }
    }

    /**
     * Check if comment should be added
     *
     * @param Shipment $shipment
     * @param string $newStatus
     * @param string $currentStatus
     * @param string $currentLocation
     * @return bool
     */
    private function shouldAddComment(
        Shipment $shipment,
        string $newStatus,
        string $currentStatus,
        string $currentLocation
    ): bool {
        if ($newStatus !== $currentStatus && $newStatus !== 'sigewep_in_transit') {
            return true;
        }

        if ($newStatus === 'sigewep_in_transit') {
            return $this->hasLocationChanged($shipment, $currentLocation);
        }

        return false;
    }

    /**
     * Check if location has changed from last comment
     *
     * @param Shipment $shipment
     * @param string $currentLocation
     * @return bool
     */
    private function hasLocationChanged(Shipment $shipment, string $currentLocation): bool
    {
        $comments = $shipment->getCommentsCollection();
        
        if ($comments->getSize() === 0) {
            return true;
        }

        $lastComment = $comments->getLastItem();
        $lastCommentText = $lastComment->getComment();

        if (preg_match('/(?:Localização|Local):\s*([^\.]+)/', $lastCommentText, $matches)) {
            $lastLocation = trim($matches[1]);
            $lastLocation = preg_replace('/\s+em\s+\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}/', '', $lastLocation);
            $normalLastLocation = $this->normalizeTrackingLocation($lastLocation);
            $normalCurrentLocal = $this->normalizeTrackingLocation($currentLocation);
            
            return $normalLastLocation !== $normalCurrentLocal;
        }

        return true;
    }

    /**
     * Format status update comment
     *
     * @param string $trackNumber
     * @param array $trackingInfo
     * @return \Magento\Framework\Phrase
     */
    protected function formatStatusComment(string $trackNumber, array $trackingInfo): \Magento\Framework\Phrase
    {
        if (empty($trackingInfo['progressdetail'])) {
            // phpcs:ignore
            return __(
                'Seu pedido foi encaminhado para coleta dos Correios. Código de rastreio: %1. Aguarde até 24 horas para acompanhar a entrega.',
                $trackNumber
            );
        }

        $latestEvent = $trackingInfo['progressdetail'][0];
        $deliveryDate = $this->formatTrackingDate($latestEvent['deliverydate'] ?? '');
        $deliveryTime = $this->formatTrackingTime($latestEvent['deliverytime'] ?? '');
        $deliveryLocation = $this->formatTrackingLocation($latestEvent['deliverylocation'] ?? '');

        switch ($trackingInfo['status']) {
            case 'sigewep_delivered':
            case 'Delivered':
                return __(
                    'Seu pedido foi entregue em %1 às %2. Local: %3.',
                    $deliveryDate,
                    $deliveryTime,
                    $deliveryLocation
                );
                
            default:
                return __(
                    'Atualização da entrega: %1. Local: %2. Atualizado em %3 às %4.',
                    $latestEvent['activity'],
                    $deliveryLocation,
                    $deliveryDate,
                    $deliveryTime
                );
        }
    }

    /**
     * Format tracking date for customer-facing comments.
     *
     * @param string $date
     * @return string
     */
    private function formatTrackingDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        try {
            return (new DateTime($date))->format('d/m/Y');
        } catch (\Exception $exc) {
            return $date;
        }
    }

    /**
     * Format tracking time for customer-facing comments.
     *
     * @param string $time
     * @return string
     */
    private function formatTrackingTime(string $time): string
    {
        return preg_replace('/:\d{2}$/', '', $time) ?: $time;
    }

    /**
     * Format tracking location for customer-facing comments.
     *
     * @param string $location
     * @return string
     */
    private function formatTrackingLocation(string $location): string
    {
        return str_replace(' - ', '/', $location);
    }

    /**
     * Normalize tracking location for duplicate comment checks.
     *
     * @param string $location
     * @return string
     */
    private function normalizeTrackingLocation(string $location): string
    {
        $location = str_replace(' - ', '/', $location);

        return preg_replace('/\s+/', ' ', strtolower(trim($location)));
    }

    /**
     * Add comment to shipment
     *
     * @param Shipment $shipment
     * @param string $comment
     * @param bool $shouldNotifyCustomer
     * @return void
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    protected function addShipmentComment(Shipment $shipment, string $comment, bool $shouldNotifyCustomer = true)
    {
        $order = $shipment->getOrder();
        $status = $order->getStatus();
        
        $escapedComment = $this->escaper->escapeHtml($comment);

        $shipment->addComment($escapedComment, $shouldNotifyCustomer, true);
        
        try {
            $shipment->save();

            if ($shouldNotifyCustomer && $status !== 'sigewep_created') {
                $this->commentSender->send($shipment, true, $escapedComment);
            }

        } catch (\Exception $exc) {
            $this->logError('Error sending shipment comment email', $exc, [
                'shipment_id' => $shipment->getId(),
                'order_id' => $shipment->getOrderId()
            ]);
        }
    }

    /**
     * Update order status and history
     *
     * @param Order $order
     * @param string $comment
     * @param string $status
     * @return void
     */
    protected function updateOrderStatus(Order $order, string $comment, string $status)
    {
        $history = $this->historyFactory->create()
            ->setParentId($order->getEntityId())
            ->setComment($comment)
            ->setStatus($status)
            ->setIsCustomerNotified(false)
            ->setEntityName('order');

        try {
            $order->addStatusHistory($history);
            $this->orderRepo->save($order);
        } catch (\Exception $exc) {
            $this->logError('Error updating order status', $exc);
        }
    }

    /**
     * Log error with context
     *
     * @param string $message
     * @param \Exception $exc
     * @param array $context
     * @return void
     */
    protected function logError(string $message, \Exception $exc, array $context = [])
    {
        $this->logger->error(
            $message . ': ' . $exc->getMessage(),
            array_merge($context, ['exception' => $exc])
        );
    }
}
