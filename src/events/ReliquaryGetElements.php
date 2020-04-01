<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\events;

use yii\base\Event;

/**
 * Event raised on an element class in order to retrieve underlying element
 * objects based on IDs filtered from an underlying search query.
 */
class ReliquaryGetElements extends Event
{
	/**
	 * @var int[] The IDs of the elements to retrieve.
	 */
	public $ids;

	/**
	 * @var int The ID of the site that elements should be retrieved for.
	 */
	public $siteId;

	/**
	 * @var \craft\base\Element[] The elements retrieved by the provided IDs.
	 */
	public $elements = [];
}
