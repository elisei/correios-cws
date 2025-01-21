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

namespace O2TI\SigepWebCarrier\Block\Adminhtml\Form\Field\Column;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use O2TI\SigepWebCarrier\Model\Config\Source\Service;

/**
 * Class ServiceColumn - Create Field to Column with Services List.
 */
class ServiceColumn extends Select
{
    /**
     * @var Service
     */
    private $serviceSource;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Service $serviceSource
     * @param array $data
     */
    public function __construct(
        Context $context,
        Service $serviceSource,
        array $data = []
    ) {
        $this->serviceSource = $serviceSource;
        parent::__construct($context, $data);
    }

    /**
     * Sets name for input element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setData('name', $value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }

        return parent::_toHtml();
    }

    /**
     * Get service options
     *
     * @return array
     */
    private function getSourceOptions(): array
    {
        return $this->serviceSource->toOptionArray();
    }
}
