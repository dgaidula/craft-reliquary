<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\helpers;

use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\db\Query;

/**
 * Provides some static utility methods.
 */
class Search
{
	/**
	 * Creates a set of 3-character ngrams that comprise the input value.
	 * @param string $value The value to build into ngrams.
	 * @return array The ngrams, may be empty if the value is too short.
	 */
	public static function buildNgram($value)
	{
		// This function also performs some string normalization, to ensure
		// punctuation and similar are removed/standardized from string to
		// equivalent string.

		// Craft's method of string normalization differs a bit from the
		// method outlined here. Craft's process (as of writing) is this:
		// - Merge any array values to strings
		// - Strip HTML tags
		// - Convert &nbsp; HTML entities to spaces
		// - Remove all other HTML entities
		// - Convert string to lowercase
		// - Perform basic transliteration with a character lookup table
		// - Remove pre-defined stop words
		// - Replace/condense whitespace
		// - Trim whitespace from final string.

		// The method used within Reliquary differs in that it takes advantage
		// of unicode character properties to handle stripping/collapsing
		// punctuation and whitespace, as well as employing the oft-installed
		// intl extension to perform string normalization. Finally, because of
		// the way the ngram system is handled, real transliteration can be
		// avoided. Instead, the process employed is as follows:
		// - Strip HTML tags
		// - Convert HTML entities to their original characters
		// - Normalize characters (if the intl extension is loaded)
		// - Convert to lowercase
		// - Replace all consecutive punctuation, symbols, and whitespace to
		//   single space characters
		// - Remove all control and mark characters

		// Leaving strings as is, without attempting transliteration, helps to
		// avoid potentially strange or unexpected matching behavior.

		// Remove all HTML tags.
		$value = strip_tags($value);

		$value = html_entity_decode($value, ENT_HTML5, 'UTF-8');

		// If the `intl` extension is present, normalize our string with it.
		if (extension_loaded('intl')) {
			// http://userguide.icu-project.org/transforms/normalization
			// http://www.unicode.org/reports/tr15/#Norm_Forms
			$value = transliterator_transliterate('NFKC; Lower;', $value);
		} else { // Otherwise just go with php's own built-ins.
			// Convert to lower case.
			$value = mb_strtolower($value);
		}

		// Replace all consecutive punctuation, symbols, and whitespace down to single space characters.
		$value = preg_replace('/[\p{P}\p{S}\p{Z}]+/u', ' ', $value);

		// Remove all control and mark characters.
		$value = preg_replace('/[\p{C}\p{M}]/u', '', $value);

		// Add new keys to the ngram data.
		$values = [];
		$cap = mb_strlen($value) - 3;
		for ($index = 0; $index <= $cap; $index += 1) {
			$values[] = mb_substr($value, $index, 3);
		}

		return $values;
	}

	/**
	 * Updates the index for the given element and site.
	 * @param int elementId The ID of the element to index.
	 * @param int siteId The ID of the site to index the content of.
	 */
	public static function processElementIndex($elementId, $siteId)
	{
		// Retrieve all queued IDs of values that need to be indexed (in case of duplicates).
		$indexQueueIds = (new Query())
			->select('MAX(id)')
			->from('{{%reliquary_indexqueue}}')
			->where(['elementId' => $elementId])
			->where(['siteId' => $siteId])
			->groupBy([
				'fieldId',
				'attribute',
			])
			->column();

		if (empty($indexQueueIds)) {
			// Nothing to update, may have already been handled in a previous pass.
			return;
		}

		// Retrieve the actual values using the IDs.
		$indexValues = (new Query())
			->select([
				'fieldId',
				'attribute',
				'value',
			])
			->from('{{%reliquary_indexqueue}}')
			->where(['id' => $indexQueueIds])
			->all();

		// Retrieve index IDs for this element.
		$indexIds = (new Query())
			->select([
				'id',
				'fieldId',
				'attribute',
			])
			->from('{{%reliquary_ngramindex}}')
			->where([
				'elementId' => $elementId,
				'siteId' => $siteId,
			])
			->all();

		// Map the retrieved index IDs to an array based on site id and field/attribute id.
		$indexMap = [];
		foreach ($indexIds as $indexId) {
			if (!isset($indexMap[$indexId['fieldId'] ?? $indexId['attribute']])) {
				$indexMap[$indexId['fieldId'] ?? $indexId['attribute']] = $indexId['id'];
			}
		}

		// Store all of the current index IDs, remove them as ngrams are generated.
		$indexesToDelete = [];
		foreach ($indexIds as $indexId) {
			$indexesToDelete[$indexId['id']] = true;
		}

		// Generate new indexes to batch insert.
		$newIndexes = []; // Set of new indexes to be batch inserted.
		foreach ($indexValues as $dataIndex => $newIndex) {
			if (isset($indexMap[$newIndex['fieldId'] ?? $newIndex['attribute']])) {
				$newIndex['id'] = $indexMap[$newIndex['fieldId'] ?? $newIndex['attribute']]; // Use the existing index ID.
				unset($indexesToDelete[$newIndex['id']]); // Remove from list to delete.
			} else {
				$newIndex['offset'] = count($newIndexes); // Store new index offset.
				$newIndexes[] = [
					$elementId,
					$siteId,
					$newIndex['fieldId'],
					$newIndex['attribute'],
				];
			}
			$indexValues[$dataIndex] = $newIndex;
		}

		// Insert new indexes for the element all at once.
		Craft::$app->getDb()->createCommand()
			->batchInsert('{{%reliquary_ngramindex}}', [
				'elementId',
				'siteId',
				'fieldId',
				'attribute',
			], $newIndexes, false)
			->execute();

		// Store first ID of the batched indexes.
		$firstId = Craft::$app->getDb()->getLastInsertID();

		// Delete old data for the element before inserting.
		if (Craft::$app->getDb()->getIsMysql()) {
			$sql = <<<EOT
DELETE
	dt
FROM
	{{%reliquary_ngramdata}} dt
INNER JOIN
	{{%reliquary_ngramindex}} idx
ON
	dt.`indexId` = idx.`id`
WHERE
	idx.`elementId` = :elementId
	AND idx.`siteId` = :siteId
EOT;
		} else {
			$sql = <<<EOT
DELETE FROM
	{{%reliquary_ngramdata}} dt
USING
	{{%reliquary_ngramindex}} idx
WHERE
	dt."indexId" = idx."id"
	AND idx."elementId" = :elementId
	AND idx."siteId" = :siteId
EOT;
		}
		Craft::$app->getDb()->createCommand($sql, [
				'elementId' => $elementId,
				'siteId' => $siteId,
			])
			->execute();

		// Delete old indexes that no longer exist.
		if (count($indexesToDelete)) {
			Craft::$app->getDb()->createCommand()
				->delete('{{%reliquary_ngramindex}}', [
					'id' => array_keys($indexesToDelete),
				])
				->execute();
		}

		// Generate new data to batch insert.
		$newData = []; // Set of new data to be batch inserted.
		foreach ($indexValues as $newIndex) {
			// Build ngrams from the value to index.
			// Wrap it in spaces to ensure values with a single character become
			// at least 1 ngram.
			$ngrams = Search::buildNgram(' ' . $newIndex['value'] . ' ');

			// Add the ngrams generated to the data batch.
			foreach ($ngrams as $key => $ngram) {
				$newData[] = [
					$newIndex['id'] ?? ($firstId + $newIndex['offset']),
					$key,
					$ngram,
				];
			};
		}

		// Insert new data for the element all at once.
		Craft::$app->getDb()->createCommand()
			->batchInsert('{{%reliquary_ngramdata}}', [
				'indexId',
				'offset',
				'key',
			], $newData, false)
			->execute();

		// Update the index table with the new ngram counts.
		if (Craft::$app->getDb()->getIsMysql()) {
			$sql = <<<EOT
UPDATE
	{{%reliquary_ngramindex}} idx
INNER JOIN (
	SELECT
		`indexId`
		, COUNT(*) as ngrams
	FROM
		{{%reliquary_ngramdata}}
	GROUP BY
		`indexId`
) dt
ON
	dt.`indexId` = idx.`id`
SET
	idx.`ngrams` = dt.`ngrams`
WHERE
	idx.`elementId` = :elementId
	AND idx.`siteId` = :siteId
EOT;
		} else {
			$sql = <<<EOT
UPDATE
	{{%reliquary_ngramindex}} idx
SET
	"ngrams" = dt."ngrams"
FROM (
	SELECT
		"indexId"
		, COUNT(*) as ngrams
	FROM
		{{%reliquary_ngramdata}}
	GROUP BY
		"indexId"
) dt
WHERE
	idx."elementId" = :elementId
	AND idx."siteId" = :siteId
EOT;
		}
		Craft::$app->getDb()->createCommand($sql, [
				'elementId' => $elementId,
				'siteId' => $siteId,
			])
			->execute();

		// Clear index queue.
		Reliquary::getInstance()->search->clearPendingIndexQueue($elementId, $siteId);
	}
}
