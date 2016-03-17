# SimpleCache

[![Latest Version](https://img.shields.io/packagist/v/battis/simplecache.svg)](https://packagist.org/packages/battis/simplecache)

Manage a simple cache backed by a MySQL database

## Install

In your `composer.json` add:

```JSON
"require": {
  "battis/simplecache": "1.*"
}
```

## Use

Create a cache:

```PHP
$mysqli = new mysqli('localhost', 'bob', 's00pers3kr3t', 'app-db'); // or whatever your credentials are
$cache = new \Battis\SimpleCache($mysqli);
```

Basic workflow using cached data (check the cache and used cached data if there, otherwise build data and cache it for next time):

```PHP
$data = $cache->getCache('foo');
if ($data === false) {

  // ... lots and lots of work to create $data from scratch

  $cache->setCache('foo', $data); // cache for next use
}
