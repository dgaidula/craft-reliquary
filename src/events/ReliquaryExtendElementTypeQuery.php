<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\events;

use yii\base\Event;

/**
 * Event raised on an element class in order to allow extension of search
 * queries based on a given element being searched for.
 */
class ReliquaryExtendElementTypeQuery extends Event
{
	/**
	 * @var \jaredlindo\reliquary\models\SearchGroupElement The configuration
	 * object for the search group describing the type of element to be included
	 * in this search.
	 */
	public $searchElement;

	/**
	 * @var \craft\db\Query|null The query provided back, it should filter out
	 * elements based on the provided SearchGroupElement criteria. The query
	 * should select element IDs as `id`, element types as `type`, and element
	 * type IDs as `typeId`.
	 */
	public $query = null;
}
