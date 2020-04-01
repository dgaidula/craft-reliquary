<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\errors;

use yii\base\Exception;

/**
 * Exception thrown when a EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS event was
 * handled by multiple elements but the provided option types conflict with
 * one another, and thus cannot be handled appropriately.
 */
class ConflictingAttributeOptionsException extends Exception
{
	/**
	 * @return string the user-friendly name of this exception.
	 */
	public function getName()
	{
		return 'Attribute options conflict among group elements';
	}
}
