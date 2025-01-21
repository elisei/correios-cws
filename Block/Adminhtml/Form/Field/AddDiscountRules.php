<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class AddDiscountRules extends AbstractFieldArray
{
    /**
     * Prepare rendering the new field by adding all the needed columns.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _prepareToRender()
    {
        $this->addColumn('zip_start', [
            'label' => __('Initial ZIP Code'),
            'class' => 'required-entry validate-zip-br validate-number',
        ]);

        $this->addColumn('zip_end', [
            'label' => __('Final ZIP Code'),
            'class' => 'required-entry validate-zip-br validate-number',
        ]);

        $this->addColumn('discount', [
            'label' => __('Discount (%)'),
            'class' => 'required-entry validate-number validate-number-range number-range-0-100',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Discount Rule');
    }

    /**
     * Prepare existing row data object.
     *
     * @param DataObject $row
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $row->setData('option_extra_attrs', $options);
    }
}
