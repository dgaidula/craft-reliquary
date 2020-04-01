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
 * A controller used for customizing score handling.
 */
class CustomizeController extends Controller
{
	/**
	 * Update the global score settings.
	 */
	public function actionSaveSettings($settings = null)
	{
		$plugin = Craft::$app->getPlugins()->getPlugin('reliquary');
		Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

		return $this->redirect('reliquary');
	}
}
