<?php

namespace Tests\Wrappers;

use Battis\SimpleCache;

class CacheWrapper
{
    public $cache = null;

    const TABLE = 0;
    const KEY = 1;
    const CACHE = 2;
    public $names = [
        SimpleCache::DEFAULT_TABLE_NAME,
        SimpleCache::DEFAULT_KEY_NAME,
        SimpleCache::DEFAULT_CACHE_NAME
    ];

    const SET_GET = 'set-get';
    const EXPIRE = 'test-expire';
    const LIFETIME = 'test-lifetime';
    public $values = [
        'set-get' => 'foo',
        'test-expire' => 'bar',
        'test-lifetime' => 'baz'
    ];
}
