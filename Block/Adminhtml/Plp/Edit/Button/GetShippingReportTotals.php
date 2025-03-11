<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Block\Adminhtml\Plp\Edit\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Backend\Block\Widget\Context;
use O2TI\SigepWebCarrier\Model\Session\PlpSession;
use O2TI\SigepWebCarrier\Api\PlpRepositoryInterface;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

/**
 * Class GetShippingReportTotals Button
 * Button for downloading pre-shipping list.
 */
class GetShippingReportTotals implements ButtonProviderInterface
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var PlpSession
     */
    protected $plpSession;

    /**
     * @var PlpRepositoryInterface
     */
    protected $plpRepository;

    /**
     * @param Context $context
     * @param PlpSession $plpSession
     * @param PlpRepositoryInterface $plpRepository
     */
    public function __construct(
        Context $context,
        PlpSession $plpSession,
        PlpRepositoryInterface $plpRepository
    ) {
        $this->context = $context;
        $this->plpSession = $plpSession;
        $this->plpRepository = $plpRepository;
    }

    /**
     * Get button data
     *
     * @return array
     */
    public function getButtonData()
    {
        $plpId = $this->getPlpId();
        
        if (!$plpId) {
            return [];
        }

        $data = [
            'label' => __('Download Report Shipping Totals'),
            'class' => 'download pre-shipping-list',
            'on_click' => sprintf("window.location.href = '%s';", $this->getPreShippingListUrl()),
            'sort_order' => 35
        ];

        return $data;
    }

    /**
     * Get URL for pre-shipping list download
     *
     * @return string
     */
    private function getPreShippingListUrl()
    {
        $params = ['plp_id' => $this->getPlpId()];
        return $this->getUrl('sigepweb/plp/shippingreporttotals', $params);
    }

    /**
     * Get PLP ID from session
     *
     * @return int|null
     */
    private function getPlpId()
    {
        return $this->plpSession->getCurrentPlpId();
    }

    /**
     * Generate URL
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    private function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
