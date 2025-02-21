<?php
namespace O2TI\SigepWebCarrier\Ui\Component\Listing\DataProvider;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory;
use O2TI\SigepWebCarrier\Model\ResourceModel\PlpOrder\CollectionFactory as PlpOrderCollectionFactory;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;
use Magento\Ui\DataProvider\AbstractDataProvider;

class OrderDataProvider extends AbstractDataProvider
{
    /**
     * @var PlpOrderCollectionFactory
     */
    protected $plpOrderCollectionFactory;

    /**
     * @var PlpSession
     */
    protected $plpSession;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Grid\Collection
     */
    protected $collection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param PlpOrderCollectionFactory $plpOrderCollectionFactory
     * @param PlpSession $plpSession
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        PlpOrderCollectionFactory $plpOrderCollectionFactory,
        PlpSession $plpSession,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->plpOrderCollectionFactory = $plpOrderCollectionFactory;
        $this->plpSession = $plpSession;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $items = $this->getCollection();
        
        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($items->getData()),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getCollection()
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $collection */
        $collection = $this->collection;
        
        $plpOrderCollection = $this->plpOrderCollectionFactory->create();

        $currentPlpId = $this->plpSession->getCurrentPlpId();
        
        if ($currentPlpId) {
            $plpOrderCollection->addFieldToFilter('plp_id', ['neq' => $currentPlpId]);
        }
        
        $existingOrderIds = $plpOrderCollection->getColumnValues('order_id');

        if (!empty($existingOrderIds)) {
            $collection->addFieldToFilter(
                'entity_id',
                ['nin' => $existingOrderIds]
            );
        }

        return $collection;
    }

    /**
     * Add field to select
     *
     * @param string|array $field
     * @param string|null $alias
     * @return void
     */
    public function addField($field, $alias = null)
    {
        if ($this->collection) {
            $this->collection->addFieldToSelect($field, $alias);
        }
    }
}
