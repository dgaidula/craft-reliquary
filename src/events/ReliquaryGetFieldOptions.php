<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\events;

use yii\base\Event;

/**
 * Event raised on a field class in order to request available options for a
 * given field.
 */
class ReliquaryGetFieldOptions extends Event
{
	/**
	 * @var \craft\base\Field The instance which data is being requested for.
	 */
	public $field;

	/**
	 * @var string The input hint to provide forward indicating how the option
	 * data should be selected by an end user. Examples may include include:
	 *   'single' (dropdown, radiobuttons, lightswitch)
	 *   'multiple' (multiselect, elements, checkboxes)
	 *   'string' (plaintext)
	 *   'number' (number)
	 */
	public $type;

	/**
	 * @var bool Whether or not the options provided by the request are a
	 * partial amount. This may be true in the case of Element fields where
	 * there are many underlying options.
	 */
	public $partial = false;

	/**
	 * @var int|string|object|null A hint provided back to the underlying field
	 * in the case of a request for more values from a partial.
	 */
	public $hint = null;

	/**
	 * @var array Options provided back by handlers for the given field.
	 */
	public $options = [];

	/**
	 * @var int The total number of options available. Varies based on request.
	 */
	public $total = 0;
}
