<?php

namespace Tests;

use mysqli;
use DateTime;
use Exception;
use Battis\SimpleCache;
use Battis\SimpleCache_Exception;
use PHPUnit\Framework\TestCase;
use Tests\Wrappers\CacheWrapper as CW;

class SimpleCacheTest extends TestCase
{
    protected static $mysqli = null;

    const SHORT_EXPIRE = 1;

    public static function setUpBeforeClass()
    {
        self::$mysqli = new mysqli(
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWORD'],
            $GLOBALS['DB_DBNAME'],
            $GLOBALS['DB_PORT']
        );
    }

    protected function checkMySQLResponse($response)
    {
        $this->assertTrue($response !== false, 'Error ' . self::$mysqli->errno . ': ' . self::$mysqli->error);
    }

    protected function checkDbTableDefinition($table, $key, $cache)
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

    protected function checkDbCache(
        CW $wrapper,
        $key,
        $data,
        $tableName = false,
        $keyName = false,
        $cacheName = false
    ) {
        $tableName = ($tableName === false ? $wrapper->names[CW::TABLE] : $tableName);
        $keyName = ($keyName === false ? $wrapper->names[CW::KEY] : $keyName);
        $cacheName = ($cacheName === false ? $wrapper->names[CW::CACHE] : $cacheName);

        $response = self::$mysqli->query(
            "SELECT * FROM `$tableName` " .
            "WHERE `$keyName` = '$key' AND " .
            "`$cacheName` = '" . serialize($data) . "' AND " .
            "`expire` IS NOT NULL"
        );
        $this->checkMySQLResponse($response);
        return $response;
    }

    public function testPreInstantiation(CW $wrapper = null)
    {
        if (!isset($wrapper)) {
            $wrapper = new CW();
        }

        self::$mysqli->query('DROP TABLE IF EXISTS `' . $wrapper->names[CW::TABLE] . '`');

        return $wrapper;
    }

    /**
     * @depends testPreInstantiation
     */
    public function testInstantiation(CW $wrapper)
    {
        $wrapper->cache = new SimpleCache(self::$mysqli);
        $this->assertInstanceOf(SimpleCache::class, $wrapper->cache);

        return $wrapper;
    }

    /**
     * @depends testInstantiation
     */
    public function testDatabaseConfiguration(CW $wrapper)
    {
        $this->assertTrue($wrapper->cache->buildCache());
        $this->checkDbTableDefinition(...$wrapper->names);

        return $wrapper;
    }

    /**
     * @depends testDatabaseConfiguration
     */
    public function testBadSqlInstance(CW $wrapper)
    {
        $this->assertFalse($wrapper->cache->setSql(false));
    }

    /**
     * @depends testDatabaseConfiguration
     */
    public function testNames(CW $wrapper)
    {
        $this->assertEquals($wrapper->names[CW::TABLE], $wrapper->cache->getTableName());
        $this->assertEquals($wrapper->names[CW::KEY], $wrapper->cache->getKeyName());
        $this->assertEquals($wrapper->names[CW::CACHE], $wrapper->cache->getCacheName());
        $this->assertEquals(SimpleCache::DEFAULT_LIFETIME, $wrapper->cache->getLifetime());
    }

    /**
     * @depends testDatabaseConfiguration
     */
    public function testBadNames(CW $wrapper)
    {
        foreach (['setTableName', 'setKeyName', 'setCacheName'] as $method) {
            try {
                $wrapper->cache->$method("foo'");
            } catch (Exception $e) {
                $this->assertInstanceOf(SimpleCache_Exception::class, $e);
            }
        }
    }

    /**
     * @depends testDatabaseConfiguration
     */
    public function testSetCache(CW $wrapper)
    {
        $this->assertTrue($wrapper->cache->setCache(CW::SET_GET, $wrapper->values[CW::SET_GET]));

        $response = $this->checkDbCache($wrapper, CW::SET_GET, $wrapper->values[CW::SET_GET]);
        $this->assertEquals(1, $response->num_rows, '`' . CW::SET_GET . '` not found');

        return $wrapper;
    }

    /**
     * @depends testSetCache
     */
    public function testGetCache(CW $wrapper)
    {
        $data = $wrapper->cache->getCache(CW::SET_GET);
        $this->assertEquals($wrapper->values[CW::SET_GET], $data, 'Wrong value for `' . CW::SET_GET . '`');

        return $wrapper;
    }

    /**
     * @depends testSetCache
     */
    public function testGetCacheTimestamp(CW $wrapper)
    {
        $timestamp = $wrapper->cache->getCacheTimestamp(CW::SET_GET);
        $this->assertInstanceOf(DateTime::class, $timestamp);
        $this->assertGreaterThanOrEqual(0, (integer) ($timestamp->diff(new DateTime())->format('%s')));
    }

    /**
     * @depends testSetCache
     */
    public function testGetCacheExpiration(CW $wrapper)
    {
        $timestamp = $wrapper->cache->getCacheExpiration(CW::SET_GET);
        $this->assertInstanceOf(DateTime::class, $timestamp);
        $this->assertLessThanOrEqual(0, (integer) ($timestamp->diff(new DateTime())->format('%s')));
    }

    /**
     * @depends testGetCache
     */
    public function testResetCache(CW $wrapper)
    {
        $this->assertTrue($wrapper->cache->resetCache(CW::SET_GET));
        $response = $this->checkDbCache($wrapper, CW::SET_GET, $wrapper->values[CW::SET_GET]);
        $this->assertEquals(0, $response->num_rows, 'reset `' . CW::SET_GET . '` still present');
    }

    /**
     * @depends testDatabaseConfiguration
     */
    public function testSetCacheWithExpirationPreExpire(CW $wrapper)
    {
        $this->assertTrue($wrapper->cache->setCache(
            CW::EXPIRE,
            $wrapper->values[CW::EXPIRE], self::SHORT_EXPIRE
        ));
        $data = $wrapper->cache->getCache(CW::EXPIRE);
        $this->assertEquals($wrapper->values[CW::EXPIRE], $data, 'Wrong value for `' . CW::EXPIRE . '`');

        return $wrapper;
    }

    /**
     * @depends testSetCacheWithExpirationPreExpire
     */
    public function testSetCacheWithLifetimePreExpire(CW $wrapper)
    {
        $wrapper->cache->setLifetime(self::SHORT_EXPIRE);
        $this->assertTrue($wrapper->cache->setCache(CW::LIFETIME, $wrapper->values[CW::LIFETIME]));
        $data = $wrapper->cache->getCache(CW::LIFETIME);
        $this->assertEquals(
            $wrapper->values[CW::LIFETIME],
            $data,
            'wrong value for `' . CW::LIFETIME . '`'
        );

        return $wrapper;
    }

    /**
     * @depends testSetCacheWithLifetimePreExpire
     */
    public function testExpire(CW $wrapper)
    {
        sleep(self::SHORT_EXPIRE + 1);

        return $wrapper;
    }

    /**
     * @depends testExpire
     */
    public function testGetCacheWithExpirationPostExpire(CW $wrapper)
    {
        $data = $wrapper->cache->getCache(CW::EXPIRE);
        $this->assertFalse($data, '`' . CW::EXPIRE . '` not expired');

        return $wrapper;
    }

    /**
     * @depends testGetCacheWithExpirationPostExpire
     */
    public function testGetCacheWithLifetimePostExpire(CW $wrapper)
    {
        $data = $wrapper->cache->getCache(CW::LIFETIME);
        $this->assertFalse($data, '`' . CW::LIFETIME . '` not expired');

        return $wrapper;
    }

    /**
     * @depends testGetCacheWithLifetimePostExpire
     */
    public function testPurgeExpired(CW $wrapper)
    {
        $keys = [CW::EXPIRE, CW::LIFETIME];
        foreach ($keys as $key) {
            $response = $this->checkDbCache($wrapper, $key, $wrapper->values[$key]);
            $this->assertEquals(1, $response->num_rows, "expired `$key` missing");
        }

        $wrapper->cache->purgeExpired();

        foreach ($keys as $key) {
            $response = $this->checkDbCache($wrapper, $key, $wrapper->values[$key]);
            $this->assertEquals(0, $response->num_rows, "purged `$key` still present");
        }
    }

    public static function tearDownAfterClass()
    {
        /* intentionally leaving detritus of testing in database for analysis */
        self::$mysqli->close();
        self::$mysqli = null;
    }
}
