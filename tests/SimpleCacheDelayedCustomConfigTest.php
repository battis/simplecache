<?php

namespace Tests;

use mysqli;
use DateTime;
use Exception;
use Battis\SimpleCache;
use Battis\SimpleCache_Exception;
use PHPUnit\Framework\TestCase;
use Tests\Wrappers\CacheWrapper as CW;

class SimpleCacheDelayedCustomConfigTest extends SimpleCacheTest
{
    public function testPreInstantiation(CW $wrapper = null)
    {
        if (!isset($wrapper)) {
            $wrapper = new CW();
            $wrapper->names = ['alice', 'bob', 'carol'];
        }

        return parent::testPreInstantiation($wrapper);
    }

    /**
     * @depends testPreInstantiation
     */
    public function testInstantiation(CW $wrapper)
    {
        $wrapper->cache = new SimpleCache();
        $this->assertInstanceOf(SimpleCache::class, $wrapper->cache);

        return $wrapper;
    }

    /**
     * @depends testInstantiation
     */
    public function testUnreadiness(CW $wrapper)
    {
        $this->assertFalse($wrapper->cache->setTableName($wrapper->names[CW::TABLE]));
        $this->assertFalse($wrapper->cache->setKeyName($wrapper->names[CW::KEY]));
        $this->assertFalse($wrapper->cache->setCacheName($wrapper->names[CW::CACHE]));

        try {
            $wrapper->cache->purgeExpired();
        } catch (Exception $e) {
            $this->assertInstanceOf(SimpleCache_Exception::class, $e);
            $this->assertEquals(SimpleCache_Exception::DATABASE_NOT_INITIALIZED, $e->getCode());
        }

        try {
            $wrapper->cache->setCache('foo', 'bar');
        } catch (Exception $e) {
            $this->assertInstanceOf(SimpleCache_Exception::class, $e);
            $this->assertEquals(SimpleCache_Exception::DATABASE_NOT_INITIALIZED, $e->getCode());
        }

        return $wrapper;
    }

    /**
     * @depends testUnreadiness
     */
    public function testDelayedConfiguration(CW $wrapper)
    {
        $this->assertTrue($wrapper->cache->setSqlInfo(
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWORD'],
            $GLOBALS['DB_DBNAME']
        ));
        $this->assertTrue($wrapper->cache->setTableName($wrapper->names[CW::TABLE]));
        $this->assertTrue($wrapper->cache->setKeyName($wrapper->names[CW::KEY]));
        $this->assertTrue($wrapper->cache->setCacheName($wrapper->names[CW::CACHE]));

        return $wrapper;
    }

    /**
     * @depends testDelayedConfiguration
     */
    public function testDatabaseConfiguration(CW $wrapper)
    {
        parent::testDatabaseConfiguration($wrapper);

        return $wrapper;
    }
}
