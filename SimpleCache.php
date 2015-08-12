<?php

/** SimpleCache and related classes */

namespace Battis;

/**
 * An object to manage a simple cache
 *
 * The cache is backed by a MySQL database, with data stored as `key => data`
 * pairs. All data stored is must be serializable.
 *
 * @author Seth Battis <seth@battis.net>
 **/
class SimpleCache {
	
	/** Default cache lifetime (1 hour) */
	const DEFAULT_LIFETIME = 3600; /* 60 seconds * 60 minutes = 1 hour */
	
	/** @var \mysqli MySQL database handle */
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
	 * @param \mysqli $sql (Optional)
	 * @param string $table (Optional) Cache table name in database
	 * @param string $key (Optional) Cache key field name in database
	 * @param string $cache (Optional) Cache storage field name in database
	 *
	 * @return void
	 **/
	public function __construct($sql = null, $table = null, $key = null, $cache = null) {
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
	public function setSqlInfo($host, $user, $password, $database) {
		return $this->setSql(new \mysqli($host, $user, $password, $database));
	}
	
	/**
	 * Set up database connection
	 *
	 * @param \mysqli $sql
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 **/
	public function setSql($sql) {
		if ($sql instanceof \mysqli) {
			$this->sql = $sql;
			return true;
		}
		return false;
	}
	
	/**
	 * Set cache table name
	 *
	 * @param string $table
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 **/
	public function setTableName($table) {
		if ($this->sql instanceof \mysqli) {
			if ($table == $this->sql->real_escape_string($table)) {
				$this->table = $table;
				return true;
			} else {
				throw new SimpleCache_Exception("Apparent SQL injection: `$table` is not a valid table name");
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
	public function setKeyName($key) {
		if ($this->sql instanceof \mysqli) {
			if ($table == $this->sql->real_escape_string($key)) {
				$this->key = $key;
				return true;
			} else {
				throw new SimpleCache_Exception("Apparent SQL injection: `$key` is not a valid field name");
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
	public function setCacheName($cache) {
		if ($this->sql instanceof \mysqli) {
			if ($cache == $this->sql->real_escape_string($cache)) {
				$this->cache = $cache;
				return true;
			} else {
				throw new SimpleCache_Exception("Apparent SQL injection: `$cache` is not a valid field name");
			}
		}
		return false;
	}
	
	/**
	 * Set lifetime of cached data
	 *
	 * @param int $lifetimeInSeconds Defaults to DEFAULT_LIFETIME, values less than
	 *		zero are treated as zero
	 *
	 * @return void;
	 **/
	public function setLifetime($lifetimeInSeconds = self::DEFAULT_LIFETIME) {
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
	protected function sqlInitialized() {
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
	public function buildCache($table = null, $key = null, $cache = null) {
		if ($this->sql instanceof \mysqli &&
			($table === null || $this->setTable($table)) &&
			($key === null || $this->setKeyName($key)) &&
			($cache === null || $this->setCacheName($cache))) {
			if ($this->sql->query("
				CREATE TABLE IF NOT EXISTS `{$this->table}` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`{$this->key}` text NOT NULL,
					`{$this->cache}` text NOT NULL,
					`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`)
				)
			")) {
				$this->initialized = true;
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
	public function getCache($key) {
		if ($this->sqlInitialized()) {
			if ($this->sql instanceof \mysqli) {
				$liveCache = date('Y-m-d H:i:s', time() - $this->lifetime);
				if ($response = $this->sql->query("
					SELECT *
						FROM `{$this->table}`
						WHERE
							`{$this->key}` = '" . $this->sql->real_escape_string($key) . "' AND
							`timestamp` > '{$liveCache}'
				")) {
					if ($cache = $response->fetch_assoc()) {
						return unserialize($cache[$this->cache]);
					}	
				}
			}
			return false;
		}
	}
	
	/**
	 * Store data in cache
	 *
	 * @param string $key
	 * @param mixed $data Must be serializable
	 *
	 * @return boolean Returns `TRUE` on success, `FALSE` on failure
	 **/
	public function setCache($key, $data) {
		if ($this->sqlInitialized()) {
			if ($this->sql instanceof \mysqli) {
				$response = $this->sql->query("
					SELECT *
						FROM `{$this->table}`
						WHERE
							`{$this->key}` = '" . $this->sql->real_escape_string($key) . "'
				");
				if ($cache = $response->fetch_assoc()) {
					if ($response = $this->sql->query("
						UPDATE
							`{$this->table}`
							SET
								`{$this->cache}` = '" . $this->sql->real_escape_string($data) . "'
							WHERE
								`{$this->key}` = '" . $this->sql->real_escape_string($key) . "'
					")) {
						return true;
					}
				} elseif ($response = $this->sql->query("
					INSERT
						INTO `{$this->table}`
						(
							`{$this->key}`,
							`{$this->cache}`
						) VALUES (
							'" . $this->sql->real_escape_string($key) . "',
							'" . $this->sql->real_escape_string(serialize($data)) . "'
						)
				")) {
					return true;
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
	public function resetCache($key) {
		if ($this->sqlInitialized()) {
			if ($this->sql->query("
				DELETE
					FROM `{$this->table}`
					WHERE
						`{$this->key}` = '" . $this->sql->real_escape_string($key) . "'
			")) {
				return true;
			}
			return false;
		}
	}
}

/**
 * All exceptions thrown by SimpleCache
 *
 * @author Seth Battis <seth@battis.net>
 **/
class SimpleCache_Exception extends \Exception {
	
	/** A connection with the backing database could not be initialized */
	const DATABASE_NOT_INITALIZED = 1;
}
	
?>