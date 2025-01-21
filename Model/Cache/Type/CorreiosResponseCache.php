<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * System / Cache Management / Cache type "Correios API Response Cache".
 */
class CorreiosResponseCache extends TagScope
{
    /**
     * Cache type code unique among all cache types.
     */
    public const TYPE_IDENTIFIER = 'correios_response';

    /**
     * The tag name that limits the cache cleaning scope within a particular tag.
     */
    public const CACHE_TAG = 'CORREIOS_RESPONSE';

    /**
     * The lifetime from cache.
     */
    public const CACHE_LIFETIME = 86400;

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct(
            $cacheFrontendPool->get(self::TYPE_IDENTIFIER),
            self::CACHE_TAG
        );
    }
}
