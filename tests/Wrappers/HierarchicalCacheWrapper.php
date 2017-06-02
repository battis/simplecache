<?php

namespace Tests\Wrappers;

use Battis\HierarchicalSimpleCache;

class HierarchicalCacheWrapper extends CacheWrapper
{
    const BASE = 0;
    const DELIMITER = 1;
    const PLACEHOLDER = 2;

    public $hierarchy = [
        HierarchicalSimpleCache::DEFAULT_DELIMITER,
        HierarchicalSimpleCache::DEFAULT_DELIMITER,
        HierarchicalSimpleCache::DEFAULT_PLACEHOLDER
    ];
}
