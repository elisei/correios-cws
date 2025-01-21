<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Block class for testing SigepWeb credentials
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class TestCredentials extends Field
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'O2TI_SigepWebCarrier::system/config/test_credentials.phtml';

    /**
     * Remove scope label and use default/website checkboxes
     *
     * @param AbstractElement $element Element instance
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get element HTML
     *
     * @param AbstractElement $element Element instance
     * @return string
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get URL for AJAX request
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('sigepweb/system_config/testcredentials');
    }

    /**
     * Get HTML for the test button
     *
     * @return string
     */
    public function getButtonHtml()
    {
        /** @var \Magento\Backend\Block\Widget\Button $button */
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => 'test_credentials_button',
                'label' => __('Test your Credentials'),
            ]
        );

        return $button->toHtml();
    }
}
