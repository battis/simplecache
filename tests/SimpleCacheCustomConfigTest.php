<?php

namespace Tests;

use mysqli;
use DateTime;
use Battis\SimpleCache;
use PHPUnit\Framework\TestCase;
use Tests\Wrappers\CacheWrapper as CW;

class SimpleCacheCustomConfigTest extends SimpleCacheTest
{
    public function testPreInstantiation(CW $wrapper = null)
    {
        if (!isset($wrapper)) {
            $wrapper = new CW();
            $wrapper->names = ['foo', 'bar', 'baz'];
        }

        return parent::testPreInstantiation($wrapper);
    }

    /**
     * @depends testPreInstantiation
     */
    public function testInstantiation(CW $wrapper)
    {
        $wrapper->cache = new SimpleCache(
            self::$mysqli,
            $wrapper->names[CW::TABLE],
            $wrapper->names[CW::KEY],
            $wrapper->names[CW::CACHE],
            true
        );
        $this->assertInstanceOf(SimpleCache::class, $wrapper->cache);

        return $wrapper;
    }
}
