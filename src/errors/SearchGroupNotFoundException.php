<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\errors;

use yii\base\Exception;

/**
 * Exception thrown when a search group was expected, but one was not found.
 */
class SearchGroupNotFoundException extends Exception
{
	/**
	 * @return string the user-friendly name of this exception.
	 */
	public function getName()
	{
		return 'Search group not found';
	}
}
