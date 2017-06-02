<?php

namespace Tests;

use mysqli;
use DateTime;
use Battis\SimpleCache;
use Battis\HierarchicalSimpleCache;
use PHPUnit\Framework\TestCase;
use Tests\Wrappers\CacheWrapper;
use Tests\Wrappers\HierarchicalCacheWrapper as HCW;

class HierarchicalSimpleCacheTest extends SimpleCacheTest
{
    protected function checkDbCache(
        CacheWrapper $wrapper,
        $key,
        $data,
        $tableName = false,
        $keyName = false,
        $cacheName = false
    ) {
        $this->assertInstanceOf(HierarchicalSimpleCache::class, $wrapper->cache);
        return parent::checkDbCache(
            $wrapper,
            $wrapper->cache->getHierarchicalKey($key),
            $data,
            $tableName,
            $keyName,
            $cacheName
        );
    }

    public function testPreInstantiation(CacheWrapper $wrapper = null)
    {
        if (!isset($wrapper)) {
            $wrapper = new HCW();
        }

        return parent::testPreInstantiation($wrapper);
    }

    /**
     * @depends testPreInstantiation
     */
    public function testInstantiation(CacheWrapper $wrapper)
    {
        $wrapper->cache = new HierarchicalSimpleCache(self::$mysqli);
        $this->assertInstanceOf(HierarchicalSimpleCache::class, $wrapper->cache);

        return $wrapper;
    }

    /**
     * @depends testInstantiation
     */
    public function testHierarchicalValues(CacheWrapper $wrapper)
    {
        $this->assertEquals($wrapper->hierarchy[HCW::BASE], $wrapper->cache->getBase());
        $this->assertEquals($wrapper->hierarchy[HCW::DELIMITER], $wrapper->cache->getDelimiter());
        $this->assertEquals($wrapper->hierarchy[HCW::PLACEHOLDER], $wrapper->cache->getPlaceholder());
    }
}
