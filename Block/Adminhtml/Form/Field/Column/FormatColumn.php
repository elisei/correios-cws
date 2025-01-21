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
use O2TI\SigepWebCarrier\Model\Config\Source\Format;

/**
 * Format Column Block for Admin Form Field.
 */
class FormatColumn extends Select
{
    /**
     * @var Format
     */
    private $formatSource;

    /**
     * @param Context $context
     * @param Format $formatSource
     * @param array $data
     */
    public function __construct(
        Context $context,
        Format $formatSource,
        array $data = []
    ) {
        $this->formatSource = $formatSource;
        parent::__construct($context, $data);
    }

    /**
     * Set input name.
     *
     * @param string $value
     * @return $this
     */
    public function setInputName(string $value): self
    {
        return $this->setData('name', $value);
    }

    /**
     * Set input id.
     *
     * @param string $value
     * @return $this
     */
    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML.
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
     * Get source options.
     *
     * @return array<int|string, array<string, string|int|null>>
     */
    private function getSourceOptions(): array
    {
        return $this->formatSource->toOptionArray();
    }
}
