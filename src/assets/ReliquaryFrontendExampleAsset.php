<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\assets;

use craft\web\AssetBundle;

/**
 * Asset bundle for example frontend search UI.
 */
class ReliquaryFrontendExampleAsset extends AssetBundle
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->sourcePath = __DIR__ . '/dist';

		$this->js[] = 'js/reliquary-api.js';
		$this->js[] = 'js/reliquary-sample-search.js';

		$this->css[] = 'css/reliquary-sample-search.css';

		parent::init();
	}
}
