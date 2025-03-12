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
 * Class GetDeclaration Button
 * Button for downloading content declaration.
 */
class GetDeclaration implements ButtonProviderInterface
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var PlpSession
     */
    private $plpSession;

    /**
     * @var PlpRepositoryInterface
     */
    private $plpRepository;

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
            'label' => __('Download Declaration'),
            'class' => 'download declaration',
            'on_click' => sprintf("window.location.href = '%s';", $this->getDeclarationUrl()),
            'sort_order' => 30
        ];

        if (!$this->canDownload()) {
            $data['disabled'] = true;
            $data['class'] .= ' disabled';
            $data['title'] = __('Declaration content is only available for completed PLPs');
        }

        return $data;
    }

    /**
     * Get URL for declaration download
     *
     * @return string
     */
    private function getDeclarationUrl()
    {
        $params = ['plp_id' => $this->getPlpId()];
        return $this->getUrl('sigepweb/plp/declaration', $params);
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
     * Check if declaration can be downloaded
     *
     * @return bool
     */
    private function canDownload()
    {
        $plpId = $this->getPlpId();
        
        if (!$plpId) {
            return false;
        }
        
        try {
            $plp = $this->plpRepository->getById($plpId);
            if (!$plp || !$plp->getId()) {
                return false;
            }
            
            return $plp->getStatus() === PlpStatus::STATUS_PLP_COMPLETED;
            
        } catch (\Exception $e) {
            return false;
        }
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
