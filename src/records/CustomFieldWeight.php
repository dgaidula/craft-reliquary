<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\records;

use jaredlindo\reliquary\models\CustomFieldWeight as CustomFieldWeightModel;

use craft\db\ActiveRecord;
use craft\base\Field;

/**
 * A record for [[\jaredlindo\reliquary\models\CustomFieldWeight]].
 */
class CustomFieldWeight extends ActiveRecord
{
	/**
	 * @inheritdoc
	 * @see yii\db\ActiveRecord
	 * @return string
	 */
	public static function tableName(): string
	{
		return '{{%reliquary_customfieldweights}}';
	}

	/**
	 * Creates a model from a record.
	 * @param CustomFieldWeight|null $record The record to convert to a model.
	 * @return CustomFieldWeightModel|null The model created from the record, or null if one couldn't be created.
	 */
	public static function createModel(CustomFieldWeight $record = null)
	{
		if (!$record) {
			return null;
		}

		$model = new CustomFieldWeightModel($record->toArray([
			'fieldId',
			'attribute',
			'elementType',
			'elementTypeId',
			'multiplier',
		]));

		return $model;
	}
}
