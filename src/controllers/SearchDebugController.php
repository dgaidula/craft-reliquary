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
 * An admin-only endpoint for reviewing internals of Reliquary search
 * functionality.
 */
class SearchDebugController extends Controller
{
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
	 * Performs a search, returning element meta-information without rendering
	 * the underlying template for a group.
	 */
	public function actionRawSearch()
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		$groupKey = Craft::$app->getRequest()->getRequiredParam('group');
		$options = Craft::$app->getRequest()->getRequiredParam('options');

		$page = Craft::$app->getRequest()->getParam('page');
		if (!$page) {
			$page = 1;
		}
		$results = Reliquary::getInstance()->search->doSearch($groupKey, $options, $page);

		if ($results && isset($results['elements'])) {
			$elements = [];
			foreach ($results['elements'] as $element) {
				$elements[] = [
					'id' => $element->id,
					'element' => get_class($element),
					'title' => $element->title,
					'editUrl' => $element->cpEditUrl ?? '',
					'searchScore' => $element->searchScore ?? null,
				];
			}
			$results['elements'] = $elements;
		}

		return $this->asJson($results);
	}

	/**
	 * Retrieves information used to explain the score calculated for a search
	 * result given the provided query.
	 */
	public function actionExplainSearch()
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		$groupKey = Craft::$app->getRequest()->getRequiredParam('group');
		$options = Craft::$app->getRequest()->getRequiredParam('options');
		$element = Craft::$app->getRequest()->getRequiredParam('element');

		$results = Reliquary::getInstance()->search->explainSearch($groupKey, $options, $element);

		return $this->asJson($results);
	}
}
