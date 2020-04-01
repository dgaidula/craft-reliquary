<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\jobs;

use jaredlindo\reliquary\helpers\Search;

use Craft;
use craft\queue\BaseJob;

use yii\db\Expression;

/**
 * A job that will process queued Reliquary index updates.
 */
class ProcessIndexes extends BaseJob
{
	public $elementId;

	public $siteId;

	public function execute($queue)
	{
		Search::processElementIndex($this->elementId, $this->siteId);
	}

	protected function defaultDescription()
	{
		return 'Updating Reliquary Indexes';
	}
}
