<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\services;

use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;

use yii\base\Component;
use yii\helpers\Inflector;

/**
 * A service that intercepts calls to Craft's native Search service, performing
 * Reliquary's own indexing behavior and then forwarding calls as becessary to
 * craft's original Search service.
 */
class CraftSearchWrapper extends Component
{
	/**
	 * A handle to the old service that is now replaced.
	 */
	private $_oldService;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->_oldService = Craft::$app->getSearch();
	}

	/**
	 * Used to forward any unhandled properties to the wrapped service.
	 */
	public function __get($name)
	{
		return $this->_oldService->$name;
	}

	/**
	 * Used to forward any unhandled calls to the wrapped service.
	 */
	public function __call($name, $arguments)
	{
		return call_user_func_array([$this->_oldService, $name], $arguments);
	}

	/**
	 * Indexes the attributes of a given element defined by its element type.
	 * @param ElementInterface $element The element to index the attributes of.
	 * @return bool True if indexing succeeded, false if not.
	 */
	public function indexElementAttributes(ElementInterface $element): bool
	{
		if (Reliquary::shouldDiscardIndex(get_class($element))) { // If this element should have its indexes discarded.
			return true;
		}

		$searchableAttributes = $element::searchableAttributes();

		$searchableAttributes[] = 'slug';

		if ($element::hasTitles()) {
			$searchableAttributes[] = 'title';
		}

		$transaction = Craft::$app->getDb()->beginTransaction();

		try {
			foreach ($searchableAttributes as $attribute) {
				$value = $element->getSearchKeywords($attribute);
				$this->queueSearchIndex($element->id, $element->siteId, $attribute, null, $value);
			}
			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}

		// ----- THIS IS COPIED RIGHT FROM CRAFT -----
		// Acquire a lock for this element/site ID
		$mutex = Craft::$app->getMutex();
		/** @var Element $element */
		$lockKey = "searchindex:{$element->id}:{$element->siteId}";
		if (!$mutex->acquire($lockKey)) {
			// Not worth waiting around; for all we know the other process has newer search attributes anyway
			return true;
		}

		// Custom fields too?
		if ($element::hasContent() && ($fieldLayout = $element->getFieldLayout()) !== null) {
			$keywords = [];
			foreach ($fieldLayout->getFields() as $field) {
				/** @var Field $field */
				if ($field->searchable) {
					// Set the keywords for the content's site
					$fieldValue = $element->getFieldValue($field->handle);
					$fieldSearchKeywords = $field->getSearchKeywords($fieldValue, $element);
					$keywords[$field->id] = $fieldSearchKeywords;
				}
			}

		// ------ END COPY -----
			$this->_indexFields($element->id, $element->siteId, $keywords);
		// ----- THIS IS COPIED RIGHT FROM CRAFT -----
		}

		// Release the lock
		$mutex->release($lockKey);
		// ----- END COPY -----

		return $this->_oldService->indexElementAttributes($element);
	}

	/**
	 * Indexes the field values for a given element and site.
	 * @param int $elementId The ID of the element getting indexed.
	 * @param int $siteId The site ID of the content getting indexed.
	 * @param array $fields The field values, indexed by field ID.
	 * @return bool True if indexing succeeded, false if not.
	 */
	public function indexElementFields(int $elementId, int $siteId, array $fields): bool
	{
		// Get the element type.
		$element = (new Query())
			->select(['type'])
			->from('{{%elements}}')
			->where(['id' => $elementId])
			->one();

		if (Reliquary::shouldDiscardIndex($element['type'])) { // If this element should have its indexes discarded.
			return true;
		}

		$this->_indexFields($elementId, $siteId, $fields);

		return $this->_oldService->indexElementFields($elementId, $siteId, $fields);
	}

	/**
	 * Indexes the field values for a given element and site.
	 * @param int $elementId The ID of the element getting indexed.
	 * @param int $siteId The site ID of the content getting indexed.
	 * @param array $fields The field values, indexed by field ID.
	 * @return bool True if indexing succeeded, false if not.
	 */
	private function _indexFields(int $elementId, int $siteId, array $fields)
	{
		$transaction = Craft::$app->getDb()->beginTransaction();

		try {
			foreach ($fields as $fieldId => $value) {
				$this->queueSearchIndex($elementId, $siteId, null, $fieldId, $value);
			}
			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}
	}

	/**
	 * Queues a value to be indexed at a later time.
	 * @param int $elementId The ID of the element being indexed.
	 * @param int $siteId The ID of site containing the element.
	 * @param string $attribute The name of the attribute containing the
	 * content, if applicable.
	 * @param int $fieldId The ID of the field containing the content, if
	 * applicable.
	 * @param string $value The content being indexed.
	 */
	private function queueSearchIndex($elementId, $siteId, $attribute, $fieldId, $value)
	{
		Craft::$app->getDb()->createCommand()
			->insert('{{%reliquary_indexqueue}}', [
				'elementId' => $elementId,
				'siteId' => $siteId,
				'fieldId' => $fieldId,
				'attribute' => $attribute,
				'value' => $value,
			], false)
			->execute();
	}
}
