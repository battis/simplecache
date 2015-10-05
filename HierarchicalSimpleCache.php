<?php

/** HierarchicalSimpleCache and related classes */

namespace Battis;

/**
 * A modestly hierarchical version of SimpleCache
 *
 * @author Seth Battis <seth@battis.net>
 **/
class HierarchicalSimpleCache extends SimpleCache {

	/** @var string Base for hierarchical keys `base/key` */
	private $base = '';
	
	/** @var string Delimiter for layers of hierarchy */
	protected $delimiter = '/';
	
	/** @var string Replacement for `delimiter` wthin layers of hierarchy */
	protected $placeholder = '_';

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
		return "{$this->base}{$this->delimiter}$key";
	}
	
	/**
	 * Get the current hierarchical base key
	 *
	 * @return string
	 **/
	public function getBase() {
		return $this->base;
	}
	
	/**
	 * Add a layer of depth to the key hierarchy
	 *
	 * @param string $key
	 *
	 * @return string The new base key
	 **/
	public function pushKey($layer) {
		$this->base .= $this->delimiter . str_replace($this->delimiter, $this->placeholder, $layer);
		return $this->getBase();
	}
	
	/**
	 * Remove a layer of depth from the key hierarchy
	 *
	 * @return string|null The layer that was removed from the hierarchy (`NULL`
	 *		if no layers exist)
	 **/
	public function popKey() {
		if (strlen($this->base)) {
			$layers = explode($this->delimiter, $this->base);
			$last = count($layers) - 1;
			$layer = $layers[$last];
			if ($last > 0) {
				unset ($layers[$last]);
				$this->base = implode($this->delimiter, $layers);
			} else {
				$this->base = '';
			}
			return $layer;
		}
		
		return null;
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