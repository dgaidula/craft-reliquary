<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\services;

use jaredlindo\reliquary\errors\ConflictingAttributeOptionsException;
use jaredlindo\reliquary\errors\DuplicateSearchGroupFilterException;
use jaredlindo\reliquary\errors\NoModifyFilterQueryHandlerException;
use jaredlindo\reliquary\errors\NoGetFieldOptionsHandlerException;
use jaredlindo\reliquary\errors\NoGetAttributeOptionsHandlerException;
use jaredlindo\reliquary\errors\NoGetElementsHandlerException;
use jaredlindo\reliquary\errors\SearchGroupNotFoundException;
use jaredlindo\reliquary\errors\SearchGroupFilterNotFoundException;
use jaredlindo\reliquary\events\ReliquaryExtendElementTypeQuery;
use jaredlindo\reliquary\events\ReliquaryGetAttributeOptions;
use jaredlindo\reliquary\events\ReliquaryGetElements;
use jaredlindo\reliquary\events\ReliquaryGetElementTypes;
use jaredlindo\reliquary\events\ReliquaryGetFieldOptions;
use jaredlindo\reliquary\events\ReliquaryModifyFilterQuery;
use jaredlindo\reliquary\helpers\Search as SearchHelper;
use jaredlindo\reliquary\models\CustomFieldWeight;
use jaredlindo\reliquary\records\CustomFieldWeight as CustomFieldWeightRecord;
use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\base\Element;
use craft\db\Query;

use yii\base\Component;
use yii\base\Event;
use yii\db\Expression;

/**
 * A service regarding general search capabilities.
 */
class Search extends Component
{
	/**
	 * Retrieves all element types that can be selected and the available
	 * attributes that can be filtered on each.
	 * @return array The array of element types, which each have a `name`, an
	 * array of `attributes` (each with a `name` and `handle`), and an array
	 * of `subtypes` (each with a `name` and field `layoutId`), representing
	 * individual groupings of the given element type, such as Entry Types or
	 * Category Groups.
	 */
	public function getAvailableElements()
	{
		$event = new ReliquaryGetElementTypes();
		Event::trigger(Reliquary::class, Reliquary::EVENT_RELIQUARY_GET_ELEMENT_TYPES, $event);
		return $event->elementTypes;
	}

	/**
	 * Retrieves all field groups associated with element types that are
	 * searchable.
	 * @return array An array of layouts. Each layout is keyed by the layout ID,
	 * and each layout itself is an array of field IDs.
	 */
	public function getFieldLayouts()
	{
		$layoutsQuery = (new Query())
			->select([
				'layoutId' => 'fl.id',
				'elementType' => 'fl.type',
				'fieldId' => 'flf.fieldId',
			])
			->from('{{%fieldlayouts}} fl')
			->leftJoin('{{%fieldlayoutfields}} flf', 'fl.id = flf.layoutId')
			->all();

		$layouts = [];
		foreach ($layoutsQuery as $layout) {
			// Don't add layouts for unrecognized (unsearchable) elements.
			if (!class_exists($layout['elementType'])) { // Field layouts may be associated with invalid classes (deleted plugins).
				continue;
			}
			if (!Event::hasHandlers($layout['elementType'], Reliquary::EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY)) {
				continue;
			}

			// Build a storage array for fields in this layout, if one doesn't exist yet.
			if (!isset($layouts[$layout['layoutId']])) {
				$layouts[$layout['layoutId']] = [];
			}

			if ($layout['fieldId']) { // Some may be null (empty layouts).
				$layouts[$layout['layoutId']][] = $layout['fieldId'];
			}
		}
		return $layouts;
	}

	/**
	 * Retrieves all fields that have Reliquary option handlers.
	 * @return array An array of fields.
	 */
	public function getAvailableFields()
	{
		$fields = [];
		foreach (Craft::$app->getFields()->getAllFields() as $fieldModel) {
			$field = [
				'name' => $fieldModel->name
			];
			// Make sure that this is a supported field.
			if (Event::hasHandlers(get_class($fieldModel), Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS)) {
				$field['type'] = $fieldModel::displayName();
			} else {
				$field['type'] = null;
			}

			$fields[$fieldModel->id] = $field;
		}
		return $fields;
	}

	/**
	 * Retrieves options that can be chosen for a given filter.
	 * @param int $filterId The ID of the filter to retrieve options for.
	 * @param mixed $hint The hint to provide to the underlying option adapter,
	 * if any.
	 * @return mixed Either the `ReliquaryGetFieldOptions` event or the
	 * `ReliquaryGetAttributeOptions` event, depending on the underlying filter.
	 */
	public function getOptions($filterId, $hint = null)
	{
		$filter = Reliquary::getInstance()->searchGroupFilters->getFilterById($filterId);
		if ($filter->fieldId) {
			$field = $filter->getField();
			$event = new ReliquaryGetFieldOptions([
				'field' => $field,
				'hint' => $hint,
			]);
			$field->trigger(Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS, $event);
			if (!$event->handled) {
				throw new NoGetFieldOptionsHandlerException('No options handler for ' . get_class($field));
			}
			return $event;
		} else {
			$group = $filter->getGroup();
			$handled = [];
			foreach ($group->getSearchElements() as $element) {
				$event = new ReliquaryGetAttributeOptions([
					'attribute' => $filter->attribute,
					'hint' => $hint,
				]);
				Event::trigger($element->elementType, Reliquary::EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS, $event);
				if ($event->handled) {
					$handled[] = $event;
				}
			}
			// If the attribute was provided by multiple elements, try to ensure they have a matching option set.
			foreach ($handled as $event) {
				if ($event->type != $handled[0]->type
					|| $event->total != $handled[0]->total) {
						throw new ConflictingAttributeOptionsException('Multiple attribute options provided for ' . $filter->attribute);
				}
			}
			if (!count($handled)) {
				throw new NoGetAttributeOptionsHandlerException('No options handler for ' . $filter->attribute);
			}
			return $handled[0];
		}
	}

	/**
	 * Performs a search.
	 * @param int $groupId The ID or handle of the search group to use in the search.
	 * @param mixed $options The search term and filters to perform the search
	 * with. An option provided with a null filter is a general search term.
	 * @param int $page The page of results to retrieve, result count per page
	 * depends upon the search group itself.
	 * @param bool $skipRecording Pass true to explicitly ignore recording
	 * statistics about the search term and filters used.
	 * @return array A structured array containing:
	 * `totalElements` - Count of all elements that match the provided filters.
	 * `totalPages` - Count of pages that match the provided filters, based
	 *   on the group configuration.
	 * `firstElement` - Index of the first element in the page, 1-based.
	 * `lastElement` - Index of the last element in the page, 1-based.
	 * `elements` - An array of elements that are on the current page.
	 * `currentPage` - The page searched for.
	 * `previousPage` - The index of the previous page, or null if one does not
	 *   exist.
	 * `nextPage` - The index of the next page, or null if one does not exist.
	 * `pageSize` - Elements provided per page.
	 * `queryTime` - The time it took the request to be parsed, executed, and
	 *   returned.
	 */
	public function doSearch(int $groupId, $options, $page = 1, $skipRecording = false)
	{
		$queryTime = microtime(true);

		$group = Reliquary::getInstance()->searchGroups->getGroupById($groupId);

		// No group found, throw an error.
		if (!$group) {
			throw new SearchGroupNotFoundException('Invalid search group: ' . $groupId);
		}

		$searchElements = $group->getSearchElements();
		$filters = [];
		foreach ($group->getFilters() as $filter) {
			$filters[$filter->id] = $filter;
		};

		// At this point there are a number of routes that could be taken to
		// determine the final results of a query. All in all, we have a set of
		// elements that must be filtered, some potentially concrete criteria
		// through relations or another similar index, and potentially some
		// text that needs to be searched.

		// To facilitate speed when searching, we need to determine what
		// criteria are most important to filter by first in order to retrieve
		// a reasonable subset of elements to inspect more thoroughly.

		// In Craft's own searching mechanism, the query is built from the base
		// set of criteria, such as status, creation date, titles, fields,
		// and so on, and then used to retrieve a set of potential element IDs.

		// These IDs are then sent to the search service and the provided query
		// is used to further filter out elements that are not relevant based on
		// the provided keywords. It also comes with handy features for
		// searching content columns, titles, etc. that are parsed from the
		// (generally) client-provided search string.

		// These other index-like searching features are handled through the
		// options provided to Reliquary, so we don't need the same kind of
		// extra search features, we're only dealing with keywords.

		// When using only keywords, it is possible that our keywords may narrow
		// down a search much more than the other (site/element) criteria does.
		// This is especially true in cases where one is searching large groups
		// that cover multiple sections or a large number of entries. Instead,
		// what we are able to do as a rough heuristic, is gather usage
		// statistics of our required ngrams, and use that to make a guess about
		// how we should structure our queries and approach retrieving the data.

		// A rough outline of Reliquary's approach is as follows:

		// 1) Build initial query elements.
		// Using the filters provided, query parts (where/join/etc.) are built
		// out through the adapter system. At the same time, search strings are
		// gathered based on the filters.

		// 2) Heuristics passes.
		// If there are many filters applied, it may be more beneficial to start
		// with filtered element IDs, similar to how Craft already manages.
		// Alternatively, some statistics about filters being used, such as
		// how many elements have a particular tag, or exist within a particular
		// section may be used.

		// If search strings exist, they are broken down into ther relevant 3
		// character ngrams and used in a query to the aggregate table to find
		// out general usage information. This can be used as a rough indicator
		// of how many entries may match a particular search.

		// This aggregation is a possible future optimization, and not currently
		// implemented or used.

		// 3) Final filtering.
		// Regardless of approach, the final query will narrow the results
		// down to a criteria that can finally be sent to retrieve a set of
		// element IDs and types. If no search query is provided, this will also
		// apply the group's specified sorting condition. If search queries are
		// provided, results are returned by relevancy based on the ngram
		// lookups performed.

		// Create base element filters from the element types being searched for.
		$typeUnionQuery = null;
		foreach ($searchElements as $element) {
			$event = new ReliquaryExtendElementTypeQuery([
				'searchElement' => $element,
			]);
			Event::trigger($element->elementType, Reliquary::EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY, $event);
			$event->query->addSelect(new Expression($element->sortOrder . ' AS `searchElementOrder`'));
			if (!$typeUnionQuery) {
				$typeUnionQuery = $event->query;
			} else {
				$typeUnionQuery->union($event->query);
			}
		}

		// Create the base query for elements by their ID.
		$elementQuery = (new Query())
			->select([
				'e.id',
				'e.type',
			])
			->from('{{%elements_sites}} es')
			->innerJoin('{{%elements}} e', 'es.elementId = e.id')
			->where([
				'es.siteId' => $group->siteId,
				'e.enabled' => 1,
				'e.archived' => 0,
				'e.draftId' => null,
				'e.revisionId' => null,
				'e.dateDeleted' => null,
			]);

		// Get build query parts related to the provided filters.
		$searchStrings = [];
		$filterQuery = (new Query())
			->select(['e.id', 'e.type'])
			->from('{{%elements_sites}} es')
			->innerJoin('{{%elements}} e', 'es.elementId = e.id');
		$checkedFilters = [];
		foreach ($options as $option) {
			if (!isset($option['value']) || empty($option['value'])) { // Ignore any unset/empty filters.
				continue;
			}
			if (!isset($option['filter'])) { // General string search on contents.
				// Ensure a duplicate search filter isn't being used.
				if (isset($checkedFilters[-1])) {
					throw new DuplicateSearchGroupFilterException('Duplicate search filters provided.');
				}
				$checkedFilters[-1] = true;

				$searchStrings[-1] = $option['value'];
			} else { // Custom filter search by value.
				// Ensure a duplicate search filter isn't being used.
				if (isset($checkedFilters[$option['filter']])) {
					throw new DuplicateSearchGroupFilterException('Duplicate search filters provided.');
				}
				$checkedFilters[$option['filter']] = true;

				// Ensure the search filter is valid.
				if (!isset($filters[$option['filter']])) {
					throw new SearchGroupFilterNotFoundException('Invalid search group filter ID: ' . $option['filter']);
				}
				$filter = $filters[$option['filter']];

				// Check what should be done for the given filter.
				$event = new ReliquaryModifyFilterQuery([
					'query' => $filterQuery,
					'filter' => $filter,
					'value' => $option['value'],
				]);
				if ($filter->fieldId) { // Filter is for a field.
					Event::trigger(get_class($filter->getField()), Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY, $event);
					if (!$event->handled) {
						throw new NoModifyFilterQueryHandlerException('No filter handler for ' . get_class($filter->getField()));
					}
				} else { // Filter is for an attribute.
					foreach ($searchElements as $element) {
						Event::trigger($element->elementType, Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY, $event);
					}
					if (!$event->handled) {
						throw new NoModifyFilterQueryHandlerException('No filter handler for ' . get_class($filter->getField()));
					}
				}
				if ($event->textSearch) { // Query removed from event, this indicates the value should be forwarded to the text searching system.
					$searchStrings[$option['filter']] = $option['value'];
				}
			}
		}

		if (!$skipRecording) {
			$searchId = Craft::$app->getSession()->get('reliquary_searchId');
			if (!$searchId) {
				$searchId = md5(Craft::$app->getSession()->getId() . date('U'));
				Craft::$app->getSession()->set('reliquary_searchId', $searchId);
			}
			$filterRecord = [];
			foreach ($options as $option) {
				if (!isset($option['filter']) || !isset($option['value']) || empty($option['value'])) {
					continue;
				}
				$filterRecord[] = [
					'field' => $filters[$option['filter']]->getField()->handle,
					'value' => $option['value'],
				];
			}
			Craft::$app->getDb()->createCommand()
				->insert('{{%reliquary_searchrecord}}', [
					'subjectId' => $searchId,
					'time' => date('Y-m-d H:i:s'),
					'term' => isset($searchStrings[-1]) ? $searchStrings[-1] : null,
					'filters' => json_encode($filterRecord),
				], false)
				->execute();
		}

		// Convert all search strings to ngrams, collapse down to list of
		// non-duplicate grams per filter.
		$searchGrams = [];
		foreach ($searchStrings as $filter => $searchString) {
			$searchGrams[$filter] = [];

			// NOTE: In special cases, where search should occur for fewer characters
			// We may want to take search strings that are short, and find existing
			// ngrams that begin with these characters.

			// Build a 3-gram set out of the provided string.
			$grams = SearchHelper::buildNgram($searchString);

			// Put each gram into groups, store groups in final search array.
			// Make sure each gram added to the final array is weighted by
			// its total usage.
			$gramGroup = [];
			foreach ($grams as $gram) {
				if (preg_match('/ /', $gram)) { // Contains space, clear group.
					// Add each gram to the search, weighted by the total grams in each keyword.
					foreach ($gramGroup as $finalGram) {
						if (!isset($searchGrams[$filter][$finalGram])) {
							$searchGrams[$filter][$finalGram] = 0;
						}

						$searchGrams[$filter][$finalGram] += 1 / count($gramGroup);
					}
					$gramGroup = [];
				} else { // No whitespace, add to group.
					$gramGroup[] = $gram;
				}
			}

			// Dump any remaining grams in the group.
			foreach ($gramGroup as $finalGram) {
				if (!isset($searchGrams[$finalGram])) {
					$searchGrams[$filter][$finalGram] = 0;
				}

				$searchGrams[$filter][$finalGram] += 1 / count($gramGroup);
			}
		}

		// Convert key -> score pairs to an array of data.
		// Determine total number of unique filters that should match
		// in order to be considered a valid result.
		$totalFilters = count($searchStrings);
		$temp = [];
		foreach ($searchGrams as $filterId => $gram) {
			if ($filterId == -1) {
				$filterId = null;
				$totalFilters -= 1; // General text search is not really a filter.
			}
			foreach ($gram as $key => $score) {
				$temp[] = [
					$key,
					$score,
					$filterId,
				];
			}
		}
		$searchGrams = $temp;

		if (!empty($searchGrams)) {
			// Build a list containing all search grams.
			// Quotes and all have already been stripped out.
			$allGrams = [];
			foreach ($searchGrams as $gram) {
				$allGrams[$gram[0]] = true;
			}
			$allGrams = array_keys($allGrams);
			$condition = '\'' . implode('\',\'', $allGrams) . '\'';

			// Start with a subquery that retrieves all relevant ngrams.
			if (Craft::$app->getDb()->getIsMysql()) {
				// Create temporary table to store data being searched for.
				$sql = <<<EOT
DROP TEMPORARY TABLE IF EXISTS reliquary_querygrams;

CREATE TEMPORARY TABLE reliquary_querygrams (
	`key` CHAR(3) NOT NULL
	, score DOUBLE NOT NULL
	, filterId INT NULL
)
ENGINE=MEMORY;
EOT;
				Craft::$app->getDb()->createCommand($sql)
					->execute();

				Craft::$app->getDb()->createCommand()
					->batchInsert(
						'reliquary_querygrams',
						['key', 'score', 'filterId'],
						$searchGrams,
						false
					)
					->execute();

				$sql = <<<EOT
SELECT
	`dat`.`indexId`
	, `dat`.`key`
	, @rid := @rid + ((@prevOffset + 1) != `dat`.`offset`) AS `runId`
	, @prevOffset := `dat`.`offset` as `offset`
FROM (
	SELECT
		@rid := 0
		, @prevOffset := 0
	) `states`
	, {{%reliquary_ngramdata}} `dat`
WHERE
	`dat`.`key` IN ($condition)
ORDER BY
	`dat`.`indexId`
	, `dat`.`offset`
EOT;
			} else {
				throw new \Exception('PostgreSQL 🚮');
			}

			// Calculate the run length (number of adjacent ngrams), combine with filter information to ensure only required filters are pulled.
			$searchQuery = (new Query())
				->select([
					'filteredKeys.indexId',
					'filteredKeys.runId',
					'runScore' => 'POWER(SUM(`qg`.`score`), 10 / COUNT(`qg`.`key`))',
					'filterId' => 'COALESCE(`f`.`id`, NULL)',
				])
				->from(['filteredKeys' => new Expression('(' . $sql . ')')])
				->innerJoin('reliquary_querygrams qg', 'filteredKeys.key = qg.key')
				->innerJoin('{{%reliquary_ngramindex}} idx', 'filteredKeys.indexId = idx.id')
				->leftJoin('{{%reliquary_searchgroupfilters}} f', 'qg.filterId = f.id AND (idx.fieldId = f.fieldId OR idx.attribute = f.attribute)')
				->groupBy([
					'filteredKeys.indexId',
					'filteredKeys.runId',
				]);

			// Calculate field weights by totalling the runs.
			$searchQuery = (new Query())
				->select([
					'runs.indexId',
					'runs.filterId',
					'indexWeight' => 'SUM(`runs`.`runScore`) / LOG10(`idx`.`ngrams`)',
				])
				->from(['runs' => $searchQuery])
				->innerJoin('{{%reliquary_ngramindex}} idx', 'runs.indexId = idx.id')
				->groupBy([
					'runs.indexId',
					'runs.filterId',
				]);

			// Calculate element weights by combining each field.
			$searchQuery = (new Query())
				->select([
					'idx.elementId',
					'elementWeight' => 'SUM(`weights`.`indexWeight` * COALESCE(`cfw`.`multiplier`, 1))',
					'filterCount' => 'COUNT(DISTINCT(`weights`.`filterId`))',
				])
				->from(['weights' => $searchQuery])
				->innerJoin('{{%reliquary_ngramindex}} idx', 'weights.indexId = idx.id')
				->innerJoin(['typeUnion' => $typeUnionQuery], 'idx.elementId = typeUnion.id')
				->innerJoin('{{%elements}} sq_e', 'idx.elementId = sq_e.id')
				->leftJoin('{{%reliquary_customfieldweights}} cfw', '(idx.fieldId = cfw.fieldId OR idx.attribute = cfw.attribute) AND sq_e.type = cfw.elementType AND typeUnion.typeId = cfw.elementTypeId')
				->where([
					'idx.siteId' => $group->siteId,
				])
				->groupBy([
					'idx.elementId',
				])
				->having([
					'filterCount' => $totalFilters,
				])
				->orderBy('elementWeight DESC');

			$elementQuery->innerJoin(['searchWeights' => $searchQuery], 'e.id = searchWeights.elementId');

			$elementQuery->orderBy('searchWeights.elementWeight DESC');
			$elementQuery->addSelect(['searchScore' => 'searchWeights.elementWeight']);

			// Ensure minimum score based on settings.
			$elementQuery->andWhere(['>', 'searchWeights.elementWeight', Craft::$app->getPlugins()->getPlugin('reliquary')->getSettings()->minimumScore]);
		} else {
			$elementQuery->innerJoin(['typeUnion' => $typeUnionQuery], 'es.elementId = typeUnion.id');

			// Sort by group settings.
			switch ($group->searchOrder) {
				case 'default':
					// Default sorts each element type separately, in the order configured for the group.
					// Each type tries to be sorted by what Craft would have as default.
					$elementQuery->leftJoin('{{%structureelements}} structureForSort', 'es.elementId = structureForSort.elementId');
					$elementQuery->leftJoin('{{%entries}} entriesForSort', 'es.elementId = entriesForSort.id');
					$elementQuery->orderBy('`typeUnion`.`searchElementOrder`, `structureForSort`.`lft`, `entriesForSort`.`postDate`');
					break;
				case 'default nogroup':
					// Default nogroup attempts to sort all elements together by craft's default.
					// This will only function properly if every element is from the same kind of source (all from the same structure, etc.).
					$elementQuery->leftJoin('{{%structureelements}} structureForSort', 'es.elementId = structureForSort.elementId');
					$elementQuery->leftJoin('{{%entries}} entriesForSort', 'es.elementId = entriesForSort.id');
					$elementQuery->orderBy('`structureForSort`.`lft`, `entriesForSort`.`postDate`');
					break;
				case 'id asc':
					$elementQuery->orderBy('`es`.`elementId`');
					break;
				case 'id desc':
					$elementQuery->orderBy('`es`.`elementId` DESC');
					break;
				case 'title asc':
					$elementQuery->leftJoin('{{%content}} contentForSort', 'es.elementId = contentForSort.elementId AND es.siteId = contentForSort.siteId');
					$elementQuery->orderBy('`contentForSort`.`title`');
					break;
				case 'title desc':
					$elementQuery->leftJoin('{{%content}} contentForSort', 'es.elementId = contentForSort.elementId AND es.siteId = contentForSort.siteId');
					$elementQuery->orderBy('`contentForSort`.`title` DESC');
					break;
				case 'date asc':
					$elementQuery->leftJoin('{{%entries}} entriesForSort', 'es.elementId = entriesForSort.id');
					$elementQuery->orderBy('COALESCE(`entriesForSort`.`postDate`, `e`.`dateUpdated`)');
					break;
				case 'date desc':
					$elementQuery->leftJoin('{{%entries}} entriesForSort', 'es.elementId = entriesForSort.id');
					$elementQuery->orderBy('COALESCE(`entriesForSort`.`postDate`, `e`.`dateUpdated`) DESC');
					break;
				default:
					break;
			}
		}

		// Add joins specified in filter query to the element query.
		foreach ($filterQuery->join as $join) {
			// Element already joined, skip that.
			if ($join[1] == '{{%elements}} e') {
				continue;
			}
			$elementQuery->join[] = $join;
		}

		// Add where clauses from filter query into the element query.
		$elementQuery->andWhere($filterQuery->where);

		// Count search results.
		$totalResults = $elementQuery->count();

		// Add limit/offsets.
		$elementQuery->limit($group->pageSize);
		$elementQuery->offset($group->pageSize * ($page - 1));

		// Retrieve element meta information for the search.
		$results = $elementQuery->all();

		// Index meta information by ID and by element type.
		$elementIndex = [];
		$elementTypeIndex = [];
		foreach ($results as $result) {
			$elementIndex[$result['id']] = $result;
			if (!isset($elementTypeIndex[$result['type']])) {
				$elementTypeIndex[$result['type']] = [];
			}
			$elementTypeIndex[$result['type']][] = $result['id'];
		}

		// Retrieve underlying elements by each type.
		foreach ($elementTypeIndex as $elementClass => $ids) {
			$event = new ReliquaryGetElements();
			$event->ids = $ids;
			$event->siteId = $group->siteId;
			Event::trigger($elementClass, Reliquary::EVENT_RELIQUARY_GET_ELEMENTS, $event);

			if (!$event->handled) {
				throw new NoGetElementsHandlerException('No elements handler for ' . $elementClass);
			}

			foreach ($event->elements as $id => $element) {
				$result = $elementIndex[$id];
				if (isset($result['searchScore'])) {
					$element->searchScore = $result['searchScore'];
				}
				$elementIndex[$id] = $element;
			}
		}

		// Swap out results in place with the now retrieved/keyed elements.
		foreach ($results as $key => $result) {
			$results[$key] = $elementIndex[$result['id']];
		}

		$totalPages = ceil($totalResults / $group->pageSize);

		return [
			'totalElements' => $totalResults,
			'totalPages' => $totalPages,
			'firstElement' => ($group->pageSize * ($page - 1)) + 1,
			'lastElement' => ($group->pageSize * ($page - 1)) + count($results),
			'elements' => $results,
			'currentPage' => $page,
			'previousPage' => ($page - 1 > 0) ? $page - 1 : null,
			'nextPage' => ($page + 1 <= $totalPages) ? $page + 1 : null,
			'pageSize' => $group->pageSize,
			'queryTime' => (microtime(true) - $queryTime) * 1000,
		];
	}

	/**
	 * Clears out all reliquary index data.
	 */
	public function clearIndexTables()
	{
		Craft::$app->getDb()->createCommand()
			->truncateTable('{{%reliquary_ngramdata}}')
			->execute();

		Craft::$app->getDb()->createCommand()
			->truncateTable('{{%reliquary_ngramindex}}')
			->execute();

		Craft::$app->getDb()->createCommand()
			->truncateTable('{{%reliquary_indexqueue}}')
			->execute();
	}

	/**
	 * Deletes stored Reliquary index data for a given element.
	 * @param Element $element The element to delete the index data of.
	 */
	public function deleteIndexDataForElement($element)
	{
		if ($element) {
			$this->deleteIndexDataForElementById($element->id);
		}
	}

	/**
	 * Deletes stored Reliquary index data for a given element by its ID.
	 *
	 * Keep in mind if something within this method fails or PHP happens to
	 * crash partway through, the index will be out of sync with the actual data
	 * and would need to be rebuilt for the most accurate results.
	 * @param int $elementId The ID of the element to delete the index data of.
	 */
	public function deleteIndexDataForElementById(int $elementId)
	{
		$indexes = (new Query())
			->select(['id'])
			->from('{{%reliquary_ngramindex}}')
			->where(['elementId' => $elementId])
			->column();

		if (count($indexes)) { // Remove data related to existing indexes.
			// Remove raw data.
			Craft::$app->getDb()->createCommand()
				->delete('{{%reliquary_ngramdata}}', [
					'indexId' => $indexes
				])
				->execute();
		}

		// Remove index pointing to data.
		Craft::$app->getDb()->createCommand()
			->delete('{{%reliquary_ngramindex}}', [
				'elementId' => $elementId
			])
			->execute();
	}

	/**
	 * Clears out any pending index updates for an element.
	 * @param int The element to clear the pending indexes for.
	 * @param int The site to clear the pending indexes for.
	 */
	public function clearPendingIndexQueue(int $elementId, int $siteId)
	{
		Craft::$app->getDb()->createCommand()
			->delete('{{%reliquary_indexqueue}}', [
				'elementId' => $elementId,
				'siteId' => $siteId,
			])
			->execute();
	}

	/**
	 * Updates or creates a field weight record based on the provided properties.
	 * @param $weight The model representing the custom field weight change.
	 * @return bool True on successful delete, false if no records were deleted.
	 */
	public function saveFieldWeight(CustomFieldWeight $weight)
	{
		$record = CustomFieldWeightRecord::find()
			->where([
				'and',
				['attribute' => $weight->attribute],
				['fieldId' => $weight->fieldId],
				['elementType' => $weight->elementType],
				['elementTypeId' => $weight->elementTypeId],
			])
			->one();

		if (!$record) {
			$record = new CustomFieldWeightRecord([
				'attribute' => $weight->attribute,
				'fieldId' => $weight->fieldId,
				'elementType' => $weight->elementType,
				'elementTypeId' => $weight->elementTypeId,
				'multiplier' => $weight->multiplier,
			]);
		} else {
			$record->multiplier = $weight->multiplier;
		}

		$record->save();

		return true;
	}
}
