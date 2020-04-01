<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\controllers;

use jaredlindo\reliquary\Reliquary;
use jaredlindo\reliquary\models\SearchGroup;
use jaredlindo\reliquary\models\SearchGroupElement;
use jaredlindo\reliquary\models\SearchGroupFilter;

use Craft;
use craft\web\Controller;

use yii\helpers\Json;

/**
 * A controller use in managing groups.
 */
class GroupsController extends Controller
{
	/**
	 * Display the edit group screen.
	 */
	public function actionEditGroup(int $groupId = null, SearchGroup $group = null)
	{
		if (!$group && $groupId) {
			$group = Reliquary::getInstance()->searchGroups->getGroupById($groupId);
			if (!$group) {
				throw new NotFoundHttpException('Invalid group ID: ' . $groupId);
			}
		}

		if (!$group) {
			$group = new SearchGroup();
		}

		$data = [
			'group' => $group,
		];

		return $this->renderTemplate('reliquary/groups/_edit', $data);
	}

	/**
	 * Save or update a new or existing group.
	 */
	public function actionSaveGroup()
	{
		$this->requirePostRequest();

		// Retrieve/create model.
		$group = null;
		$groupId = Craft::$app->getRequest()->getBodyParam('id');
		if ($groupId) {
			$group = Reliquary::getInstance()->searchGroups->getGroupById($groupId);
		} else {
			$group = new SearchGroup();
		}
		$group->name = Craft::$app->getRequest()->getRequiredBodyParam('name');
		$group->handle = Craft::$app->getRequest()->getRequiredBodyParam('handle');
		$group->siteId = Craft::$app->getRequest()->getRequiredBodyParam('siteId');
		$group->template = Craft::$app->getRequest()->getRequiredBodyParam('template');
		$group->pageSize = Craft::$app->getRequest()->getRequiredBodyParam('pageSize');
		$group->searchOrder = Craft::$app->getRequest()->getRequiredBodyParam('searchOrder');
		$elements = Craft::$app->getRequest()->getBodyParam('elements', []);
		foreach ($elements as $key => $rawElement) {
			$element = new SearchGroupElement();
			$element->elementType = $rawElement['type'];
			$element->elementTypeId = $rawElement['typeId'];
			$elements[$key] = $element;
		}
		$group->overrideSearchElements(array_values($elements));
		$filters = Craft::$app->getRequest()->getBodyParam('filters', []);
		foreach ($filters as $key => $rawFilter) {
			$filter = new SearchGroupFilter();
			$filter->fieldId = $rawFilter['fieldId'];
			$filter->attribute = $rawFilter['attribute'];
			$filter->handle = $rawFilter['handle'];
			$filter->name = $rawFilter['name'];
			$filters[$key] = $filter;
		}
		$group->overrideFilters(array_values($filters));

		if (!Reliquary::getInstance()->searchGroups->saveGroup($group)) {
			Craft::$app->getSession()->setError(Craft::t('reliquary', 'Couldn’t save search group.'));

			Craft::$app->getUrlManager()->setRouteParams([
				'group' => $group
			]);

			return null;
		}

		return $this->redirectToPostedUrl();
	}

	/**
	 * Change the order of a specified set of groups.
	 */
	public function actionReorderGroups()
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		$ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
		Reliquary::getInstance()->searchGroups->reorderGroups($ids);

		return $this->asJson(['success' => true]);
	}

	/**
	 * Delete a group.
	 */
	public function actionDeleteGroup()
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		$id = Craft::$app->getRequest()->getRequiredBodyParam('id');
		Reliquary::getInstance()->searchGroups->deleteGroupById($id);

		return $this->asJson(['success' => true]);
	}
}
