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

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use O2TI\SigepWebCarrier\Api\PlpManagementInterface;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Api\Data\PlpCreateResultInterfaceFactory;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status;

/**
 * PLP Management Service - creates PLP with validated orders.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlpManagement implements PlpManagementInterface
{
    /**
     * Carrier code prefix used by this module.
     */
    private const CARRIER_CODE = 'sigep_web_carrier';

    /**
     * @var PlpRepositoryInterface
     */
    private $plpRepository;

    /**
     * @var PlpFactory
     */
    private $plpFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PlpCreateResultInterfaceFactory
     */
    private $resultFactory;

    /**
     * @param PlpRepositoryInterface $plpRepository
     * @param PlpFactory $plpFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param PlpCreateResultInterfaceFactory $resultFactory
     */
    public function __construct(
        PlpRepositoryInterface $plpRepository,
        PlpFactory $plpFactory,
        OrderRepositoryInterface $orderRepository,
        PlpCreateResultInterfaceFactory $resultFactory
    ) {
        $this->plpRepository = $plpRepository;
        $this->plpFactory = $plpFactory;
        $this->orderRepository = $orderRepository;
        $this->resultFactory = $resultFactory;
    }

    /**
     * @inheritDoc
     */
    public function create(array $orderIds, $storeId = null)
    {
        if (empty($orderIds)) {
            throw new InputException(__('At least one order ID is required.'));
        }

        $validatedOrderIds = $this->validateOrders($orderIds);

        $plp = $this->plpFactory->create();
        $plp->setStatus(Status::STATUS_PLP_OPENED);
        $plp->setStoreId($storeId ?: 1);
        $plp->setCanSendToCws(true);

        $plp = $this->plpRepository->save($plp);

        $this->plpRepository->addOrderToPlp((int) $plp->getEntityId(), $validatedOrderIds);

        /** @var \O2TI\SigepWebCarrier\Api\Data\PlpCreateResultInterface $result */
        $result = $this->resultFactory->create();
        $result->setPlpId((int) $plp->getEntityId());
        $result->setOrderIds($validatedOrderIds);

        return $result;
    }

    /**
     * Validate that each order exists, can be shipped, and uses Correios carrier.
     *
     * @param string[] $orderIds
     * @return string[]
     * @throws InputException
     * @throws LocalizedException
     */
    private function validateOrders(array $orderIds)
    {
        $errors = [];
        $validated = [];

        foreach ($orderIds as $orderId) {
            try {
                $order = $this->orderRepository->get($orderId);
            } catch (NoSuchEntityException $e) {
                $errors[] = __('Order %1 does not exist.', $orderId);
                continue;
            }

            if (!$order->canShip()) {
                $errors[] = __('Order %1 cannot be shipped.', $order->getIncrementId());
                continue;
            }

            $shippingMethod = $order->getShippingMethod();
            if (!$this->isCorreiosShippingMethod($shippingMethod)) {
                $errors[] = __(
                    'Order %1 does not use Correios shipping method (current: %2).',
                    $order->getIncrementId(),
                    $shippingMethod ?: 'none'
                );
                continue;
            }

            $validated[] = (string) $orderId;
        }

        if (!empty($errors)) {
            throw new LocalizedException(
                __(implode(' ', array_map('strval', $errors)))
            );
        }

        return $validated;
    }

    /**
     * Check whether the given shipping method belongs to Correios.
     *
     * Accepts the current carrier code prefix and the legacy `sigep_<service_code>`
     * format, since older orders may still carry the previous shipping method.
     *
     * @param string|null $shippingMethod
     * @return bool
     */
    private function isCorreiosShippingMethod($shippingMethod)
    {
        if (!$shippingMethod) {
            return false;
        }

        if (strpos($shippingMethod, self::CARRIER_CODE) === 0) {
            return true;
        }

        if (strpos($shippingMethod, 'sigep_') === 0) {
            $parts = explode('_', $shippingMethod);
            $lastPart = end($parts);
            return is_numeric($lastPart);
        }

        return false;
    }
}
