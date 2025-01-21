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

namespace O2TI\SigepWebCarrier\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Block\Adminhtml\Form\Field\Column\FormatColumn;

/**
 * Class AddPackageRules - Admin Configuration for Package Rules.
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class AddPackageRules extends AbstractFieldArray
{
    /**
     * @var FormatColumn
     */
    protected $formatRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn('format', [
            'label'    => __('Format'),
            'renderer' => $this->getFormatRenderer(),
            'class'    => 'required-entry',
        ]);
        
        $this->addColumn('height', [
            'label' => __('Height (cm)'),
            'class' => 'required-entry validate-number',
        ]);

        $this->addColumn('width', [
            'label' => __('Width (cm)'),
            'class' => 'required-entry validate-number',
        ]);

        $this->addColumn('length', [
            'label' => __('Length (cm)'),
            'class' => 'required-entry validate-number',
        ]);

        $this->addColumn('diameter', [
            'label' => __('Diameter (cm)'),
            'class' => 'required-entry validate-number',
        ]);

        $this->addColumn('description', [
            'label' => __('Description'),
            'class' => 'required-entry',
        ]);

        $this->addColumn('max_weight', [
            'label' => __('Maximum Weight (kg)'),
            'class' => 'required-entry validate-number',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Prepare array row for format renderer.
     *
     * @param DataObject $row
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $format = $row->getFormat();
        if ($format !== null) {
            $options['option_' . $this->getFormatRenderer()->calcOptionHash($format)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Get format column renderer.
     *
     * @return FormatColumn
     * @throws LocalizedException
     */
    private function getFormatRenderer(): FormatColumn
    {
        if (!$this->formatRenderer) {
            $this->formatRenderer = $this->getLayout()->createBlock(
                FormatColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->formatRenderer;
    }
}
