<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\events;

use yii\base\Event;

/**
 * Event raised on an element class in order to request available options for a
 * given attribute.
 */
class ReliquaryGetAttributeOptions extends Event
{
	/**
	 * @var string The attribute which data is being requested for.
	 */
	public $attribute;

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
	 * partial amount. This may be true in the case of Element attribute where
	 * there are many underlying options.
	 */
	public $partial = false;

	/**
	 * @var int|string|object|null A hint provided back to the underlying
	 * attribute in the case of a request for more values from a partial.
	 */
	public $hint = null;

	/**
	 * @var array Options provided back by handlers for the given attribute.
	 */
	public $options = [];

	/**
	 * @var int The total number of options available. Varies based on request.
	 */
	public $total = 0;
}
