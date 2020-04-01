<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\records;

use jaredlindo\reliquary\models\SearchGroupFilter as SearchGroupFilterModel;

use craft\db\ActiveRecord;
use craft\records\Field;

/**
 * A record for [[\jaredlindo\reliquary\models\SearchGroupFilter]].
 */
class SearchGroupFilter extends ActiveRecord
{
	/**
	 * @inheritdoc
	 * @see yii\db\ActiveRecord
	 * @return string
	 */
	public static function tableName(): string
	{
		return '{{%reliquary_searchgroupfilters}}';
	}

	/**
	 * Returns the group associated with this filter.
	 * @return ActiveQueryInterface The relational query object.
	 */
	public function getGroup(): ActiveQueryInterface
	{
		return $this->hasOne(SearchGroup::class, ['id' => 'groupId']);
	}

	/**
	 * Returns the field that this acts as a filter for.
	 * @return ActiveQueryInterface The relational query object.
	 */
	public function getField(): ActiveQueryInterface
	{
		return $this->hasOne(Field::class, ['id' => 'fieldId']);
	}

	/**
	 * Creates a search group filter model from a search group filter record.
	 * @param SearchGroupFilter|null $record The record to convert to a model.
	 * @return SearchGroupFilterModel|null The model created from the record, or null if one couldn't be created.
	 */
	public static function createModel(SearchGroupFilter $record = null)
	{
		if (!$record) {
			return null;
		}

		$model = new SearchGroupFilterModel($record->toArray([
			'id',
			'groupId',
			'fieldId',
			'attribute',
			'handle',
			'name',
			'sortOrder',
		]));

		return $model;
	}
}
