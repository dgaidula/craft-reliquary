<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\assets;

use craft\web\AssetBundle;

/**
 * Asset bundle for the Control Panel.
 */
class ReliquaryAsset extends AssetBundle
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->sourcePath = __DIR__ . '/dist';

		$this->depends = [
			\craft\web\assets\cp\CpAsset::class,
		];

		$this->js[] = 'js/reliquary.js';

		$this->css[] = 'css/reliquary.css';

		parent::init();
	}
}
