<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary;

use jaredlindo\reliquary\helpers\Search as SearchHelper;
use jaredlindo\reliquary\jobs\ProcessIndexes;
use jaredlindo\reliquary\services\Search;
use jaredlindo\reliquary\services\SearchGroups;
use jaredlindo\reliquary\services\SearchGroupElements;
use jaredlindo\reliquary\services\SearchGroupFilters;

use Craft;
use craft\base\Plugin;
use craft\controllers\UtilitiesController;
use craft\events\PluginEvent;
use craft\events\ElementEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Plugins;
use craft\services\Elements;
use craft\web\Application;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use Yii;
use yii\base\ActionEvent;
use yii\base\Event;

/**
 * The main Craft plugin class.
 */
class Reliquary extends Plugin
{
	/**
	 * @event ReliquaryGetFieldOptions Retrieve options available to a field.
	 * @see jaredlindo\reliquary\events\ReliquaryGetFieldOptions
	 */
	const EVENT_RELIQUARY_GET_FIELD_OPTIONS = 'reliquaryGetFieldOptions';

	/**
	 * @event ReliquaryGetElementTypes Provides element types that can be searched.
	 * @see jaredlindo\reliquary\events\ReliquaryGetElementTypes
	 */
	const EVENT_RELIQUARY_GET_ELEMENT_TYPES = 'reliquaryGetElementTypes';

	/**
	 * @event ReliquaryGetElementAttributes Retrieve attributes available on an
	 * element.
	 * @see jaredlindo\reliquary\events\reliquaryGetElementAttributes
	 */
	const EVENT_RELIQUARY_GET_ELEMENT_ATTRIBUTES = 'reliquaryGetElementAttributes';

	/**
	 * @event ReliquaryGetAttributeOptions Retrieve options available for
	 * attributes on an element.
	 * @see jaredlindo\reliquary\events\ReliquaryGetAttributeOptions
	 */
	const EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS = 'ReliquaryGetAttributeOptions';

	/**
	 * @event ReliquaryExtendElementTypeQuery Extends a search query in order to
	 * retrieve a specfic type of element.
	 * @see jaredlindo\reliquary\events\ReliquaryExtendElementTypeQuery
	 */
	const EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY = 'reliquaryExtendElementTypeQuery';

	/**
	 * @event ReliquaryModifyFilterQuery Extends a search query in order to
	 * filter by the specified values set for a field.
	 * @see jaredlindo\reliquary\events\ReliquaryModifyFilterQuery
	 */
	const EVENT_RELIQUARY_MODIFY_FILTER_QUERY = 'reliquaryModifyFilterQuery';

	/**
	 * @event ReliquaryModifyAttributeQuery Extends a search query in order to
	 * filter by the specified values set for an element attribute.
	 * @see jaredlindo\reliquary\events\ReliquaryModifyAttributeQuery
	 */
	const EVENT_RELIQUARY_MODIFY_ATTRIBUTE_QUERY = 'reliquaryModifyAttributeQuery';

	/**
	 * @event ReliquaryGetElements Provides element objects as the result of an
	 * underlying query.
	 * @see jaredlindo\reliquary\events\ReliquaryGetElements
	 */
	const EVENT_RELIQUARY_GET_ELEMENTS = 'reliquaryGetElements';

	/**
	 * Elements that will not be indexed
	 * @see jaredlindo\reliquary\Reliquary::shouldDiscardIndex()
	 */
	const ELEMENT_NOINDEX_LIST = [
		'craft\\elements\\MatrixBlock' => true,
		'verbb\\supertable\\elements\\SuperTableBlockElement' => true,
	];

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function init()
	{
		// Register plugin services.
		$this->setComponents([
			'search' => Search::class,
			'searchGroups' => SearchGroups::class,
			'searchGroupElements' => SearchGroupElements::class,
			'searchGroupFilters' => SearchGroupFilters::class,
		]);

		// Wrap Craft's native search service with Reliquary's, in order to intercept calls to it.
		Event::on(
			Application::class,
			Application::EVENT_INIT,
			function () {
				Craft::$app->set('search', Yii::createObject(\jaredlindo\reliquary\services\CraftSearchWrapper::class));
			}
		);

		// Redirect to the control panel section for Reliquary after install.
		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_INSTALL_PLUGIN,
			function (PluginEvent $event) {
				if ($event->plugin === $this) {
					if (Craft::$app->getRequest()->isCpRequest) {
						Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('reliquary'));
					}
				}
			}
		);

		// If the search index is rebuilt, trigger Reliquary's rebuild behavior as well.
		Event::on(
			UtilitiesController::class,
			UtilitiesController::EVENT_BEFORE_ACTION,
			function (ActionEvent $event) {
				// Only for this specific action.
				if ($event->action->id != 'search-index-perform-action') {
					return;
				}

				// See \craft\controllers\UtilitiesController::actionSearchIndexPerformAction()
				// Pull parameters just as it does, and check the `start` parameter.
				// Only clear the table on first call.
				$params = Craft::$app->getRequest()->getRequiredBodyParam('params');
				if (!empty($params['start'])) {
					$this->search->clearIndexTables();
				}
			}
		);

		// After an element has been manually reindexed, manually process the Reliquary index.
		Event::on(
			UtilitiesController::class,
			UtilitiesController::EVENT_AFTER_ACTION,
			function (ActionEvent $event) {
				// Only for this specific action.
				if ($event->action->id != 'search-index-perform-action') {
					return;
				}

				// See \craft\controllers\UtilitiesController::actionSearchIndexPerformAction()
				// Pull parameters just as it does, and check the `start` parameter.
				// Only process when called for an element.
				$params = Craft::$app->getRequest()->getRequiredBodyParam('params');
				if (!empty($params['id'])) {

					$class = $params['type'];

					if (Reliquary::shouldDiscardIndex($class)) { // If this element should have its indexes discarded.
						return;
					}

					if ($class::isLocalized()) {
						$siteIds = Craft::$app->getSites()->getAllSiteIds();
					} else {
						$siteIds = [Craft::$app->getSites()->getPrimarySite()->id];
					}

					foreach ($siteIds as $siteId) {
						SearchHelper::processElementIndex($params['id'], $siteId);
					}
				}
			}
		);

		// Ensure ngram indexes are cleaned up after an element is deleted.
		Event::on(
			Elements::class,
			Elements::EVENT_AFTER_DELETE_ELEMENT,
			function (ElementEvent $event) {
				$this->search->deleteIndexDataForElement($event->element);
			}
		);

		// Clear any pending index updates that haven't yet processed, just in
		// case multiple saves trigger in rapid succession before the queue has
		// a chance to process.
		Event::on(
			Elements::class,
			Elements::EVENT_BEFORE_SAVE_ELEMENT,
			function(ElementEvent $event) {
				if ($event->element->id) { // No ID for new entries, skip clearing.
					$this->search->clearPendingIndexQueue($event->element->id, $event->element->siteId);
				}
			}
		);

		// Queue the job that updates indexes after an element is saved.
		Event::on(
			Elements::class,
			Elements::EVENT_AFTER_SAVE_ELEMENT,
			function(ElementEvent $event) {
				if (Reliquary::shouldDiscardIndex(get_class($event->element))) { // If this element should have its indexes discarded.
					return;
				}
				Craft::$app->queue->push(new ProcessIndexes([
					'elementId' => $event->element->id,
					'siteId' => $event->element->siteId,
				]));
			}
		);

		// Set up all Reliquary routes.
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$event->rules['reliquary/groups/new'] = 'reliquary/groups/edit-group';
				$event->rules['reliquary/groups/<groupId:\d+>'] = 'reliquary/groups/edit-group';
			}
		);

		// Add Reliquary plugin directly to the craft object available via Twig.
		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function (Event $event) {
				$variable = $event->sender;
				$variable->set('reliquary', $this);
			}
		);

		\jaredlindo\reliquary\adapters\AttributeOptions::setup();
		\jaredlindo\reliquary\adapters\FieldOptions::setup();
		\jaredlindo\reliquary\adapters\ElementBuilders::setup();
		\jaredlindo\reliquary\adapters\ElementTypeQueries::setup();
		\jaredlindo\reliquary\adapters\ElementTypes::setup();
		\jaredlindo\reliquary\adapters\FilterQueries::setup();

		parent::init();
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function getCpNavItem()
	{
		$item = parent::getCpNavItem();
//		$item['badgeCount'] = '!';

		$item['subnav'] = [
			'dashboard' => [
				'label' => Craft::t('reliquary', 'Dashboard'),
				'url' => 'reliquary',
			],
			'groups' => [
				'label' => Craft::t('reliquary', 'Search Groups'),
				'url' => 'reliquary/groups',
			],
			'customize' => [
				'label' => Craft::t('reliquary', 'Score Customization'),
				'url' => 'reliquary/customize',
			],
		];

		return $item;
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	protected function createSettingsModel()
	{
		return new \jaredlindo\reliquary\models\Settings();
	}

	/**
	 * Used to determine if a given element should not be indexed on its own.
	 *
	 * Craft natively indexes all content for an element by field, but some
	 * fields, such as Craft's native Matrix field, or plugins such as Super
	 * Table, create elements that also wind up individually being indexed,
	 * meaning their content is stored in duplicate (or triplicate, etc.
	 * depending on nesting). Considering the utility of doing text search for
	 * individual Matrix blocks instead of their containing element, Reliquary
	 * will not index content for the underlying elements generated by these
	 * fields, in order to improve performance by minimizing data that needs to
	 * be stored/updated on entry save, and by reducing the data stored within
	 * both Reliquary's own and Craft's search tables.
	 *
	 * @param string $elementClass The element's class.
	 * @return boolean True if the index data should be discarded.
	 */
	public static function shouldDiscardIndex(string $elementClass) {
		return isset(Reliquary::ELEMENT_NOINDEX_LIST[$elementClass]);
	}
}
