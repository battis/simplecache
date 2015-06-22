<?php

class SimpleCache {
	
	const DEFAULT_LIFETIME = 3600; /* 60 seconds * 60 minutes = 1 hour */
	
	protected $sql = null;
	protected $table = 'cache';
	protected $key = 'key';
	protected $cache = 'cache';
	protected $lifetime = SimpleCache::DEFAULT_LIFETIME;
	
	public function setSqlInfo($host, $user, $password, $database) {
		if (true /* TODO validation */) {
			$this->sql = new mysqli($host, $user, $password, $database);
			return true;
		}
		return false;
	}
	
	public function setSql($sql) {
		if ($sql instanceof mysqli) {
			$this->sql = $sql;
			return true;
		}
		return false;
	}
	
	public function setTableName($table) {
		if ($this->sql instanceof mysqli) {
			if ($table == $this->sql->real_escape_string($table)) {
				$this->table = $table;
				return true;
			} else {
				throw new SimpleCache_Exception("Apparent SQL injection: `$table` is not a valid table name");
			}
		}
		return false;
	}
	
	public function setKeyName($key) {
		if ($this->sql instanceof mysqli) {
			if ($table == $this->sql->real_escape_string($key)) {
				$this->key = $key;
				return true;
			} else {
				throw new SimpleCache_Exception("Apparent SQL injection: `$key` is not a valid field name");
			}
		}
		return false;
	}
	
	public function setCacheName($cache) {
		if ($this->sql instanceof mysqli) {
			if ($cache == $this->sql->real_escape_string($cache)) {
				$this->cache = $cache;
				return true;
			} else {
				throw new SimpleCache_Exception("Apparent SQL injection: `$cache` is not a valid field name");
			}
		}
		return false;
	}
	
	protected function setLifetime($lifetimeInSeconds = SimpleCache::DEFAULT_LIFETIME) {
		$this->lifetime = max(0, intval($lifetimeInSeconds));
	}
	
	public function buildCache($table = null, $key = null, $cache = null) {
		if ($this->sql instanceof mysqli &&
			($table === null || $this->setTable($table)) &&
			($key === null || $this->setKeyName($key)) &&
			($cache === null || $this->setCacheName($cache))) {
			if ($this->sql->query("
				CREATE TABLE `{$this->table}` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`{$this->key}` text NOT NULL,
					`{$this->cache}` text NOT NULL,
					`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`)
				)
			")) {
				return true;
			}
		}
		return false;
	}
	
	public function getCache($key) {
		if ($this->sql instanceof mysqli) {
			$liveCache = date('Y-m-d H:i:s', time() - CACHE_DURATION);
			if ($response = $this->sql->query("
				SELECT *
					FROM `{$this->$table}`
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
	
	public function setCache($key, $data) {
		if ($this->sql instanceof mysqli) {
			if ($response = $this->sql->query("
				INSERT
					INTO `{$this->table}`
					(
						`{$this->key}`,
						`{$this->cache}`
					) VALUES (
						'" . $this->sql->real_escale_string($key) . "',
						'" . $this->sql->real_escape_string(serialize($cachedData)) . "'
					)
			")) {
				return true;
			}
		}
		return false;
	}
	
	public function resetCache($key) {
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

class SimpleCache_Exception extends Exception {}
	
?>