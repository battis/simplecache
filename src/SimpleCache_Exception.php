<?php
/** SimpleCache_Exception class */

namespace Battis;

/**
 * All exceptions thrown by SimpleCache
 *
 * @author Seth Battis <seth@battis.net>
 * @codingStandardsIgnoreStart
 **/
class SimpleCache_Exception extends \Exception
{
    /* @codingStandardsIgnoreEnd */

    /** A connection with the backing database could not be initialized */
    const DATABASE_NOT_INITALIZED = 1;

    /** An error occurred trying to write to the cache */
    const CACHE_WRITE = 2;
}
