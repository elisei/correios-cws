<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Session;

use Magento\Framework\Session\SessionManager;

class PlpSession extends SessionManager
{
    public const CURRENT_PLP_ID = 'current_plp_id';

    /**
     * Set current PPN ID
     *
     * @param int $plpId
     * @return void
     */
    public function setCurrentPlpId($plpId)
    {
        $this->storage->setData(self::CURRENT_PLP_ID, $plpId);
    }

    /**
     * Get current PPN ID
     *
     * @return int|null
     */
    public function getCurrentPlpId()
    {
        return $this->storage->getData(self::CURRENT_PLP_ID);
    }
}
