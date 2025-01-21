<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\StateInterface;
use O2TI\SigepWebCarrier\Model\Cache\Type\CorreiosResponseCache;

class ResponseCache
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var StateInterface
     */
    private $cacheState;

    /**
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param TypeListInterface $cacheTypeList
     * @param StateInterface $cacheState
     */
    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        TypeListInterface $cacheTypeList,
        StateInterface $cacheState
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheState = $cacheState;
    }

    /**
     * Get cached response api data
     *
     * @param string $key
     * @return array|null
     */
    public function get(string $key): ?array
    {
        if (!$this->cacheState->isEnabled(CorreiosResponseCache::TYPE_IDENTIFIER)) {
            return null;
        }

        $cached = $this->cache->load($this->getCacheKey($key));
        if ($cached === false) {
            return null;
        }

        return $this->serializer->unserialize($cached);
    }

    /**
     * Save response api data to cache
     *
     * @param string $key
     * @param array $data
     * @return bool
     */
    public function save(string $key, array $data): bool
    {
        if (!$this->cacheState->isEnabled(CorreiosResponseCache::TYPE_IDENTIFIER)) {
            return false;
        }

        $serialized = $this->serializer->serialize($data);

        return $this->cache->save(
            $serialized,
            $this->getCacheKey($key),
            [CorreiosResponseCache::CACHE_TAG],
            CorreiosResponseCache::CACHE_LIFETIME
        );
    }

    /**
     * Remove specific cache entry
     *
     * @param string $key
     * @return bool
     */
    public function remove(string $key): bool
    {
        return $this->cache->remove($this->getCacheKey($key));
    }

    /**
     * Clean all response api cache
     *
     * @return void
     */
    public function clean(): void
    {
        $this->cacheTypeList->cleanType(CorreiosResponseCache::TYPE_IDENTIFIER);
    }

    /**
     * Generate cache key
     *
     * @param string $key
     * @return string
     */
    private function getCacheKey(string $key): string
    {
        return CorreiosResponseCache::CACHE_TAG . '_' . $key;
    }
}
