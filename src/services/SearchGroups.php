<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\services;

use jaredlindo\reliquary\Reliquary;
use jaredlindo\reliquary\errors\SearchGroupNotFoundException;
use jaredlindo\reliquary\helpers\MultiCache;
use jaredlindo\reliquary\models\SearchGroup;
use jaredlindo\reliquary\records\SearchGroup as SearchGroupRecord;

use craft\models\Site;

use yii\base\Component;

use Craft;

/**
 * A service regarding search groups.
 */
class SearchGroups extends Component
{
	/**
	 * @var MultiCache Stores a single set of all search groups.
	 */
	private $_allSearchGroups;

	/**
	 * @var MultiCache Stores individual search groups keyed by ID.
	 */
	private $_searchGroups;

	/**
	 * @var MultiCache Stores individual search groups keyed by handle.
	 */
	private $_searchGroupsByHandle;

	/**
	 * @var MultiCache Stores sets of search groups, keyed by site ID.
	 */
	private $_siteSearchGroups;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->_allSearchGroups = new MultiCache();
		$this->_searchGroups = new MultiCache();
		$this->_searchGroupsByHandle = new MultiCache();
		$this->_siteSearchGroups = new MultiCache();
	}

	/**
	 * Returns a search group by its ID.
	 * @param int $groupId The ID of the group to retrieve.
	 * @return SearchGroup|null The associated search group, or null if one
	 * could not be found with that id.
	 */
	public function getGroupById(int $groupId)
	{
		// Retrieve data, if cache incomplete.
		if (!$this->_searchGroups->isFinalized($groupId)) {
			// Find a record.
			$record = SearchGroupRecord::find()
				->where(['id' => $groupId])
				->one();

			if (!$record) {
				return null;
			}

			// Cache the record and then return it.
			$model = SearchGroupRecord::createModel($record);
			$this->_cacheSearchGroup($model);
		}
		return $this->_searchGroups->getItem($groupId);
	}

	/**
	 * Returns a search group by its handle.
	 * @param string $handle The handle of the group to retrieve.
	 * @return SearchGroup|null The associated search group, or null if one
	 * could not be found with that handle.
	 */
	public function getGroupByHandle(string $handle)
	{
		// Retrieve data, if cache incomplete.
		if (!$this->_searchGroupsByHandle->isFinalized($handle)) {
			// Find a record.
			$record = SearchGroupRecord::find()
				->where(['handle' => $handle])
				->one();

			if (!$record) {
				return null;
			}

			// Cache the record and then return it.
			$model = SearchGroupRecord::createModel($record);
			$this->_cacheSearchGroup($model);
		}
		return $this->_searchGroupsByHandle->getItem($handle);
	}

	/**
	 * Returns a set of search groups assigned to the given site, ordered by
	 * their internal sort order.
	 * @param Site $site The site to retrieve search groups for.
	 * @return SearchGroup[] An array of search groups.
	 */
	public function getGroupsBySite(Site $site)
	{
		if ($site == null) {
			return [];
		}

		return $this->getGroupsBySiteId($site->id);
	}

	/**
	 * Returns a set of search groups assigned to the given site ID, ordered by
	 * their internal sort order.
	 * @param int $siteId The ID of the site to retrieve search groups for.
	 * @return SearchGroup[] An array of search groups.
	 */
	public function getGroupsBySiteId(int $siteId)
	{
		if (!$siteId) {
			return [];
		}

		// Retrieve data, if cache incomplete.
		if (!$this->_siteSearchGroups->isFinalized($siteId)) {
			// Pull ids of any already cached groups.
			$groupIds = array_map(function ($group) {
				return $group->id;
			}, $this->_siteSearchGroups->getItems($siteId));

			// Retrieve all other records for the site.
			$records = SearchGroupRecord::find()
				->where([
					'and',
					['siteId' => $siteId],
					['not in', 'id', $groupIds],
				])
				->all();

			// Cache the records.
			foreach ($records as $record) {
				$model = SearchGroupRecord::createModel($record);
				$this->_cacheSearchGroup($model);
			}

			// Finalize and sort the 'site' cache, now that records have been retrieved.
			$this->_siteSearchGroups->finalize($siteId);
			$this->_sortSiteSearchGroups($siteId);
		}
		return $this->_siteSearchGroups->getItems($siteId);
	}

	/**
	 * Returns a set of all search groups, sorted by ID.
	 * @return SearchGroup[] An array of all search groups.
	 */
	public function getAllGroups()
	{
		// Retrieve data, if cache incomplete.
		if (!$this->_allSearchGroups->isFinalized()) {
			// Pull ids of any already cached groups.
			$groupIds = array_map(function ($group) {
				return $group->id;
			}, $this->_allSearchGroups->getItems());

			// Retrieve all other records.
			$records = SearchGroupRecord::find()
				->where(['not in', 'id', $groupIds])
				->all();

			// Cache the records, store their IDs.
			foreach ($records as $record) {
				$groupIds[] = $record->id;
				$model = SearchGroupRecord::createModel($record);
				$this->_cacheSearchGroup($model);

				// Finalize and sort the 'site' cache if needed, since all records have been retrieved.
				if (!$this->_siteSearchGroups->isFinalized($model->siteId)) {
					$this->_siteSearchGroups->finalize($model->siteId);
					$this->_sortSiteSearchGroups($model->siteId);
				}
			}
			// Finalize and sort the 'all' cache.
			$this->_allSearchGroups->finalize();
			$this->_sortAllSearchGroups();
		}

		return $this->_allSearchGroups->getItems();
	}

	/**
	 * Creates or updates a search group based on the provided model.
	 * @param SearchGroup $model The group to save.
	 * @param bool $runValidation Set to false in order to skip validating the
	 * record before saving.
	 * @return bool True on successful save, false on failure.
	 */
	public function saveGroup(SearchGroup $model, bool $runValidation = true): bool
	{
		// Validate the group before doing anything.
		if ($runValidation && !$model->validate()) {
			Craft::info('Search group not saved due to validation error.', __METHOD__);
			return false;
		}

		// Find an existing record or create a new one.
		if ($model->id) {
			$record = SearchGroupRecord::find()
				->where(['id' => $model->id])
				->one();
			if (!$record) {
				throw new SearchGroupNotFoundException('Invalid search group ID: ' . $model->id);
			}
		} else {
			$record = new SearchGroupRecord();
		}

		// Site ID is updating, remove from current 'site' cache.
		if ($model->id && $record->siteId != $model->siteId) {
			$this->_siteSearchGroups->removeItem($model->id, $record->siteId);
		}

		// Handle is updating, remove from current 'handle' cache.
		if ($model->id && $record->siteId != $model->siteId) {
			$this->_searchGroupsByHandle->clearItem($record->handle);
		}

		// Update the record's properties.
		$record->siteId = $model->siteId;
		$record->handle = $model->handle;
		$record->name = $model->name;
		$record->template = $model->template;
		$record->pageSize = $model->pageSize;
		$record->searchOrder = $model->searchOrder;
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
				$this->_cacheSearchGroup($model); // Cache the new record.
			}
			$this->_sortSiteSearchGroups($model->siteId); // Re-sort 'site' cache if necessary.
			$this->_sortAllSearchGroups(); // Re-sort 'all' cache if necessary.

			// Resave all elements.
			$elements = $model->getSearchElements();
			Craft::$app->getDb()->createCommand()
				->delete('{{%reliquary_searchgroupelements}}', ['groupId' => $model->id])
				->execute();
			$sortOrder = 1;
			foreach ($elements as $element) {
				$element->groupId = $model->id;
				$element->sortOrder = $sortOrder;
				$sortOrder += 1;
				if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($element)) {
					$transaction->rollBack();
					return false;
				}
			}

			// Resave all filters.
			$filters = $model->getFilters();
			Craft::$app->getDb()->createCommand()
				->delete('{{%reliquary_searchgroupfilters}}', ['groupId' => $model->id])
				->execute();
			$sortOrder = 1;
			foreach ($filters as $filter) {
				$filter->groupId = $model->id;
				$filter->sortOrder = $sortOrder;
				$sortOrder += 1;
				if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($filter)) {
					$transaction->rollBack();
					return false;
				}
			}

			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}

		return true;
	}

	/**
	 * Deletes a search group by its provided ID.
	 * @param int $groupId The ID of the group to delete.
	 * @return bool True on successful delete, false if no records were deleted.
	 */
	public function deleteGroupById(int $groupId): bool
	{
		$group = $this->getGroupById($groupId);

		return $this->deleteGroup($group);
	}

	/**
	 * Deletes a search group.
	 * @param SearchGroup $group The group to delete.
	 * @return bool True on successful delete, false if no records were deleted.
	 */
	public function deleteGroup(SearchGroup $group): bool
	{
		if ($group == null) {
			return false;
		}

		$transaction = Craft::$app->getDb()->beginTransaction();
		try {
			Craft::$app->getDb()->createCommand()
				->delete(SearchGroupRecord::tableName(), ['id' => $group->id])
				->execute();

			// Remove the group from every cache.
			$this->_searchGroups->clearItem($group->id);
			$this->_searchGroupsByHandle->clearItem($group->handle);
			$this->_allSearchGroups->removeItem($group->id);
			$this->_siteSearchGroups->removeItem($group->id, $group->siteId);

			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}

		return true;
	}

	/**
	 * Reorders search groups based on the provided IDs.
	 * @param int[] $ids The IDs of all search groups, in the new order.
	 * @param bool True on successful update, false on failure.
	 */
	public function reorderGroups(array $ids)
	{
		$transaction = Craft::$app->getDb()->beginTransaction();
		try {
			$sortOrder = 1;
			foreach ($ids as $id) {
				$record = SearchGroupRecord::find()
					->where(['id' => $id])
					->one();
				$record->sortOrder = $sortOrder;
				$record->save();
				$sortOrder += 1;
			}

			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}

		foreach ($this->_siteSearchGroups->getKeys() as $key) {
			$this->_sortSiteSearchGroups($key);
		}

		return true;
	}

	/**
	 * Adds the specified search group to all caches.
	 * @param SearchGroup|null $group The group to add.
	 */
	private function _cacheSearchGroup($group)
	{
		if ($group == null) {
			return;
		}

		$this->_searchGroups->setItem($group, $group->id);
		$this->_searchGroups->finalize($group->id);
		$this->_searchGroupsByHandle->setItem($group, $group->handle);
		$this->_searchGroupsByHandle->finalize($group->handle);
		$this->_allSearchGroups->addItem($group, $group->id);
		$this->_siteSearchGroups->addItem($group, $group->id, $group->siteId);
	}

	/**
	 * Sorts a set of groups stored within the 'site' cache.
	 * @param int $siteId The ID of the site that should have its set of groups
	 * sorted.
	 */
	private function _sortSiteSearchGroups(int $siteId)
	{
		if (!$this->_siteSearchGroups->isFinalized($siteId)) { // Check optional skip.
			return;
		}

		$this->_siteSearchGroups->sortSet(function ($a, $b) {
			return $a->sortOrder - $b->sortOrder;
		}, $siteId);
	}

	/**
	 * Sorts the set of groups stored within the 'all' cache.
	 */
	private function _sortAllSearchGroups()
	{
		if (!$this->_allSearchGroups->isFinalized()) { // Check optional skip.
			return;
		}

		$this->_allSearchGroups->sortSet(function ($a, $b) {
			return $a->id - $b->id;
		});
	}
}
