<?php

namespace Battis;

use mysqli;

/**
 * An object to manage a simple cache
 *
 * The cache is backed by a MySQL database, with data stored as `key => data`
 * pairs. All data stored is must be serializable.
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

	/** @var mysqli MySQL database handle */
	protected $sql = null;

	/** @var boolean If the cache database tables have been created */
	protected $initialized = false;

	/** @var string Cache table in database */
	protected $table = 'cache';

	/** @var string Cache key field in database */
	protected $key = 'key';

	/** @var string Cache storage field in database */
	protected $cache = 'cache';

	/** @var int Cache lifetime */
	protected $lifetime = SimpleCache::DEFAULT_LIFETIME;

	/**
	 * Create a a new SimpleCache
	 *
	 * @param mysqli $sql (Optional)
	 * @param string $table (Optional) Cache table name in database
	 * @param string $key (Optional) Cache key field name in database
	 * @param string $cache (Optional) Cache storage field name in database
	 * @param boolean $purge (Optional) Automatically purge expired items upon
	 *		construction (Defaults to `FALSE`)
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
	 * @return boolean
	 */
	protected function validateToken($token) {
		return ($token == $this->sql->real_escape_string($token));
	}

	/**
	 * Set cache table name
	 *
	 * @param string $table
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 **/
	public function setTableName($table)
	{
		if ($this->sql) {
			if ($this->validateToken($table)) {
				$this->table = $table;
				return true;
			} else {
				throw new SimpleCache_Exception("`$table` is not a valid table name");
			}
		}
		return false;
	}

	/**
	 * Set cache key field name
	 *
	 * @param string $key
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 **/
	public function setKeyName($key)
	{
		if ($this->sql) {
			if ($this->validateToken($key)) {
				$this->key = $key;
				return true;
			} else {
				throw new SimpleCache_Exception("`$key` is not a valid field name");
			}
		}
		return false;
	}

	/**
	 * Set cache storage field name
	 *
	 * @param string $cache
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 **/
	public function setCacheName($cache)
	{
		if ($this->sql) {
			if ($this->validateToken($cache)) {
				$this->cache = $cache;
				return true;
			} else {
				throw new SimpleCache_Exception("`$cache` is not a valid field name");
			}
		}
		return false;
	}

	/**
	 * Set default lifetime of cached data
	 *
	 * This lifetime will be used for all new and updated caches that do not
	 * explicitly override it.
	 *
	 * @param int $lifetimeInSeconds Defaults to DEFAULT_LIFETIME, values less than
	 *		zero are treated as zero
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
	 *		database connection cannot be initialized
	 **/
	protected function sqlInitialized()
	{
		if (!$this->initialized) {
			if (!$this->buildCache()) {
				throw new SimpleCache_Exception(
					'Backing database not initialized',
					SimpleCache_Exception::DATABASE_NOT_INITALIZED
				);
			}
		}
		return true;
	}

	/**
	 * Create cache table in database
	 *
	 * @param string $table (Optional) Cache table name
	 * @param string $key (Optional) Cache key field name
	 * @param string $cache (Optional) Cache storage field name
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 **/
	public function buildCache($table = null, $key = null, $cache = null)
	{
		if ($this->sql &&
			($table === null || $this->setTableName($table)) &&
			($key === null || $this->setKeyName($key)) &&
			($cache === null || $this->setCacheName($cache))) {
			if ($this->sql->query("
				CREATE TABLE IF NOT EXISTS `{$this->table}` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`{$this->key}` text NOT NULL,
					`{$this->cache}` longtext NOT NULL,
					`expire` timestamp NULL DEFAULT NULL,
					`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`)
				);
			")) {
				$this->initialized = true;

				/* Upgrade older cache tables for longer cache data */
				$this->sql->query("
					ALTER TABLE `{$this->table}` CHANGE `{$this->cache}` `{$this->cache}` longtext NOT NULL;
				");

				/* Upgrade older cached tables to include an expire column for each row */
				$this->sql->query("
					ALTER TABLE `{$this->table}` ADD `expire` timestamp NULL DEFAULT NULL AFTER `{$this->cache};
				");
			}
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
		if ($this->sqlInitialized()) {
			if ($this->sql) {
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
			}
			return false;
		}
	}

	/**
	 * Purge expired cache data
	 *
	 * By default, this purges only cached data that has its own expiration set
	 * explicitly, however, if the option to `$useLocalLifetime` is set to `TRUE`,
	 * the cache lifetime default (set by `setLifetime()`, defaulting to
	 * DEFAULT_LIFETIME) will be compared the timestamps as well, purging cache
	 * data without explicitly set expirations.
	 *
	 * @param boolean $useLocalLifetime (Optional) Defaults to `FALSE`
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 *
	 * @see setLifetime() setLifetime()
	 **/
	public function purgeExpired($useLocalLifetime = false)
	{
		// FIXME actually use $useLocalLifetime!
		if ($this->sqlInitialized()) {
			if ($this->sql) {
				if($this->sql->query("
					DELETE
						FROM `{$this->table}`
						WHERE `expire` < NOW()
				")) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Store data in cache
	 *
	 * @param string $key
	 * @param mixed $data Must be serializable
	 * @param int $lifetimeInSeconds (Optional)
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 *
	 * @see setLifetime() setLifetime()
	 **/
	public function setCache($key, $data, $lifetimeInSeconds = null)
	{
		if ($this->sqlInitialized()) {

			if ($this->sql) {

				/* escape query data */
				$_key = $this->sql->real_escape_string($key);
				$_data = $this->sql->real_escape_string(serialize($data));

				/* if no lifetime passed in, use local default lifetime */
				$lifetime = ($lifetimeInSeconds === null ? $this->lifetime : $lifetimeInSeconds);
				if ($lifetime !== self::IMMORTAL_LIFETIME) {
					$_expire = date(self::MYSQL_TIMESTAMP, time() + $lifetime);
				} else {
					$_expire = false;
				}

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
			return false;
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
		if ($this->sqlInitialized()) {
			$_key = $this->sql->real_escape_string($key);
			if ($this->sql->query("
				DELETE
					FROM `{$this->table}`
					WHERE
						`{$this->key}` = '$_key'
			")) {
				return true;
			}
			return false;
		}
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
		if ($this->sqlInitialized()) {
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
		return false;
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
		if ($this->sqlInitialized()) {
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
		return false;
	}
}
