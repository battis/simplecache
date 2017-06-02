<?php

namespace Tests;

use mysqli;
use DateTime;
use Battis\HierarchicalSimpleCache;
use PHPUnit\Framework\TestCase;
use Tests\Wrappers\CacheWrapper;
use Tests\Wrappers\HierarchicalCacheWrapper as HCW;

class HierarchicalSimpleCacheCustomConfigTest extends HierarchicalSimpleCacheTest
{
    public function testPreInstantiation(CacheWrapper $wrapper = null)
    {
        if (!isset($wrapper)) {
            $wrapper = new HCW();
            $wrapper->names = ['hier_foo', 'bar', 'baz'];
            $wrapper->hierarchy = ['foo', '#', '%'];
        }

        return parent::testPreInstantiation($wrapper);
    }

    /**
     * @depends testPreInstantiation
     */
    public function testInstantiation(CacheWrapper $wrapper)
    {
        $wrapper->cache = new HierarchicalSimpleCache(
            self::$mysqli,
            $wrapper->hierarchy[HCW::BASE],
            $wrapper->hierarchy[HCW::DELIMITER],
            $wrapper->names[HCW::TABLE],
            $wrapper->names[HCW::KEY],
            $wrapper->names[HCW::CACHE],
            true,
            $wrapper->hierarchy[HCW::PLACEHOLDER]
        );
        $this->assertInstanceOf(HierarchicalSimpleCache::class, $wrapper->cache);

        return $wrapper;
    }
}
