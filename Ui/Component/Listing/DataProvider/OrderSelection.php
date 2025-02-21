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
    protected $plpOrderCollectionFactory;

    /**
     * @var CollectionFactory
     */
    protected $orderGridCollectionFactory;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param CollectionFactory $orderGridCollectionFactory
     * @param PlpOrderCollectionFactory $plpOrderCollectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        CollectionFactory $orderGridCollectionFactory,
        PlpOrderCollectionFactory $plpOrderCollectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->orderGridCollectionFactory = $orderGridCollectionFactory;
        $this->plpOrderCollectionFactory = $plpOrderCollectionFactory;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        $collection = $this->orderGridCollectionFactory->create();
        
        // Excluir pedidos que já estão em PLPs
        $plpOrderCollection = $this->plpOrderCollectionFactory->create();
        $existingOrderIds = $plpOrderCollection->getColumnValues('order_id');
        
        if (!empty($existingOrderIds)) {
            $collection->addFieldToFilter(
                'entity_id',
                ['nin' => $existingOrderIds]
            );
        }

        // Adicionar filtros adicionais se necessário
        // Por exemplo, filtrar apenas pedidos com status específico
        $collection->addFieldToFilter('status', ['in' => ['processing', 'complete']]);

        // Ordenar por ID decrescente
        $collection->setOrder('entity_id', 'DESC');

        $result = [
            'totalRecords' => $collection->getSize(),
            'items' => $collection->getData(),
        ];

        return $result;
    }
}
