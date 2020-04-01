<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\errors;

use yii\base\Exception;

/**
 * Exception thrown when a EVENT_RELIQUARY_MODIFY_FILTER_QUERY event was raised
 * but was not handled. If this happens, the query won't reflect the intended
 * filtering options.
 */
class NoModifyFilterQueryHandlerException extends Exception
{
	/**
	 * @return string the user-friendly name of this exception.
	 */
	public function getName()
	{
		return 'Filter does not provide custom query handler';
	}
}
