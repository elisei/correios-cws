<?php
namespace O2TI\SigepWebCarrier\Ui\Component\Listing\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;

class OrderSelection extends DataProvider
{
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
        $collection = $this->orderGridCollection->create();
        $plpOrderCollection = $this->plpOrderCollection->create();
        $existingOrderIds = $plpOrderCollection->getColumnValues('order_id');
        
        if (!empty($existingOrderIds)) {
            $collection->addFieldToFilter(
                'entity_id',
                ['nin' => $existingOrderIds]
            );
        }

        // $collection->addFieldToFilter('state', ['in' => ['processing']]);

        $collection->setOrder('entity_id', 'DESC');

        $result = [
            'totalRecords' => $collection->getSize(),
            'items' => $collection->getData()
        ];

        return $result;
    }
}
