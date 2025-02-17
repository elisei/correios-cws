<?php
namespace O2TI\SigepWebCarrier\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;
use Magento\Framework\Escaper;

class ShipmentLink extends Column
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
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

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['shipment_id']) && !empty($item['shipment_id'])) {
                    $url = $this->urlBuilder->getUrl(
                        'sales/shipment/view',
                        ['shipment_id' => $item['shipment_id']]
                    );
                    $escapedUrl = $this->escaper->escapeUrl($url);
                    $escapedLabel = $this->escaper->escapeHtml('#' . $item['shipment_id']);
                    $item[$this->getData('name')] = '<a href="' . $escapedUrl . '">' . $escapedLabel . '</a>';
                }
            }
        }

        return $dataSource;
    }
}