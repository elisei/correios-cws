<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use O2TI\SigepWebCarrier\Block\Adminhtml\Form\Field\Column\ServiceColumn;

/**
 * Class AddServiceRules - Add Service Rules to field.
 */
class AddServiceRules extends AbstractFieldArray
{
    /**
     * @var ServiceColumn
     */
    protected $serviceRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _prepareToRender()
    {
        $this->addColumn('service', [
            'label'    => __('Service'),
            'renderer' => $this->getServiceRenderer(),
            'class' => 'required-entry',
        ]);

        $this->addColumn('zip_start', [
            'label' => __('Initial ZIP Code'),
            'class' => 'required-entry validate-zip-br validate-number',
        ]);

        $this->addColumn('zip_end', [
            'label' => __('Final ZIP Code'),
            'class' => 'required-entry validate-zip-br validate-number',
        ]);

        $this->addColumn('delivery_time', [
            'label' => __('Delivery Time (days)'),
            'class' => 'required-entry validate-number',
        ]);

        $this->addColumn('price', [
            'label' => __('Price'),
            'class' => 'required-entry validate-number',
        ]);

        $this->addColumn('max_weight', [
            'label' => __('Maximum Weight (kg)'),
            'class' => 'required-entry validate-number',
        ]);

        $this->addColumn('comment', [
            'label' => __('Comment'),
            'class' => 'required-entry',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Prepare existing row data object.
     *
     * @param DataObject $row
     *
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $service = $row->getService();
        if ($service !== null) {
            $options['option_'.$this->getServiceRenderer()->calcOptionHash($service)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Create Block ServiceColumn.
     *
     * @throws LocalizedException
     *
     * @return ServiceColumn
     */
    private function getServiceRenderer()
    {
        if (!$this->serviceRenderer) {
            $this->serviceRenderer = $this->getLayout()->createBlock(
                ServiceColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->serviceRenderer;
    }
}
