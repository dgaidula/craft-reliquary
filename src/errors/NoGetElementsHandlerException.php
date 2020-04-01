<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\errors;

use yii\base\Exception;

/**
 * Exception thrown when a EVENT_RELIQUARY_GET_ELEMENTS event was raised but was
 * not handled. If this happens, any elements of the raised type will not be
 * present in the resulting set.
 */
class NoGetElementsHandlerException extends Exception
{
	/**
	 * @return string the user-friendly name of this exception.
	 */
	public function getName()
	{
		return 'Element does not provide result conversion handler';
	}
}
