<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\records;

use jaredlindo\reliquary\models\SearchGroup as SearchGroupModel;

use craft\db\ActiveRecord;

/**
 * A record for [[\jaredlindo\reliquary\models\SearchGroup]].
 */
class SearchGroup extends ActiveRecord
{
	/**
	 * @inheritdoc
	 * @see yii\db\ActiveRecord
	 * @return string
	 */
	public static function tableName(): string
	{
		return '{{%reliquary_searchgroups}}';
	}

	/**
	 * Returns the filters associated with this group.
	 * @return ActiveQueryInterface The relational query object.
	 */
	public function getFilters(): ActiveQueryInterface
	{
		return $this->hasMany(SearchGroupFilter::class, ['groupId' => 'id']);
	}

	/**
	 * Creates a search group model from a search group record.
	 * @param SearchGroup|null $record The record to convert to a model.
	 * @return SearchGroupModel|null The model created from the record, or null if one couldn't be created.
	 */
	public static function createModel(SearchGroup $record = null)
	{
		if (!$record) {
			return null;
		}

		$model = new SearchGroupModel($record->toArray([
			'id',
			'siteId',
			'handle',
			'name',
			'template',
			'pageSize',
			'searchOrder',
			'sortOrder',
		]));

		return $model;
	}
}
