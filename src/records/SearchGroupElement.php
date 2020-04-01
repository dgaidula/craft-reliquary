<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\records;

use jaredlindo\reliquary\models\SearchGroupElement as SearchGroupElementModel;

use craft\db\ActiveRecord;
use craft\records\Field;

/**
 * A record for [[\jaredlindo\reliquary\models\SearchGroupElement]].
 */
class SearchGroupElement extends ActiveRecord
{
	/**
	 * @inheritdoc
	 * @see yii\db\ActiveRecord
	 * @return string
	 */
	public static function tableName(): string
	{
		return '{{%reliquary_searchgroupelements}}';
	}

	/**
	 * Returns the group associated with this element.
	 * @return ActiveQueryInterface The relational query object.
	 */
	public function getGroup(): ActiveQueryInterface
	{
		return $this->hasOne(SearchGroup::class, ['id' => 'groupId']);
	}

	/**
	 * Creates a search group element model from a search group element record.
	 * @param SearchGroupElement|null $record The record to convert to a model.
	 * @return SearchGroupElementModel|null The model created from the record, or null if one couldn't be created.
	 */
	public static function createModel(SearchGroupElement $record = null)
	{
		if (!$record) {
			return null;
		}

		$model = new SearchGroupElementModel($record->toArray([
			'id',
			'groupId',
			'elementType',
			'elementTypeId',
			'sortOrder',
		]));

		return $model;
	}
}
