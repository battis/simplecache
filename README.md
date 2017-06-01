# SimpleCache

[![Latest Version](https://img.shields.io/packagist/v/battis/simplecache.svg)](https://packagist.org/packages/battis/simplecache)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/battis/simplecache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/battis/simplecache/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/battis/simplecache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/battis/simplecache/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/battis/simplecache/badges/build.png?b=master)](https://scrutinizer-ci.com/g/battis/simplecache/build-status/master)

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
```

Full API documentation is available in [/docs](https://battis.github.io/simplecache/namespaces/Battis.html).
