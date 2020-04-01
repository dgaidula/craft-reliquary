<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\models;

use Craft;
use craft\base\Model;

/**
 * The plugin settings model for the Reliquary plugin.
 */
class Settings extends Model
{
	public $minimumScore = 0.1;

	/**
	 * @inheritdoc
	 * @see yii\base\BaseObject
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Model
	 */
	public function rules()
	{
		return [
			['minimumScore', 'numerical', 'integerOnly' => false, 'min' => 0],
		];
	}
}
