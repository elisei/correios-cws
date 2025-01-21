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

namespace O2TI\SigepWebCarrier\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface;

interface SigepWebServicesRepositoryInterface
{
    /**
     * Save Sigep Web Service.
     *
     * @param \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface $service
     * @return \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(SigepWebServicesInterface $service);

    /**
     * Get by id.
     *
     * @param int $id
     * @return \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function getById($id);

    /**
     * Get by code.
     *
     * @param string $code
     * @return \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getByCode($code);

    /**
     * Get list.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete Sigep Web Service.
     *
     * @param \O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface $service
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(SigepWebServicesInterface $service);

    /**
     * Delete by id.
     *
     * @param int $id
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function deleteById($id);
}
