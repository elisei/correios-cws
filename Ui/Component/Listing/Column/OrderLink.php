<?php
namespace O2TI\SigepWebCarrier\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;
use Magento\Framework\Escaper;

class OrderLink extends Column
{
    protected $urlBuilder;
    protected $escaper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->escaper = $escaper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['order_id'])) {
                    $url = $this->urlBuilder->getUrl(
                        'sales/order/view',
                        ['order_id' => $item['order_id']]
                    );
                    $escapedUrl = $this->escaper->escapeUrl($url);
                    $escapedLabel = $this->escaper->escapeHtml($item['order_increment_id'] ?? $item['order_id']);
                    $item[$this->getData('name')] = '<a href="' . $escapedUrl . '">#' . $escapedLabel . '</a>';
                }
            }
        }
        return $dataSource;
    }
}
