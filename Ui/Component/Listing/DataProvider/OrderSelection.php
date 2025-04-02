<?php
namespace O2TI\SigepWebCarrier\Ui\Component\Listing\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory;
use O2TI\SigepWebCarrier\Gateway\Config\Config;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

class OrderSelection extends DataProvider
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var PlpOrderCollectionFactory
     */
    protected $plpOrderCollection;

    /**
     * @var CollectionFactory
     */
    protected $orderGridCollection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteria
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param Config $config
     * @param CollectionFactory $orderGridCollection
     * @param PlpOrderCollectionFactory $plpOrderCollection
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
        Config $config,
        CollectionFactory $orderGridCollection,
        PlpOrderCollectionFactory $plpOrderCollection,
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
        $this->config = $config;
        $this->orderGridCollection = $orderGridCollection;
        $this->plpOrderCollection = $plpOrderCollection;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        // Get the search criteria from parent
        $searchCriteria = $this->getSearchCriteria();
        
        // Create the base collection
        $collection = $this->orderGridCollection->create();
        
        // Apply filters to exclude orders already in PLP
        $plpOrderCollection = $this->plpOrderCollection->create();
        $existingOrderIds = $plpOrderCollection->getColumnValues('order_id');
        $allowedStatus = $this->config->getAllowedStatus();
        
        if (!empty($existingOrderIds)) {
            $collection->addFieldToFilter(
                'entity_id',
                ['nin' => $existingOrderIds]
            );
        }

        $collection->addFieldToFilter('status', ['in' => $allowedStatus]);
        $collection->setPageSize(20);

        if ($searchCriteria) {
            foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
                $fields = [];
                $conditions = [];
                
                foreach ($filterGroup->getFilters() as $filter) {
                    $fields[] = $filter->getField();
                    $conditions[] = [$filter->getConditionType() => $filter->getValue()];
                }
                
                if ($fields) {
                    $collection->addFieldToFilter($fields, $conditions);
                }
            }

            if ($searchCriteria->getSortOrders()) {
                foreach ($searchCriteria->getSortOrders() as $sortOrder) {
                    $field = $sortOrder->getField();
                    if ($field) {
                        $direction = $sortOrder->getDirection() === 'ASC' ? 'ASC' : 'DESC';
                        $collection->addOrder($field, $direction);
                    }
                }
            }
            
            if ($searchCriteria->getCurrentPage()) {
                $collection->setCurPage($searchCriteria->getCurrentPage());
            }

            if ($searchCriteria->getPageSize()) {
                $collection->setPageSize($searchCriteria->getPageSize());
            }
        }

        $result = [
            'totalRecords' => $collection->getSize(),
            'items' => $collection->getData()
        ];

        return $result;
    }
}