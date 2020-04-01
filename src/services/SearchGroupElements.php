<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\services;

use jaredlindo\reliquary\errors\SearchGroupElementNotFoundException;
use jaredlindo\reliquary\helpers\MultiCache;
use jaredlindo\reliquary\models\SearchGroup;
use jaredlindo\reliquary\models\SearchGroupElement;
use jaredlindo\reliquary\records\SearchGroupElement as SearchGroupElementRecord;

use yii\base\Component;

use Craft;

/**
 * The primary Reliquary service.
 */
class SearchGroupElements extends Component
{
	/**
	  * @var MultiCache Stores sets of elements, keyed by group ID.
	 */
	private $_groupElements;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->_groupElements = new MultiCache();
	}

	/**
	 * Retrieves search elements associated with the given group.
	 * @param SearchGroup $group The group to retrieve search group elements for.
	 * @return SearchGroupElement[] Search group elements associated with the given group.
	 */
	public function getSearchElementsByGroup(SearchGroup $group)
	{
		if ($group == null) {
			return [];
		}

		return $this->getSearchElementsByGroupId($group->id);
	}

	/**
	 * Retrieves search group elements associated with the given group ID.
	 * @param int $groupId The ID of the group to retrieve search group elements for.
	 * @return SearchGroupElement[] Search group elements associated with the given group.
	 */
	public function getSearchElementsByGroupId(int $groupId)
	{
		if (!$groupId) {
			return [];
		}

		if (!$this->_groupElements->isFinalized($groupId)) {
			// Pull ids of any already cached search groups elements.
			$elementIds = array_map(function ($element) {
				return $element->id;
			}, $this->_groupElements->getItems($groupId));

			// Retrieve all other records for the group.
			$records = SearchGroupElementRecord::find()
				->where([
					'and',
					['groupId' => $groupId],
					['not in', 'id', $elementIds],
				])
				->all();

			// Cache the records.
			foreach ($records as $record) {
				$model = SearchGroupElementRecord::createModel($record);
				$this->_cacheSearchElement($model);
			}

			// Finalize and sort the 'group' cache, now that records have been retrieved.
			$this->_groupElements->finalize($groupId);
			$this->_sortGroupElements($groupId);
		}

		return $this->_groupElements->getItems($groupId);
	}

	/**
	 * Creates or updates a search group element based on the provided model.
	 * @param SearchGroupElement $model The search group element to save.
	 * @param bool $runValidation Set to false in order to skip validating the record before saving.
	 * @return bool True on successful save, false on failure.
	 */
	public function saveSearchElement(SearchGroupElement $model, bool $runValidation = true): bool
	{
		// Validate the search group element before doing anything.
		if ($runValidation && !$model->validate()) {
			Craft::info('Search group element not saved due to validation error.', __METHOD__);
			return false;
		}

		// Find an existing record or create a new one.
		if ($model->id) {
			$record = SearchGroupElementRecord::find()
				->where(['id' => $model->id])
				->one();
			if (!$record) {
				throw new SearchGroupModelNotFoundException('Invalid search group element ID: ' . $model->id);
			}
		} else {
			$record = new SearchGroupElementRecord();
		}

		// Group ID is updating, remove from current 'group' cache.
		if ($record->id && $record->groupId != $model->groupId) {
			$this->_groupElements->removeItem($model->id, $record->groupId);
		}

		$record->groupId = $model->groupId;
		$record->elementType = $model->elementType;
		$record->elementTypeId = $model->elementTypeId;
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
				$this->_cacheSearchElement($model); // Cache the new record.
			}
			$this->_sortGroupElements($model->groupId);

			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}

		return true;
	}

	/**
	 * Adds the specified element to all caches.
	 * @param SearchGroupElement|null $element The search group element to add.
	 */
	private function _cacheSearchElement($element)
	{
		if ($element == null) {
			return;
		}

		$this->_groupElements->addItem($element, $element->id, $element->groupId);
	}

	/**
	 * Sorts a set of elements stored within the 'group' cache.
	 * @param int $groupId The ID of the group that should have its set of filters sorted.
	 */
	private function _sortGroupElements(int $groupId)
	{
		if (!$this->_groupElements->isFinalized($groupId)) { // Check optional skip.
			return;
		}

		// Sort the items by their order if finalized.
		$this->_groupElements->sortSet(function ($a, $b) {
			return $a->sortOrder - $b->sortOrder;
		}, $groupId);
	}
}
