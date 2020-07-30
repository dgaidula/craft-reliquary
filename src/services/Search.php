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
use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Search as SearchHelper;

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
	public function doSearch(int $groupId, $options, $page = 1)
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

		// In Craft's own searching mechanism, the query is built from the base
		// set of criteria, such as status, creation date, titles, fields,
		// and so on, and then used to retrieve a set of potential element IDs.

		// These IDs are then sent to the search service and the provided search
		// text to compare against MySQL's full text search to further filter
		// elements of that set that match the given text.

		// Reliquary's foregoes the two-pass approach and builds the entire
		// process as a single query, to help facilitate speed, especially when
		// the initial pass at filters may return a very large pool of candidate
		// elements.

		// This mechanism builds a complex query with Yii's query builder, but
		// also has touse a little bit of manual SQL because Yii doesn't have
		// a mechanism for things like plain text search or some finer-grained
		// mechanisms that are made generic for the querying system as a whole.

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

		// Build query parts related to the provided filters.
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

		// Build search filtering for text search, if any text is provided.
		if (!empty($searchStrings)) {

			// Index every search string as a subquery, starting with the type union, allowing only elements of the search group.
			$keyQueryAliases = [];
			$scoreQuery = (new Query())
				->select([
					'e.id',
				])
				->from(['e' => $typeUnionQuery]);
			foreach ($searchStrings as $filterId => $rawKeywords) {
				$keyQueryAliases[] = 'kq' . (count($keyQueryAliases) + 1);

				// Normalize the text being searched.
				$keywords = SearchHelper::normalizeKeywords($rawKeywords, [], true, Craft::$app->getSites()->getSiteById($group->siteId)->language);

				// Perform the full-text search, totaling all of the scores found for a given element (sum really only necessary for general search).
				$matchString = 'MATCH(`keywords`) AGAINST(' . Craft::$app->getDb()->quoteValue($keywords) . ')';
				$searchQuery = (new Query())
					->select([
						'elementId',
						'SUM(' . $matchString . ') AS score',
					])
					->from(Table::SEARCHINDEX)
					->where($matchString)
					->andWhere(['siteId' => $group->siteId])
					->groupBy('elementId');

				// If this search is for a specific filter (not generic) then add a clause for the filter's attribute/fieldId.
				if ($filterId !== -1) {
					if ($filters[$filterId]->fieldId) {
						$searchQuery->andWhere(['fieldId' => $filters[$filterId]->fieldId]);
					} else {
						$searchQuery->andWhere(['attribute' => $filters[$filterId]->attribute]);
					}
				}

				$scoreQuery->innerJoin([end($keyQueryAliases) => $searchQuery], 'e.id = ' . end($keyQueryAliases) . '.elementId');
			}

			// Create a wrapping query to total up the score.
			$scoreQuery->addSelect([
				'totalScore' => implode('.score +', $keyQueryAliases) . '.score',
			]);

			// Add the .
			$elementQuery->orderBy('totalScore DESC');

			// Add the score to the final query and ensure minimum score based on settings.
			$elementQuery->innerJoin(['sq' => $scoreQuery], 'e.id = sq.id');
			$elementQuery->andWhere(['>', 'sq.totalScore', Craft::$app->getPlugins()->getPlugin('reliquary')->getSettings()->minimumScore]);
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
}
