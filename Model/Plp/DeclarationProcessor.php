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
use Magento\Framework\Exception\RuntimeException;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Gateway\Service\PlpDeclarationContent;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

class DeclarationProcessor
{
    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @var PlpOrderCollectionFactory
     */
    protected $plpOrderCollec;

    /**
     * @var PlpDeclarationContent
     */
    protected $declarationService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpOrderCollectionFactory $plpOrderCollec
     * @param PlpDeclarationContent $declarationService
     * @param LoggerInterface $logger
     * @param Json $json
     */
    public function __construct(
        PlpRepositoryInterface $plpRepository,
        PlpOrderCollectionFactory $plpOrderCollec,
        PlpDeclarationContent $declarationService,
        LoggerInterface $logger,
        Json $json
    ) {
        $this->plpRepository = $plpRepository;
        $this->plpOrderCollec = $plpOrderCollec;
        $this->declarationService = $declarationService;
        $this->logger = $logger;
        $this->json = $json;
    }

    /**
     * Process declaration content for a PPN
     *
     * @param int $plpId PPN ID
     * @return array
     * @throws LocalizedException
     */
    public function processDeclaration(int $plpId): array
    {
        try {
            $plp = $this->plpRepository->getById($plpId);
            if (!$plp) {
                throw new LocalizedException(__('PPN with ID %1 not found', $plpId));
            }

            $collection = $this->plpOrderCollec->create();
            $collection->addFieldToFilter('plp_id', $plpId);

            if ($collection->getSize() === 0) {
                throw new LocalizedException(__('No orders found in PPN %1', $plpId));
            }

            $orderIds = [];
            foreach ($collection as $plpOrder) {
                $processingData = $plpOrder->getProcessingData();
                if (!empty($processingData)) {
                    $data = $this->json->unserialize($processingData);
                    if (isset($data['id'])) {
                        $orderIds[] = $data['id'];
                    }
                }
            }

            if (empty($orderIds)) {
                throw new LocalizedException(__('No valid order IDs found in PPN %1', $plpId));
            }

            $result = $this->declarationService->execute($orderIds);

            if (!$result['success']) {
                throw new LocalizedException($result['message']);
            }

            return [
                'success' => $result['success'],
                'filename' => $result['filename'],
                'filepath' => $result['filepath']
            ];

        } catch (RuntimeException $exc) {
            $this->logger->critical($exc);
            throw new LocalizedException(__('Error processing declaration: %1', $exc->getMessage()));
        }
    }
}
