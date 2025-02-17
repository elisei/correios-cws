<?php
namespace O2TI\SigepWebCarrier\Model\Session;

use Magento\Framework\Session\SessionManager;

class PlpSession extends SessionManager
{
    public const CURRENT_PLP_ID = 'current_plp_id';

    /**
     * Set current PLP ID
     *
     * @param int $plpId
     * @return void
     */
    public function setCurrentPlpId($plpId)
    {
        $this->setData(self::CURRENT_PLP_ID, $plpId);
    }

    /**
     * Get current PLP ID
     *
     * @return int|null
     */
    public function getCurrentPlpId()
    {
        return $this->getData(self::CURRENT_PLP_ID);
    }
}