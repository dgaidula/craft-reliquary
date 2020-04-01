<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\events;

use yii\base\Event;

/**
 * Event raised on a field class in order to allow extension of search
 * queries based on filter data relating to the field.
 *
 * The query's available structure at the point these events are called is the
 * `element_sites` table mapped to the alias `es` and the `elements` table
 * mapped to the alias `e`.
 */
class ReliquaryModifyFilterQuery extends Event
{
	/**
	 * @var \craft\db\Query The underlying query that will be used to filter
	 * elements.
	 */
	public $query;

	/**
	 * @var \jaredlindo\reliquary\models\SearchGroupFilter The filter object
	 * that the given value acts as a filter for.
	 */
	public $filter;

	/**
	 * @var mixed The value(s) used to filter by.
	 */
	public $value;

	/**
	 * @var boolean set to true by the filtering event handler if the value
	 * provided back should be used as a textual search.
	 */
	public $textSearch = false;
}
