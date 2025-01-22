<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

declare(strict_types=1);

namespace O2TI\SigepWebCarrier\Model;

use DateTime;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Gateway\Service\TrackingService;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class TrackingProcessor
 *
 * Processes tracking information from Sigep Web Carrier services.
 * Handles the tracking response and formats it into a standardized structure.
 */
class TrackingProcessor
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var TrackingService
     */
    protected $quoteService;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param Config                $config       Configuration object
     * @param TrackingService       $quoteService Service for tracking quotes
     * @param StoreManagerInterface $storeManager Store manager interface
     * @param LoggerInterface       $logger       Logger interface
     */
    public function __construct(
        Config $config,
        TrackingService $quoteService,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->quoteService = $quoteService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Get tracking information for a given tracking number
     *
     * @param string $trackNumber The tracking number to look up
     *
     * @return array|null Array of tracking information or null if not found/error
     */
    public function getTrackingInfo(string $trackNumber): ?array
    {
        try {
            $response = $this->quoteService->trackObject($trackNumber);

            if (!$this->isValidResponse($response)) {
                $this->logger->warning("Invalid tracking response for: $trackNumber");
                return null;
            }

            return $this->processTrackingResponse($response['objetos'][0]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting tracking info: ' . $e->getMessage(), [
                'tracking' => $trackNumber,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Validate tracking response
     *
     * @param array|null $response The response to validate
     *
     * @return bool True if response is valid, false otherwise
     */
    private function isValidResponse(?array $response): bool
    {
        return !empty($response) && !empty($response['objetos']) && !empty($response['objetos'][0]);
    }

    /**
     * Process tracking response into standardized format
     *
     * @param array $trackingInfo Raw tracking information
     *
     * @return array Processed tracking data
     */
    public function processTrackingResponse(array $trackingInfo): array
    {
        $trackData = $this->getInitialTrackingData();

        if (empty($trackingInfo['eventos'])) {
            return $trackData;
        }

        $this->processServiceInfo($trackingInfo, $trackData);
        $this->processEvents($trackingInfo['eventos'], $trackData);
        $this->processEstimatedDelivery($trackingInfo, $trackData);
        $this->processLatestEventStatus($trackingInfo['eventos'][0] ?? [], $trackData);

        return $trackData;
    }

    /**
     * Process service information from tracking data
     *
     * @param array $trackingInfo Raw tracking information
     * @param array $trackData    Reference to tracking data being built
     *
     * @return void
     */
    private function processServiceInfo(array $trackingInfo, array &$trackData): void
    {
        if (!empty($trackingInfo['tipoPostal']['categoria'])) {
            $trackData['service'] = $trackingInfo['tipoPostal']['categoria'];
        }
    }

    /**
     * Process tracking events
     *
     * @param array $events    Array of tracking events
     * @param array $trackData Reference to tracking data being built
     *
     * @return void
     */
    private function processEvents(array $events, array &$trackData): void
    {
        foreach ($events as $event) {
            if (!$this->isValidEvent($event)) {
                continue;
            }

            $trackData['progressdetail'][] = $this->createEventDetail($event);
        }
    }

    /**
     * Validate tracking event
     *
     * @param array $event Event data to validate
     *
     * @return bool True if event is valid, false otherwise
     */
    private function isValidEvent(array $event): bool
    {
        return !empty($event['descricao']) && !empty($event['dtHrCriado']);
    }

    /**
     * Create event detail array from raw event data
     *
     * @param array $event Raw event data
     *
     * @return array Formatted event detail
     */
    private function createEventDetail(array $event): array
    {
        $datetime = new DateTime($event['dtHrCriado']);
        $detail = [
            'activity' => $event['descricao'],
            'deliverydate' => $datetime->format('Y-m-d'),
            'deliverytime' => $datetime->format('H:i:s'),
            'deliverylocation' => $this->formatLocation($event['unidade']['endereco'] ?? [])
        ];

        $this->addAdditionalEventDetails($event, $detail);

        return $detail;
    }

    /**
     * Add additional details to event data
     *
     * @param array $event  Raw event data
     * @param array $detail Reference to detail array being built
     *
     * @return void
     */
    private function addAdditionalEventDetails(array $event, array &$detail): void
    {
        if (!empty($event['detalhe'])) {
            $detail['activity'] .= ' - ' . strip_tags($event['detalhe']);
        }

        if (!empty($event['unidadeDestino'])) {
            $destLocation = $this->formatLocation($event['unidadeDestino']['endereco'] ?? []);
            if ($destLocation) {
                $detail['activity'] .= ' -> ' . $destLocation;
            }
        }
    }

    /**
     * Process estimated delivery information
     *
     * @param array $trackingInfo Raw tracking information
     * @param array $trackData    Reference to tracking data being built
     *
     * @return void
     */
    private function processEstimatedDelivery(array $trackingInfo, array &$trackData): void
    {
        if ($trackData['status'] === 'Pending' && !empty($trackingInfo['dtPrevista'])) {
            $estimatedDate = new DateTime($trackingInfo['dtPrevista']);
            $trackData['status'] = 'In Transit';
            $trackData['estimateddeliverydate'] = $estimatedDate->format('Y-m-d');
        }
    }

    /**
     * Process latest event status
     *
     * @param array $latestEvent Latest event data
     * @param array $trackData   Reference to tracking data being built
     *
     * @return void
     */
    private function processLatestEventStatus(array $latestEvent, array &$trackData): void
    {
        if (empty($latestEvent)) {
            return;
        }

        $datetime = new DateTime($latestEvent['dtHrCriado']);
        $trackData['status'] = $this->mapEventStatus($latestEvent);

        if ($this->isDeliveredEvent($latestEvent)) {
            $trackData['deliverydate'] = $datetime->format('Y-m-d');
            $trackData['deliverytime'] = $datetime->format('H:i:s');
        }
    }

    /**
     * Check if event is a delivery event
     *
     * @param array $event Event data to check
     *
     * @return bool True if event is a delivery, false otherwise
     */
    private function isDeliveredEvent(array $event): bool
    {
        return $event['codigo'] === 'BDE' && $event['tipo'] === '01';
    }

    /**
     * Get initial tracking data structure
     *
     * @return array Initial tracking data structure
     */
    private function getInitialTrackingData(): array
    {
        return [
            'status' => 'sigewep_created',
            'deliverydate' => '',
            'deliverytime' => '',
            'deliverylocation' => '',
            'progressdetail' => []
        ];
    }

    /**
     * Format location string from address array
     *
     * @param array $address Address array containing city and state
     *
     * @return string Formatted location string
     */
    private function formatLocation(array $address): string
    {
        $location = [];
        if (!empty($address['cidade'])) {
            $location[] = $address['cidade'];
        }
        if (!empty($address['uf'])) {
            $location[] = $address['uf'];
        }
        return implode(' - ', $location);
    }

    /**
     * Map event data to status string
     *
     * @param array $event Event data to map
     *
     * @return string Mapped status string
     */
    public function mapEventStatus(array $event): string
    {
        if ($event['codigo'] === 'BDE') {
            return $this->mapBDEEventStatus($event['tipo']);
        }

        return $this->mapOtherEventStatus($event['codigo']);
    }

    /**
     * Map BDE event type to status string
     *
     * @param string $type BDE event type
     *
     * @return string Mapped status string
     */
    private function mapBDEEventStatus(string $type): string
    {
        $deliveryFailureTypes = [
            '20', '21', '22', '23', '24', '25',
            '26', '27', '28', '29'
        ];

        if ($type === '01') {
            return 'sigewep_delivered';
        }

        if (in_array($type, $deliveryFailureTypes, true)) {
            return 'sigewep_delivery_failed';
        }

        return 'sigewep_in_transit';
    }

    /**
     * Map non-BDE event code to status string
     *
     * @param string $code Event code
     *
     * @return string Mapped status string
     */
    private function mapOtherEventStatus(string $code): string
    {
        $statusMap = [
            'OEC' => 'sigewep_on_delivery_route',
            'LDI' => 'sigewep_delivery_failed'
        ];

        return $statusMap[$code] ?? 'sigewep_in_transit';
    }
}
