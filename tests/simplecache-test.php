<?php
	
require_once('common.inc.php');

$key = date('Y-m-d H:i:s');
html_var_dump($key);

try{
	$cache = new \Battis\SimpleCache($sql);
	$cache->setLifetime(15*60);
	$cache->setCache("$key-1", 'default 15 min lifetime');
	html_var_dump($cache->getCache("$key-1"));
	$cache->setCache("$key-2", 'per key 20 min lifetime', 20*60);
	$cache->setCache("$key-3", 'i should have been overwritten');
	$cache->setCache("$key-3", 'i overwrote previous data');
	$cache->setCache("$key-4", 'i should have been reset');
	$cache->resetCache("$key-4");
	
	$hcache = new \Battis\HierarchicalSimpleCache($sql, 'foo');
	$hcache->setLifetime(15*60);
	$hcache->setCache('1', 'foo-level data');
	$hcache->pushKey('bar');
	$hcache->setCache('2', 'bar-level data');
	$hcache->pushKey('foobar');
	$hcache->setCache('3', 'foobar-level data', 30);
	$hcache->popKey();
	$hcache->setCache('4', 'bar-level data', 40);
	$hcache->popKey();
	$hcache->setCache('5', 'foo-level data', 50);
	$hcache->popKey();
	$hcache->setCache('6', 'root-level data');
	echo "<p>timestamp test</p>";
	$cache->setCache('A', 'test');
	html_var_dump($cache->getCacheTimestamp('A'));
	html_var_dump($cache->getCacheTimestamp('testA'));
	html_var_dump($cache->getCacheExpiration('A'));
	html_var_dump($cache->getCacheExpiration('testB'));
} catch (\Battis\SimpleCache_Exception $e) {
	html_var_dump($e);
}

?>