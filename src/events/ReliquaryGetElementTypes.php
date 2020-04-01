<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\events;

use yii\base\Event;

/**
 * Event raised on the Reliquary plugin in order to gather element types that
 * should be allowed to be selectable as searchable elements.
 */
class ReliquaryGetElementTypes extends Event
{
	/**
	 * @var mixed Follow this format:
	 * [
	 *   'CLASS' : {
	 *     'attributes' : [
	 *       {
	 *         'name' : string,
	 *         'handle' : string,
	 *       }, {
	 *         ...
	 *       }
	 *     ],
	 *     'subtypes' : [
	 *       'ID' : {
	 *         'name' : string,
	 *         'layoutId' : int,
	 *       }, {
	 *         ...
	 *       }
	 *     ]
	 *   }, {
	 *     ...
	 *   }
	 * ]
	 *
	 * Where properties are the following:
	 * [CLASS] - The top level array of elements are keyed by classname.
	 * [][name] - The frontend-friendly name of the element type.
	 * [][attributes] - Attributes available on the element type that can be
	 *   used as filters by Reliquary.
	 * [][attributes][] - The attributes array is simply a sequential index.
	 * [][attributes][][name] - The frontend-friendly name of the attribute.
	 * [][attributes][][handle] - The handle used for the attribute within the
	 *   element itself.
	 * [][subtypes] - An array of possible subtypes within the main type, such as
	 *   entry types, asset volumes, category groups, etc.; any separation
	 *   amongst elements of the specified class that gives them separate
	 *   field groups.
	 * [][subtypes][ID]
	 * [][subtypes][][name] - The frontend-friendly name of the subtype.
	 * [][subtypes][][layoutId] - The integer ID associated with the field layout
	 *   used by this subtype.
	 */
	public $elementTypes = [];
}
