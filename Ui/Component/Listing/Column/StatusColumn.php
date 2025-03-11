<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status;

class StatusColumn extends Column
{
    /**
     * @var Status
     */
    protected $status;

    /**
     * @var array
     */
    protected $statusOptions;

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Status $status
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Status $status,
        array $components = [],
        array $data = []
    ) {
        $this->status = $status;
        $this->statusOptions = array_column($this->status->toOptionArray(), 'label', 'value');
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
                if (isset($item['status']) && isset($this->statusOptions[$item['status']])) {
                    $item['status'] = $this->statusOptions[$item['status']];
                }
            }
        }
        return $dataSource;
    }
}
