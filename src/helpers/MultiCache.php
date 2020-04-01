<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\helpers;

/**
 * A simple cache that can manage individual items or sets of items.
 */
class MultiCache
{

	/**
	 * @var array Cached items and sets, each keyed by user-provided values.
	 */
	private $_cachedItems = [];

	/**
	 * @var bool[] State of each cache.
	 */
	private $_cacheState = [];

	/**
	 * Determines whether or not the cache for a given key is finalized.
	 * @param $cacheKey The key of the cache to check, or nothing for default (single-item) caches.
	 * @return bool True if the cache is finalized, false if partial/nonexistant.
	 */
	public function isFinalized($cacheKey = -1)
	{
		return isset($this->_cacheState[$cacheKey]);
	}

	/**
	 * Retrieves a single item (possibly partial) from the cache.
	 * @param $cacheKey The key of the cache to check, or nothing for default (single-item) caches.
	 * @return object The cached item, or null if no item is cached in the given slot.
	 */
	public function getItem($cacheKey = -1)
	{
		return isset($this->_cachedItems[$cacheKey]) ? $this->_cachedItems[$cacheKey] : null;
	}

	/**
	 * Retrieves an array of items (possibly partial) from the cache.
	 * @param $cacheKey The key of the cache to check, or nothing for default (single-item) caches.
	 * @return array The cached set of items, or an empty array if no item is cached in the given slot.
	 */
	public function getItems($cacheKey = -1)
	{
		return isset($this->_cachedItems[$cacheKey]) ? $this->_cachedItems[$cacheKey] : [];
	}

	/**
	 * Sets an individual item within the cache, and marks that cache as finalized.
	 * @param object $item The item to set in the given slot.
	 * @param $cacheKey The key of the cache to check, or nothing for default (single-item) caches.
	 */
	public function setItem($item, $cacheKey = -1)
	{
		$this->_cachedItems[$cacheKey] = $item;
		$this->_cacheState[$cacheKey] = true;
	}

	/**
	 * Removes an individual item from the cache.
	 * @param $cacheKey The key of the cache to clear, or nothing for default (single-item) caches.
	 */
	public function clearItem($cacheKey = -1)
	{
		unset($this->_cachedItems[$cacheKey]);
		unset($this->_cacheState[$cacheKey]);
	}

	/**
	 * Adds an item to a given set within the cache.
	 * @param object $item The item to set in the given slot.
	 * @param $setKey A key for the item being added for use within the subset, to prevent duplicates.
	 * @param $cacheKey The key of the cache to check, or nothing for default (single-item) caches.
	 */
	public function addItem($item, $setKey, $cacheKey = -1)
	{
		if (!isset($this->_cachedItems[$cacheKey])) {
			$this->_cachedItems[$cacheKey] = [];
		}
		$this->_cachedItems[$cacheKey][$setKey] = $item;
	}

	/**
	 * Marks a set within the cache as finalized.
	 * @param $cacheKey The key of the cache to mark as finalized, or nothing for default (single-item) caches.
	 */
	public function finalize($cacheKey = -1)
	{
		$this->_cacheState[$cacheKey] = true;
	}

	/**
	 * Removes an item from an underlying set within the cache.
	 * @param $setKey A key for the item being removed from the subset, the same one used when added.
	 * @param $cacheKey The key of the cache to check, or nothing for default (single-item) caches.
	 */
	public function removeItem($setKey, $cacheKey = -1)
	{
		if (isset($this->_cachedItems[$cacheKey]) && isset($this->_cachedItems[$cacheKey][$setKey])) {
			unset($this->_cachedItems[$cacheKey][$setKey]);
		}
	}

	/**
	 * Sorts an inner set by a given criteria, so that when [[getItems()]] is called, the underlying set is in this order.
	 * @param callable $sortFunc The sorting callback.
	 * @param $cacheKey The key of the cache to sort the set within, or nothing for default (single-item) caches.
	 */
	public function sortSet(callable $sortFunc, $cacheKey = -1)
	{
		if (!isset($this->_cachedItems[$cacheKey])) {
			return;
		}
		uasort($this->_cachedItems[$cacheKey], $sortFunc);
	}

	/**
	 * Retrieves all keys for each set within the multi-cache.
	 * If the only key is -1, then it is a single-set cache.
	 * @return array All keys in use.
	 */
	public function getKeys()
	{
		return array_keys($this->_cacheState);
	}
}
