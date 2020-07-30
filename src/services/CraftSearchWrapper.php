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
	 * @param array $fieldHandles The fields whose indexes should be updated.
	 * @return bool True if indexing was aborted early, false if not.
	 */
	private function _indexElementAttributes(ElementInterface $element, array $fieldHandles = null): bool
	{
		if (Reliquary::shouldDiscardIndex(get_class($element))) { // If this element should have its indexes discarded.
			return true;
		}

		return $this->_oldService->indexElementAttributes($element, $fieldHandles);
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

		return $this->_oldService->indexElementFields($elementId, $siteId, $fields);
	}
}
