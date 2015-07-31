<?php

/** HierarchicalSimpleCache and related classes */

namespace Battis;

/**
 * A modestly hierarchical version of SimpleCache
 *
 * @author Seth Battis <seth@battis.net>
 **/
class HiearchicalSimpleCache extends SimpleCache {

	/** @var string Base for hierarchical keys `base/key` */
	private $base = '';

	/**
	 * Construct a new LTI_Cache
	 *
	 * @param \mysqli $sql
	 * @param string $keyRoot
	 **/
	public function __construct($sql, $base) {
		parent::__construct($sql);
		$this->base = $base;
	}
	
	/**
	 * Build the hierarchical key `base/key`
	 *
	 * @param string $key
	 *
	 * @return string
	 **/
	private function hierarchicalKey($key) {
		return "{$this->base}/$key";
	}
	
	/**
	 * {@inheritDoc}
	 *
	 * @param string $key
	 * @param mixed $data
	 *
	 * @return boolean
	 **/
	public function setCache($key, $data) {
		return parent::setCache($this->hierarchicalKey($key), $data);
	}
	
	/**
	 * {@inheritDoc}
	 *
	 * @param string $key
	 *
	 * @return boolean
	 **/
	public function getCache($key) {
		return parent::getCache($this->hierarchicalKey($key));
	}
	
	/**
	 * {@inheritDoc}
	 *
	 * @param string $key
	 *
	 * @return boolean
	 **/
	public function resetCache($key) {
		return parent::resetCache($this->hierarchicalKey($key));
	}
}
	
/**
 * All exceptions thrown by HierarchicalSimpleCache
 *
 * @author Seth Battis <seth@battis.net>
 **/
class HierarchicalSimpleCache_Exception extends SimpleCache_Exception {}

?>