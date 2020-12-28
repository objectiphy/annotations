<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * In case you want to use the reader without a cache, or with the Objectiphy cache, and don't have 
 * Psr\SimpleCache\CacheInterface available, we won't force you to install Psr\SimpleCache - we'll just use this dummy 
 * version instead (one less dependency to worry about).
 */
interface PsrSimpleCacheInterface
{
    public function get($key, $default = null);
    public function set($key, $value, $ttl = null);
    public function delete($key);
    public function clear();
    public function getMultiple($keys, $default = null);
    public function setMultiple($values, $ttl = null);
    public function deleteMultiple($keys);
    public function has($key);
}
