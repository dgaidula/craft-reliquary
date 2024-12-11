<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\controllers;

use jaredlindo\reliquary\Reliquary;
use jaredlindo\reliquary\errors\SearchGroupNotFoundException;

use Craft;
use craft\web\Controller;

use yii\web\NotFoundHttpException;

/**
 * A publicly-accessible endpoint for Reliquary search functionality.
 */
class SearchController extends Controller
{
	// All functions within this controller are publicly/anonymously accessible.
	protected array|int|bool $allowAnonymous = true;
	public $enableCsrfValidation = false;

	/**
	 * @inheritdoc
	 */
	public function init(): void
	{
		parent::init();

		if (Craft::$app->getRequest()->getIsCpRequest()) {
			throw new NotFoundHttpException('Action cannot be called through the control panel.');
		}
	}

	/**
	 * Performs a search.
	 */
	public function actionSearch()
	{
		$this->requirePostRequest();

		$groupId = Craft::$app->getRequest()->getRequiredParam('group');
		$options = Craft::$app->getRequest()->getRequiredParam('options');

		$page = Craft::$app->getRequest()->getParam('page');
		if (!$page) {
			$page = 1;
		}
		$results = Reliquary::getInstance()->search->doSearch($groupId, $options, $page);

		$group = Reliquary::getInstance()->searchGroups->getGroupById($groupId); // Should exist if we've gotten this far.
		$data = ['results'=>$results,'options'=>$options];
		return $this->renderTemplate($group->template, $data);
	}

	/**
	 * Retrieve all search groups available on the current site.
	 */
	public function actionGetSearchGroups()
	{
		$this->requireAcceptsJson();

		$siteId = Craft::$app->getSites()->getCurrentSite()->id;
		$groups = Reliquary::getInstance()->searchGroups->getGroupsBySiteId($siteId);

		$results = [];
		foreach ($groups as $group) {
			$results[] = [
				'id' => $group->id,
				'handle' => $group->handle,
				'name' => $group->name,
			];
		}

		return $this->asJson($results);
	}

	/**
	 * Retrieve all filters available in a search group.
	 */
	public function actionGetFilters()
	{
		$this->requireAcceptsJson();

		$groupId = Craft::$app->getRequest()->getRequiredParam('group');
		$group = Reliquary::getInstance()->searchGroups->getGroupById($groupId);

		// No group found, throw an error.
		if (!$group) {
			throw new SearchGroupNotFoundException('Invalid search group: ' . $groupId);
		}

		$filters = Reliquary::getInstance()->searchGroupFilters->getFiltersByGroup($group);

		$results = [];
		foreach ($filters as $filter) {
			$results[] = [
				'id' => $filter->id,
				'handle' => $filter->handle,
				'name' => $filter->name,
			];
		}

		return $this->asJson($results);
	}

	/**
	 * Retrieve filter meta information and options.
	 */
	public function actionGetFilterOptions()
	{
		$this->requireAcceptsJson();

		$filterId = Craft::$app->getRequest()->getRequiredParam('filter');
		$hint = Craft::$app->getRequest()->getParam('hint');
		$option = Reliquary::getInstance()->search->getOptions($filterId, $hint);

		$result = [
			'type' => $option->type,
			'partial' => $option->partial,
			'hint' => $option->hint,
			'options' => $option->options,
			'total' => $option->total,
		];

		return $this->asJson($result);
	}
}
