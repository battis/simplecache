<?php

namespace Tests;

use mysqli;
use DateTime;
use Battis\SimpleCache;
use PHPUnit\Framework\TestCase;

class SimpleCacheTests extends TestCase
{
    protected static $mysqli = null;

    protected static $defaultNames = ['cache', 'key', 'cache'];
    protected static $customNames = [
        ['foo', 'bar', 'baz'],
        ['alice', 'bob', 'carol']
    ];

    const TABLE = 0;
    const KEY = 1;
    const CACHE = 2;

    public static function setUpBeforeClass()
    {
        self::$mysqli = new mysqli(
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWORD'],
            $GLOBALS['DB_DBNAME'],
            $GLOBALS['DB_PORT']
        );
        self::$mysqli->query('DROP TABLE IF EXISTS `' . static::$defaultNames[static::TABLE] . '`');
        foreach (static::$customNames as $custom) {
            self::$mysqli->query('DROP TABLE IF EXISTS `' . $custom[static::TABLE] . '`');
        }
    }

    protected function checkMySQLResponse($response)
    {
        $this->assertTrue($response !== false, 'Error ' . self::$mysqli->errno . ': ' . self::$mysqli->error);
    }

    protected function validateDatabaseConfiguration($table, $key, $cache)
    {
        $response = self::$mysqli->query("SHOW CREATE TABLE `$table`");
        $this->checkMySQLResponse($response);

        $row = $response->fetch_assoc();
        $create = preg_replace(
            '/^(CREATE TABLE.*)\sENGINE=.*$/',
            '\1',
            str_replace("\n", '', $row['Create Table'])
        );
        $this->assertEquals("CREATE TABLE `$table` (  " .
            '`id` int(11) unsigned NOT NULL AUTO_INCREMENT,  ' .
            "`$key` text NOT NULL,  " .
            "`$cache` longtext NOT NULL,  " .
            '`expire` timestamp NULL DEFAULT NULL,  ' .
            '`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ' .
            'ON UPDATE CURRENT_TIMESTAMP,  ' .
            'PRIMARY KEY (`id`))',
            $create,
            'Database not configured as expected'
        );
    }

    protected function queryDatabase($key, $cache, $tableName = 'cache', $keyName = 'key', $cacheName = 'cache')
    {
        $response = self::$mysqli->query(
            "SELECT * FROM `$tableName` " .
            "WHERE `$keyName` = '$key' AND " .
            "`$cacheName` = '" . serialize($cache) . "' AND " .
            "`expire` IS NOT NULL"
        );
        $this->checkMySQLResponse($response);
        return $response;
    }

    public function testPreInstantiation()
    {
        foreach (['cache', 'foo'] as $table) {
            $response = self::$mysqli->query("SHOW TABLES LIKE '$table'");
            $this->checkMySQLResponse($response);
            $this->assertEquals(0, $response->num_rows, "`$table` table already present, but should have been dropped");
        }
    }

    /**
     * @depends testPreInstantiation
     */
    public function testInstantiation()
    {
        $cache = new SimpleCache(self::$mysqli);
        $this->assertInstanceOf(SimpleCache::class, $cache);

        return $cache;
    }

    /**
     * @depends testInstantiation
     */
    public function testDatabaseConfiguration(SimpleCache $cache)
    {
        $this->assertTrue($cache->buildCache());
        $this->validateDatabaseConfiguration(...static::$defaultNames);

        return $cache;
    }

    /**
     * @depends testInstantiation
     */
    public function testSetCache(SimpleCache $cache)
    {
        $this->assertTrue($cache->setCache('set-get', 'foo'));

        $response = $this->queryDatabase('set-get', 'foo');
        $this->assertEquals(1, $response->num_rows, '`set-get` not found');

        return $cache;
    }

    /**
     * @depends testSetCache
     */
    public function testGetCache(SimpleCache $cache)
    {
        $data = $cache->getCache('set-get');
        $this->assertEquals('foo', $data, 'Wrong value for `set-get`');

        return $cache;
    }

    /**
     * @depends testSetCache
     */
    public function testGetCacheTimestamp(SimpleCache $cache)
    {
        $timestamp = $cache->getCacheTimestamp('set-get');
        $this->assertInstanceOf(DateTime::class, $timestamp);
        $this->assertLessThanOrEqual(0, (integer) ($timestamp->diff(new DateTime())->format('%s')));
    }

    /**
     * @depends testSetCache
     */
    public function testGetCacheExpiration(SimpleCache $cache)
    {
        $timestamp = $cache->getCacheExpiration('set-get');
        $this->assertInstanceOf(DateTime::class, $timestamp);
        $this->assertGreaterThanOrEqual(0, (integer) ($timestamp->diff(new DateTime())->format('%s')));
    }

    /**
     * @depends testGetCache
     */
    public function testResetCache(SimpleCache $cache)
    {
        $this->assertTrue($cache->resetCache('set-get'));
        $response = $this->queryDatabase('set-get', 'foo');
        $this->assertEquals(0, $response->num_rows, 'reset `set-get` still present');
    }

    /**
     * @depends testInstantiation
     */
    public function testSetCacheWithExpirationPreExpire(SimpleCache $cache)
    {
        $this->assertTrue($cache->setCache('test-expire', 'foo', 1));
        $data = $cache->getCache('test-expire');
        $this->assertEquals('foo', $data, 'Wrong value for `test-expire`');

        return $cache;
    }

    /**
     * @depends testSetCacheWithExpirationPreExpire
     */
    public function testSetCacheWithLifetimePreExpire(SimpleCache $cache)
    {
        $cache->setLifetime(1);
        $this->assertTrue($cache->setCache('test-lifetime', 'foo'));
        $data = $cache->getCache('test-lifetime');
        $this->assertEquals('foo', $data, 'wrong value for `test-lifetime`');

        return $cache;
    }

    /**
     * @depends testSetCacheWithLifetimePreExpire
     */
    public function testExpire(SimpleCache $cache)
    {
        sleep(2);

        return $cache;
    }

    /**
     * @depends testExpire
     */
    public function testGetCacheWithExpirationPostExpire(SimpleCache $cache)
    {
        $data = $cache->getCache('test-expire');
        $this->assertFalse($data, '`test-expire` not expired');

        return $cache;
    }

    /**
     * @depends testGetCacheWithExpirationPostExpire
     */
    public function testGetCacheWithLifetimePostExpire(SimpleCache $cache)
    {
        $data = $cache->getCache('test-lifetime');
        $this->assertFalse($data, '`test-lifetime` not expired');

        return $cache;
    }

    /**
     * @depends testGetCacheWithLifetimePostExpire
     */
    public function testPurgeExpired(SimpleCache $cache)
    {
        $keys = ['test-expire', 'test-lifetime'];
        foreach ($keys as $key) {
            $response = $this->queryDatabase($key, 'foo');
            $this->assertEquals(1, $response->num_rows, "expired `$key` missing");
        }

        $cache->purgeExpired();

        foreach ($keys as $key) {
            $response = $this->queryDatabase($key, 'foo');
            $this->assertEquals(0, $response->num_rows, "purged `$key` still present");
        }
    }

    public function testCustomInstantiation()
    {
        $names = static::$customNames[0];
        $cache = new SimpleCache(self::$mysqli, ...$names);
        $this->assertInstanceOf(SimpleCache::class, $cache);

        $this->assertTrue($cache->buildCache());
        $this->validateDatabaseConfiguration(...$names);

        return $cache;
    }

    /**
     * @depends testCustomInstantiation
     */
    public function testCustomSetCache(SimpleCache $cache)
    {
        $this->assertTrue($cache->setCache('custom-set-get', 'bar'));
        $response = $this->queryDatabase('custom-set-get', 'bar', ...static::$customNames[0]);
        $this->assertEquals(1, $response->num_rows, '`custom-set-get` not found');

        return $cache;
    }

    /**
     * @depends testCustomSetCache
     */
    public function testCustomGetCache(SimpleCache $cache)
    {
        $data = $cache->getCache('custom-set-get');
        $this->assertEquals('bar', $data, 'Wrong value for `custom-set-get`');
    }

    public function testDelayedCustomInstantiation()
    {
        $names = static::$customNames[1];
        $cache = new SimpleCache(self::$mysqli);
        $this->assertTrue($cache->setTableName($names[self::TABLE]));
        $this->assertTrue($cache->setKeyName($names[self::KEY]));
        $this->assertTrue($cache->setCacheName($names[self::CACHE]));
        $this->assertTrue($cache->buildCache());
        $this->validateDatabaseConfiguration(...$names);
    }

    public static function tearDownAfterClass()
    {
        self::$mysqli->close();
        self::$mysqli = null;
    }
}
