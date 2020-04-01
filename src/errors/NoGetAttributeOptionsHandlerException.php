<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\errors;

use yii\base\Exception;

/**
 * Exception thrown when a EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS event was
 * raised but was not handled. If this happens, attributes set as filters cannot
 * provide information on the frontend regarding what potential filter values
 * are.
 */
class NoGetAttributeOptionsHandlerException extends Exception
{
	/**
	 * @return string the user-friendly name of this exception.
	 */
	public function getName()
	{
		return 'Attribute does not provide option listing handler';
	}
}
