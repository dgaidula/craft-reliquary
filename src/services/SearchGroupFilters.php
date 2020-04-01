<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\services;

use jaredlindo\reliquary\errors\SearchGroupFilterNotFoundException;
use jaredlindo\reliquary\helpers\MultiCache;
use jaredlindo\reliquary\models\SearchGroup;
use jaredlindo\reliquary\models\SearchGroupFilter;
use jaredlindo\reliquary\records\SearchGroupFilter as SearchGroupFilterRecord;

use yii\base\Component;

use Craft;

/**
 * The primary Reliquary service.
 */
class SearchGroupFilters extends Component
{
	/**
	  * @var MultiCache Stores individual filters, keyed by ID.
	 */
	private $_filters;

	/**
	  * @var MultiCache Stores sets of filters, keyed by group ID.
	 */
	private $_groupFilters;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->_filters = new MultiCache();
		$this->_groupFilters = new MultiCache();
	}

	/**
	 * Retrieves search filters associated with the given group.
	 * @param SearchGroup $group The group to retrieve search filters for.
	 * @return SearchGroupFilter[] Filters associated with the given group.
	 */
	public function getFiltersByGroup(SearchGroup $group)
	{
		if ($group == null) {
			return [];
		}

		return $this->getFiltersByGroupId($group->id);
	}

	/**
	 * Retrieves search filters associated with the given group ID.
	 * @param int $groupId The ID of the group to retrieve search filters for.
	 * @return SearchGroupFilter[] Filters associated with the given group.
	 */
	public function getFiltersByGroupId(int $groupId)
	{
		if (!$groupId) {
			return [];
		}

		if (!$this->_groupFilters->isFinalized($groupId)) {
			// Pull ids of any already cached filters.
			$filterIds = array_map(function ($filter) {
				return $filter->id;
			}, $this->_groupFilters->getItems($groupId));

			// Retrieve all other records for the group.
			$records = SearchGroupFilterRecord::find()
				->where([
					'and',
					['groupId' => $groupId],
					['not in', 'id', $filterIds],
				])
				->all();

			// Cache the records.
			foreach ($records as $record) {
				$model = SearchGroupFilterRecord::createModel($record);
				$this->_cacheFilter($model);
			}

			// Finalize and sort the 'group' cache, now that records have been retrieved.
			$this->_groupFilters->finalize($groupId);
			$this->_sortGroupFilters($groupId);
		}

		return $this->_groupFilters->getItems($groupId);
	}

	/**
	 * Retrieves a search filter by its ids.
	 * @param int $filterId The ID of the filter to retrieve.
	 * @return SearchGroupFilter The filter, or null if one doesn't exist.
	 */
	public function getFilterById(int $filterId)
	{
		if (!$filterId) {
			return null;
		}

		if (!$this->_filters->getItem($filterId)) {
			// Retrieve the record.
			$record = SearchGroupFilterRecord::find()
				->where([
					'id' => $filterId
				])
				->one();

			// Cache the records.
			if ($record) {
				$model = SearchGroupFilterRecord::createModel($record);
				$this->_cacheFilter($model);
			}
		}

		return $this->_filters->getItem($filterId);
	}

	/**
	 * Creates or updates a search group filter based on the provided model.
	 * @param SearchGroupFilter $filter The filter to save.
	 * @param bool $runValidation Set to false in order to skip validating the record before saving.
	 * @return bool True on successful save, false on failure.
	 */
	public function saveFilter(SearchGroupFilter $model, bool $runValidation = true): bool
	{
		// Validate the filter before doing anything.
		if ($runValidation && !$model->validate()) {
			Craft::info('Search group filter not saved due to validation error.', __METHOD__);
			return false;
		}

		// Find an existing record or create a new one.
		if ($model->id) {
			$record = SearchGroupFilterRecord::find()
				->where(['id' => $model->id])
				->one();
			if (!$record) {
				throw new SearchGroupFilterNotFoundException('Invalid search group filter ID: ' . $model->id);
			}
		} else {
			$record = new SearchGroupFilterRecord();
		}

		// Group ID is updating, remove from current 'group' cache.
		if ($record->id && $record->groupId != $model->groupId) {
			$this->_groupFilters->removeItem($model->id, $record->groupId);
		}

		$record->handle = $model->handle;
		$record->name = $model->name;
		$record->groupId = $model->groupId;
		$record->fieldId = $model->fieldId;
		$record->attribute = $model->attribute;
		if ($model->sortOrder == null) {
			$model->sortOrder = 999; // Just set it to some large amount by default.
		}
		$record->sortOrder = $model->sortOrder;

		// Save the record.
		$transaction = Craft::$app->getDb()->beginTransaction();
		try {
			$record->save();
			if (!$model->id) { // New record.
				$model->id = $record->id; // Store the new ID.
				$this->_cacheFilter($model); // Cache the new record.
			}
			$this->_sortGroupFilters($model->groupId); // Re-sort 'group' cache if necessary.

			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}

		return true;
	}

	/**
	 * Adds the specified filter to all caches.
	 * @param SearchGroupFilter|null $filter The filter to add.
	 */
	private function _cacheFilter($filter)
	{
		if ($filter == null) {
			return;
		}

		$this->_filters->setItem($filter, $filter->id);
		$this->_groupFilters->addItem($filter, $filter->id, $filter->groupId);
	}

	/**
	 * Sorts a set of filters stored within the 'group' cache.
	 * @param int $groupId The ID of the group that should have its set of filters sorted.
	 */
	private function _sortGroupFilters(int $groupId)
	{
		if (!$this->_groupFilters->isFinalized($groupId)) { // Check optional skip.
			return;
		}

		// Sort the items by their order if finalized.
		$this->_groupFilters->sortSet(function ($a, $b) {
			return $a->sortOrder - $b->sortOrder;
		}, $groupId);
	}
}
