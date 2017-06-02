<?php
/** HierarchicalSimpleCache class */

namespace Battis;

/**
 * A modestly hierarchical version of SimpleCache
 *
 * @author Seth Battis <seth@battis.net>
 **/
class HierarchicalSimpleCache extends SimpleCache
{

    const DEFAULT_DELIMITER = '/';
    const DEFAULT_PLACEHOLDER = '_';

    /** @var string Base for hierarchical keys `base/key` */
    private $base = '';

    /** @var string Delimiter for layers of hierarchy */
    protected $delimiter = self::DEFAULT_DELIMITER;

    /** @var string Replacement for `delimiter` wthin layers of hierarchy */
    protected $placeholder = self::DEFAULT_PLACEHOLDER;

    /**
     * Construct a new LTI_Cache
     *
     * @param \mysqli $sql
     * @param string $base
     * @param delimiter
     * @param string $table
     * @param string $key
     * @param string $cache
     * @param boolean $purgeExpired
     * @param string $placeholder
     **/
    public function __construct(
        $sql,
        $base = null,
        $delimiter = null,
        $table = null,
        $key = null,
        $cache = null,
        $purgeExpired = false,
        $placeholder = null
    ) {
        parent::__construct($sql, $table, $key, $cache, $purgeExpired);

        $this->setBase($base);
        $this->setDelimiter($delimiter);
        $this->setPlaceholder($placeholder);
    }

    /**
     * Replaces all literal delimiters in $layer with placeholders, rendering
     * the layer a "single" layer (rather than allowing the literal delimiters
     * to split it up)
     *
     * @param string $layer
     * @return string
     */
    protected function getSingleLayer($layer)
    {
        return str_replace($this->delimiter, $this->placeholder, $layer);
    }

    /**
     * Build the hierarchical key `base/key`
     *
     * @param string $key
     *
     * @return string
     **/
    public function getHierarchicalKey($key)
    {
        return $this->base . $this->delimiter . $this->getSingleLayer($key);
    }

    /**
     * Set base of hierarchy
     *
     * @param string $base (Optional, defaults to delimiter)
     */
    protected function setBase($base = null)
    {
        $this->base = (isset($base) ? (string) $base : $this->delimiter);
    }

    /**
     * Get the current hierarchical base key
     *
     * @return string
     **/
    public function getBase()
    {
        return $this->base;
    }

    /**
     * Change the current delimiter that separates pushed layers
     *
     * @param string $delimiter Non-empty and not the current placeholder
     *
     * @return boolean `TRUE` if successful, `FALSE` otherwise
     */
    public function setDelimiter($delimiter)
    {
        if ($delimiter != $this->placeholder && strlen($delimiter) > 0) {
            $this->delimiter = $delimiter;
            return true;
        }
        return false;
    }

    /**
     * Get the current delimiter that separates pushed layers
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * Change the current placeholder that replaces literal delimiters in
     * pushed layers
     *
     * @param string $placeholder Non-empty and not the current delimiter
     *
     * @return boolean `TRUE` if successful, `FALSE` otherwise
     */
    public function setPlaceholder($placeholder)
    {
        if ($placeholder != $this->delimiter && strlen($placeholder) > 0) {
            $this->placeholder = $placeholder;
            return true;
        }
        return false;
    }

    /**
     * Get the current placeholder that replaces literal delimiters in pushed
     * layers
     * @return string
     */
    public function getPlaceholder()
    {
        return $this->placeholder;
    }

    /**
     * Add a layer of depth to the key hierarchy
     *
     * @param string $layer
     *
     * @return string The new base key
     **/
    public function pushKey($layer)
    {
        $this->base .= $this->delimiter . $this->getSingleLayer($layer);
        return $this->getBase();
    }

    /**
     * Remove a layer of depth from the key hierarchy
     *
     * @return string|null The layer that was removed from the hierarchy (`NULL`
     *        if no layers exist)
     **/
    public function popKey()
    {
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
     * @param int|false $lifetimeInSeconds (Optional)
     *
     * @return boolean
     **/
    public function setCache($key, $data, $lifetimeInSeconds = false)
    {
        return parent::setCache($this->getHierarchicalKey($key), $data, $lifetimeInSeconds);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $key
     *
     * @return boolean
     **/
    public function getCache($key)
    {
        return parent::getCache($this->getHierarchicalKey($key));
    }

    /**
     * {@inheritDoc}
     *
     * @param string $key
     *
     * @return boolean
     **/
    public function resetCache($key)
    {
        return parent::resetCache($this->getHierarchicalKey($key));
    }

    /**
     * {@inheritDoc}
     *
     * @param string $key
     *
     * @return \DateTime|boolean
     **/
    public function getCacheTimestamp($key)
    {
        return parent::getCacheTimestamp($this->getHierarchicalKey($key));
    }

    /**
     * {@inheritDoc}
     *
     * @param string $key
     *
     * @return \DateTime|boolean
     **/
    public function getCacheExpiration($key)
    {
        return parent::getCacheExpiration($this->getHierarchicalKey($key));
    }
}
