<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Ui\Component;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;

class DataProvider extends \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider
{
    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @var array
     */
    protected $loadedData;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteria
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param PlpRepositoryInterface $plpRepository
     * @param array $meta
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteria,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        PlpRepositoryInterface $plpRepository,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteria,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->plpRepository = $plpRepository;
    }

    /**
     * Get data
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $plpId = $this->request->getParam($this->requestFieldName);
        if ($plpId) {
            try {
                $plp = $this->plpRepository->getById($plpId);
                $this->loadedData = [
                    $plp->getEntityId() => $plp->getData()
                ];
            } catch (\Exception $exception) {}  //@codingStandardsIgnoreLine
        }

        return $this->loadedData ?? [];
    }
}
