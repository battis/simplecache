<?php
/** SimpleCache class */

namespace Battis;

use mysqli;

/**
 * An object to manage a simple cache
 *
 * The cache is backed by a MySQL database, with data stored as `key => data`
 * pairs. All data stored must be serializable.
 *
 * @author Seth Battis <seth@battis.net>
 **/
class SimpleCache
{

    /** Default cache lifetime (1 hour) */
    const DEFAULT_LIFETIME = 3600; /* 60 seconds * 60 minutes = 1 hour */

    /** Cache length never expires */
    const IMMORTAL_LIFETIME = 0;

    /** MySQL Timestamp format */
    const MYSQL_TIMESTAMP = 'Y-m-d H:i:s';

    const DEFAULT_TABLE_NAME = 'cache';
    const DEFAULT_KEY_NAME = 'key';
    const DEFAULT_CACHE_NAME = 'cache';

    /** @var mysqli MySQL database handle */
    protected $sql = null;

    /** @var boolean If the cache database tables have been created */
    protected $initialized = false;

    /** @var string Cache table in database */
    protected $table = self::DEFAULT_TABLE_NAME;

    /** @var string Cache key field in database */
    protected $key = self::DEFAULT_KEY_NAME;

    /** @var string Cache storage field in database */
    protected $cache = self::DEFAULT_CACHE_NAME;

    /** @var int Cache lifetime */
    protected $lifetime = self::DEFAULT_LIFETIME;

    /**
     * Create a a new SimpleCache
     *
     * @param mysqli $sql (Optional)
     * @param string $table (Optional) Cache table name in database
     * @param string $key (Optional) Cache key field name in database
     * @param string $cache (Optional) Cache storage field name in database
     * @param boolean $purge (Optional) Automatically purge expired items upon
     *        construction (Defaults to `FALSE`)
     **/
    public function __construct($sql = null, $table = null, $key = null, $cache = null, $purge = false)
    {
        if (isset($sql)) {
            $this->setSql($sql);
        }
        if (!empty($table)) {
            $this->setTableName($table);
        }
        if (!empty($key)) {
            $this->setKeyName($key);
        }
        if (!empty($cache)) {
            $this->setCacheName($cache);
        }

        if ($purge) {
            $this->purgeExpired();
        }
    }

    /**
     * Set up database connection
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $database
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure
     **/
    public function setSqlInfo($host, $user, $password, $database)
    {
        return $this->setSql(new mysqli($host, $user, $password, $database));
    }

    /**
     * Set up database connection
     *
     * @param mysqli $sql
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure
     **/
    public function setSql($sql)
    {
        if (is_a($sql, mysqli::class)) {
            $this->sql = $sql;
            return true;
        }
        return false;
    }

    /**
     * Validate a potential MySQL token
     *
     * @param string $token
     * @return boolean If the SQL backing database is not yet initialized, no
     *                    tokens will validate.
     */
    protected function validateToken($token)
    {
        if ($this->sql) {
            return (strlen($token) > 0 && $token == $this->sql->real_escape_string($token));
        }
        return false;
    }

    /**
     * The name of the database table backing the cache
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->table;
    }

    /**
     * Set cache table name
     *
     * @param string $table
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure (e.g.
     *                         invalid table name)
     **/
    public function setTableName($table)
    {
        if ($this->validateToken($table)) {
            $this->table = $table;
            return true;
        }
        return false;
    }

    /**
     * The name of the key field in the database table
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->key;
    }

    /**
     * Set cache key field name
     *
     * @param string $key
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure (e.g.
     *                         invalid key field name)
     **/
    public function setKeyName($key)
    {
        if ($this->validateToken($key)) {
            $this->key = $key;
            return true;
        }
        return false;
    }

    /**
     * The name of the cache field in the database table
     *
     * @return string
     */
    public function getCacheName()
    {
        return $this->cache;
    }

    /**
     * Set cache storage field name
     *
     * @param string $cache
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure (e.g.
     *                         invalid cache field name)
     **/
    public function setCacheName($cache)
    {
        if ($this->validateToken($cache)) {
            $this->cache = $cache;
            return true;
        }
        return false;
    }

    /**
     * Get the default cache lifetime in seconds
     *
     * @return int
     */
    public function getLifetime()
    {
        return $this->lifetime;
    }

    /**
     * Set default lifetime of cached data
     *
     * This lifetime will be used for all new and updated caches that do not
     * explicitly override it.
     *
     * @param int $lifetimeInSeconds Defaults to DEFAULT_LIFETIME, values less
     *                               than zero are treated as zero
     *
     * @return void
     *
     * @see setCache() setCache()
     * @see purgeExpired() purgeExpired()
     **/
    public function setLifetime($lifetimeInSeconds = self::DEFAULT_LIFETIME)
    {
        $this->lifetime = max(0, intval($lifetimeInSeconds));
    }

    /**
     * Test for database connection initialization
     *
     * If the database connection is not initalized, attempt to do so.
     *
     * @return boolean Returns `TRUE` if database connection is ready
     *
     * @throws SimpleCache_Exception DATABASE_NOT_INITIALIZED If the backing
     *        database connection cannot be initialized
     **/
    protected function requireSqlInitialized()
    {
        if (!$this->initialized) {
            if (!$this->buildCache()) {
                throw new SimpleCache_Exception(
                    'Backing database not initialized',
                    SimpleCache_Exception::DATABASE_NOT_INITIALIZED
                );
            }
        }
        return true;
    }

    /**
     * Create cache table in database (called automatically as a prerequisite
     * for any cache access operations, but accessible publicly, for early
     * setup, if so desired)
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure
     **/
    public function buildCache()
    {
        if (!$this->initialized && $this->sql) {
            $this->initialized = $this->sql->query("
                CREATE TABLE IF NOT EXISTS `{$this->table}` (
                    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                    `{$this->key}` text NOT NULL,
                    `{$this->cache}` longtext NOT NULL,
                    `expire` timestamp NULL DEFAULT NULL,
                    `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                );"
            );
        }
        return $this->initialized;
    }

    /**
     * Get available cached data
     *
     * @param string $key
     *
     * @return mixed|boolean Returns the cached data or `FALSE` if no data cached
     **/
    public function getCache($key)
    {
        $this->requireSqlInitialized();
        $_key = $this->sql->real_escape_string($key);
        if ($this->lifetime == self::IMMORTAL_LIFETIME) {
            if ($response = $this->sql->query("
                SELECT *
                    FROM `{$this->table}`
                    WHERE
                        `{$this->key}` = '$_key' AND
                        `expire` IS NULL
            ")) {
                if ($cache = $response->fetch_assoc()) {
                    return unserialize($cache[$this->cache]);
                }
            }
        } else {
            $liveCache = date('Y-m-d H:i:s', time() - $this->lifetime);
            if ($response = $this->sql->query("
                SELECT *
                    FROM `{$this->table}`
                    WHERE
                        `{$this->key}` = '$_key' AND (
                            (
                                `timestamp` > '{$liveCache}' AND
                                `expire` IS NULL
                            ) OR (
                                `expire` > NOW()
                            )
                        )
            ")) {
                if ($cache = $response->fetch_assoc()) {
                    return unserialize($cache[$this->cache]);
                }
            }
        }
        return false;
    }

    /**
     * Calculate the expiration timestamp
     *
     * @param  integer|false $lifetimeInSeconds (Optional, defaults to `FALSE`)
     * @return string|false Either a MySQL timestamp for the expiration date or
     *                             `FALSE` if the lifetime was set to
     *                             `IMMORTAL_LIFETIME`
     */
    protected function getExpirationTimestamp($lifetimeInSeconds = false)
    {
        if ($lifetimeInSeconds === false) {
            $lifetimeInSeconds = $this->lifetime;
        }
        if ($lifetimeInSeconds === self::IMMORTAL_LIFETIME) {
            return false;
        } else {
            return date(self::MYSQL_TIMESTAMP, time() + $lifetimeInSeconds);
        }
    }

    /**
     * Purge expired cache data
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure
     *
     * @see setLifetime() setLifetime()
     **/
    public function purgeExpired()
    {
        $this->requireSqlInitialized();
        return ($this->sql->query("
            DELETE
                FROM `{$this->table}`
                WHERE `expire` < NOW()
        ") !== false);
    }

    /**
     * Store data in cache
     *
     * @param string $key
     * @param mixed $data Must be serializable
     * @param int|false $lifetimeInSeconds (Optional)
     *
     * @return true Returns `TRUE` on success, `FALSE` on failure
     *
     * @throws SimpleCache_Exception CACHE_WRITE if the cache cannot be written
     *         (or updated)
     *
     * @see setLifetime() setLifetime()
     **/
    public function setCache($key, $data, $lifetimeInSeconds = false)
    {
        $this->requireSqlInitialized();

        /* escape query data */
        $_key = $this->sql->real_escape_string($key);
        $_data = $this->sql->real_escape_string(serialize($data));
        $_expire = $this->getExpirationTimestamp($lifetimeInSeconds);

        $response = $this->sql->query("
            SELECT *
                FROM `{$this->table}`
                WHERE
                    `{$this->key}` = '$_key'
        ");
        if ($response->fetch_assoc()) {
            if ($this->sql->query("
                UPDATE
                    `{$this->table}`
                    SET
                        `{$this->cache}` = '$_data',
                        `expire` = " . ($_expire === false ? 'NULL' : "'$_expire'") . "
                    WHERE
                        `{$this->key}` = '$_key'
            ")) {
                return true;
            } else {
                throw new SimpleCache_Exception(
                    'Could not update existing cache data. ' . $this->sql->error,
                    SimpleCache_Exception::CACHE_WRITE
                );
            }
        } elseif ($this->sql->query("
            INSERT
                INTO `{$this->table}`
                (
                    `{$this->key}`,
                    `{$this->cache}`
                    " . ($_expire === false ? '' : ", `expire`") . "
                ) VALUES (
                    '$_key',
                    '$_data'
                    " . ($_expire === false ? '' : ", '$_expire'") . "
                )
        ")) {
            return true;
        } else {
            throw new SimpleCache_Exception(
                'Could not insert new cache data. ' . $this->sql->error,
                SimpleCache_Exception::CACHE_WRITE
            );
        }
    }

    /**
     * Reset (empty) cached data
     *
     * @param string $key
     *
     * @return boolean Returns `TRUE` on success, `FALSE` on failure
     **/
    public function resetCache($key)
    {
        $this->requireSqlInitialized();
        $_key = $this->sql->real_escape_string($key);
        return ($this->sql->query("
            DELETE
                FROM `{$this->table}`
                WHERE
                    `{$this->key}` = '$_key'
        ") !== false);
    }

    /**
     * Get the timestamp of the cached data
     *
     * @param string $key
     *
     * @return \DateTime|boolean Returns `FALSE` on invalid key
     **/
    public function getCacheTimestamp($key)
    {
        $this->requireSqlInitialized();
        $_key = $this->sql->real_escape_string($key);
        if ($response = $this->sql->query("
            SELECT *
                FROM `{$this->table}`
                WHERE
                    `{$this->key}` = '$_key'
        ")) {
            if ($response->num_rows > 0) {
                return new \DateTime($response->fetch_assoc()['timestamp']);
            }
        }
    }

    /**
     * Get the expiration date of the cached data
     *
     * @param string $key
     *
     * @return \DateTime|boolean Returns `FALSE` on invalid key
     **/
    public function getCacheExpiration($key)
    {
        $this->requireSqlInitialized();
        $_key = $this->sql->real_escape_string($key);
        if ($response = $this->sql->query("
            SELECT *
                FROM `{$this->table}`
                WHERE
                    `{$this->key}` = '$_key'
        ")) {
            if ($response->num_rows > 0) {
                return new \DateTime($response->fetch_assoc()['expire']);
            }
        }
    }
}
