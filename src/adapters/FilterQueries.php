<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\adapters;

use jaredlindo\reliquary\events\ReliquaryModifyFilterQuery;
use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Checkboxes;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Entries;
use craft\fields\Lightswitch;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Tags;
use craft\fields\Users;

use yii\base\Event;
use yii\db\Expression;

/**
 * Adapter that attaches event handlers to field classes in order to allow
 * retrieval of available field options.
 */
class FilterQueries
{
	public static function setup()
	{
		// -----
		// First-party fields.
		// -----

		Event::on(
			Assets::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterAssetsQuery']
		);

		Event::on(
			Categories::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterCategoriesQuery']
		);

		Event::on(
			Checkboxes::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterCheckboxesQuery']
		);

		Event::on(
			Dropdown::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterDropdownQuery']
		);

		Event::on(
			Entries::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterEntriesQuery']
		);

		Event::on(
			Lightswitch::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterLigthswitchQuery']
		);

		Event::on(
			MultiSelect::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterMultiSelectQuery']
		);

		Event::on(
			PlainText::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterPlainTextQuery']
		);

		Event::on(
			RadioButtons::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterRadioButtonsQuery']
		);

		Event::on(
			Tags::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterTagsQuery']
		);

		Event::on(
			Users::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterUsersQuery']
		);

		// -----
		// Third-party fields (temporary).
		// -----

		Event::on(
			\ether\simplemap\fields\MapField::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterMapFieldQuery']
		);

		// -----
		// First-party elements.
		// -----

		Event::on(
			Asset::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterAssetAttributeQuery']
		);

		Event::on(
			Category::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterCategoryAttributeQuery']
		);

		Event::on(
			Entry::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterEntryAttributeQuery']
		);

		Event::on(
			Tag::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterTagAttributeQuery']
		);

		Event::on(
			User::class,
			Reliquary::EVENT_RELIQUARY_MODIFY_FILTER_QUERY,
			[self::class, 'filterUserAttributeQuery']
		);
	}

	public static function filterAssetsQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to relations and assets for this query.
		$relationsAlias = 'r_mfq_relations_' . $event->filter->id;
		$assetsAlias = 'r_mfq_assets_' . $event->filter->id;
		$event->query->innerJoin('{{%relations}} ' . $relationsAlias, 'e.id = ' . $relationsAlias . '.sourceId');
		$event->query->innerJoin('{{%assets}} ' . $assetsAlias, $relationsAlias . '.targetId = ' . $assetsAlias . '.id');

		// Where assets are from the user-provided set of IDs.
		$event->query->andWhere([$assetsAlias . '.id' => $event->value]);

		// Where relationships are for the provided field and site.
		$event->query->andWhere([
			'and',
			[
				'or',
				[$relationsAlias . '.sourceSiteId' => $event->filter->getGroup()->siteId],
				[$relationsAlias . '.sourceSiteId' => null],
			],
			[$relationsAlias . '.fieldId' => $event->filter->getField()->id]
		]);
	}

	public static function filterCategoriesQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to relations and categories for this query.
		$relationsAlias = 'r_mfq_relations_' . $event->filter->id;
		$categoriesAlias = 'r_mfq_categories_' . $event->filter->id;
		$event->query->innerJoin('{{%relations}} ' . $relationsAlias, 'e.id = ' . $relationsAlias . '.sourceId');
		$event->query->innerJoin('{{%categories}} ' . $categoriesAlias, $relationsAlias . '.targetId = ' . $categoriesAlias . '.id');

		// Where categories are from the user-provided set of IDs.
		$event->query->andWhere([$categoriesAlias . '.id' => $event->value]);

		// Where relationships are for the provided field and site.
		$event->query->andWhere([
			'and',
			[
				'or',
				[$relationsAlias . '.sourceSiteId' => $event->filter->getGroup()->siteId],
				[$relationsAlias . '.sourceSiteId' => null],
			],
			[$relationsAlias . '.fieldId' => $event->filter->getField()->id]
		]);
	}

	public static function filterCheckboxesQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to content for this query.
		$contentAlias = 'r_mfq_content_' . $event->filter->id;
		$event->query->innerJoin('{{%content}} ' . $contentAlias, 'es.elementId = ' . $contentAlias . '.elementId AND es.siteId = ' . $contentAlias . '.siteId');

		// Where content contains the user-provided options.
		foreach ($event->value as $item) {
			if (!is_string($item)) {
				throw new \Exception('Bad filter data');
			}
			$event->query->andWhere(['like', $contentAlias . '.field_' . $event->filter->getField()->handle, addcslashes(json_encode($item), '%_')]);
		}
	}

	public static function filterDateQuery(ReliquaryModifyFilterQuery $event)
	{
		// TODO
	}

	public static function filterDropdownQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_string($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to content for this query.
		$contentAlias = 'r_mfq_content_' . $event->filter->id;
		$event->query->innerJoin('{{%content}} ' . $contentAlias, 'es.elementId = ' . $contentAlias . '.elementId AND es.siteId = ' . $contentAlias . '.siteId');

		// Where content contains the user-provided option.
		$event->query->andWhere(['like', $contentAlias . '.field_' . $event->filter->getField()->handle, addcslashes(json_encode($event->value), '%_')]);
	}

	public static function filterLightswitchQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_string($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to content for this query.
		$contentAlias = 'r_mfq_content_' . $event->filter->id;
		$event->query->innerJoin('{{%content}} ' . $contentAlias, 'es.elementId = ' . $contentAlias . '.elementId AND es.siteId = ' . $contentAlias . '.siteId');

		// Where content is the user-provided value.
		$event->query->andWhere([$contentAlias . '.field_' . $event->filter->getField()->handle => $event->value]);
	}

	public static function filterMultiSelectQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to content for this query.
		$contentAlias = 'r_mfq_content_' . $event->filter->id;
		$event->query->innerJoin('{{%content}} ' . $contentAlias, 'es.elementId = ' . $contentAlias . '.elementId AND es.siteId = ' . $contentAlias . '.siteId');

		// Where content contains the user-provided options.
		foreach ($event->value as $item) {
			if (!is_string($item)) {
				throw new \Exception('Bad filter data');
			}
			$event->query->andWhere(['like', $contentAlias . '.field_' . $event->filter->getField()->handle, addcslashes(json_encode($item), '%_')]);
		}
	}

	public static function filterNumberQuery(ReliquaryModifyFilterQuery $event)
	{
		// TODO
	}

	public static function filterPlainTextQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_string($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Add the value to the text search set.
		$event->textSearch = true;
	}

	public static function filterEntriesQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to relations and entries for this query.
		$relationsAlias = 'r_mfq_relations_' . $event->filter->id;
		$elementsAlias = 'r_mfq_elements_' . $event->filter->id;
		$event->query->innerJoin('{{%relations}} ' . $relationsAlias, 'e.id = ' . $relationsAlias . '.sourceId');
		$event->query->innerJoin('{{%elements}} ' . $elementsAlias, $relationsAlias . '.targetId = ' . $elementsAlias . '.id');

		// Where elements are from the user-provided set of IDs.
		$event->query->andWhere([$elementsAlias . '.id' => $event->value]);

		// Where relationships are for the provided field and site.
		$event->query->andWhere([
			'and',
			[
				'or',
				[$relationsAlias . '.sourceSiteId' => $event->filter->getGroup()->siteId],
				[$relationsAlias . '.sourceSiteId' => null],
			],
			[$relationsAlias . '.fieldId' => $event->filter->getField()->id]
		]);
	}

	public static function filterRadioButtonsQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_string($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to content for this query.
		$contentAlias = 'r_mfq_content_' . $event->filter->id;
		$event->query->innerJoin('{{%content}} ' . $contentAlias, 'es.elementId = ' . $contentAlias . '.elementId AND es.siteId = ' . $contentAlias . '.siteId');

		// Where content contains the user-provided option.
		$event->query->andWhere(['like', $contentAlias . '.field_' . $event->filter->getField()->handle, addcslashes(json_encode($event->value), '%_')]);
	}

	public static function filterTagsQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to relations and tags for this query.
		$relationsAlias = 'r_mfq_relations_' . $event->filter->id;
		$tagsAlias = 'r_mfq_tags_' . $event->filter->id;
		$event->query->innerJoin('{{%relations}} ' . $relationsAlias, 'e.id = ' . $relationsAlias . '.sourceId');
		$event->query->innerJoin('{{%tags}} ' . $tagsAlias, $relationsAlias . '.targetId = ' . $tagsAlias . '.id');

		// Where tags are from the user-provided set of IDs.
		$event->query->andWhere([$tagsAlias . '.id' => $event->value]);

		// Where relationships are for the provided field and site.
		$event->query->andWhere([
			'and',
			[
				'or',
				[$relationsAlias . '.sourceSiteId' => $event->filter->getGroup()->siteId],
				[$relationsAlias . '.sourceSiteId' => null],
			],
			[$relationsAlias . '.fieldId' => $event->filter->getField()->id]
		]);
	}

	public static function filterUsersQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// Join to relations and users for this query.
		$relationsAlias = 'r_mfq_relations_' . $event->filter->id;
		$usersAlias = 'r_mfq_users_' . $event->filter->id;
		$event->query->innerJoin('{{%relations}} ' . $relationsAlias, 'e.id = ' . $relationsAlias . '.sourceId');
		$event->query->innerJoin('{{%users}} ' . $usersAlias, $relationsAlias . '.targetId = ' . $usersAlias . '.id');

		// Where users are from the user-provided set of IDs.
		$event->query->andWhere([$usersAlias . '.id' => $event->value]);

		// Where relationships are for the provided field and site.
		$event->query->andWhere([
			'and',
			[
				'or',
				[$relationsAlias . '.sourceSiteId' => $event->filter->getGroup()->siteId],
				[$relationsAlias . '.sourceSiteId' => null],
			],
			[$relationsAlias . '.fieldId' => $event->filter->getField()->id]
		]);
	}

	public static function filterMapFieldQuery(ReliquaryModifyFilterQuery $event)
	{
		$event->handled = true;

		// Make sure data exists in a proper format.
		if (!is_array($event->value)) {
			throw new \Exception('Bad filter data');
		}

		// If all values are not provided, ignore filtering.
		if (
			empty($event->value['lat'])
			&& empty($event->value['lon'])
			&& empty($event->value['rad'])
		) {
			return;
		}

		// If some values are missing, or are not numeric, raise an error.
		if (
			empty($event->value['lat'])
			|| !is_numeric($event->value['lat'])
			|| empty($event->value['lon'])
			|| !is_numeric($event->value['lon'])
			|| empty($event->value['rad'])
			|| !is_numeric($event->value['rad'])
		) {
			throw new \Exception('Bad filter data');
		}

		// Force all data to numeric types, to ensure no extraneous string data remains.
		$lat = floatval($event->value['lat']);
		$lon = floatval($event->value['lon']);
		$rad = floatval($event->value['rad']);

		// Join to map table for this query.
		$mapAlias = 'r_mfq_map_' . $event->filter->id;
		$event->query->innerJoin('{{%maps}} ' . $mapAlias, 'e.id = ' . $mapAlias . '.ownerId');

		// Where tags are from the user-provided set of IDs.
		$sql = 'ST_Distance_Sphere(point(' . $lon . ', ' . $lat . '), point(' . $mapAlias . '.lng, ' . $mapAlias . '.lat)) < ' . $rad;
		$event->query->andWhere(new Expression('(' . $sql . ')'));

		// Where map data is for the provided field and site.
		$event->query->andWhere([
			'and',
			[$mapAlias . '.ownerSiteId' => $event->filter->getGroup()->siteId],
			[$mapAlias . '.fieldId' => $event->filter->getField()->id]
		]);
	}

	public static function filterAssetAttributeQuery(ReliquaryModifyFilterQuery $event)
	{
		// TODO
	}

	public static function filterCategoryAttributeQuery(ReliquaryModifyFilterQuery $event)
	{
		// TODO
	}

	public static function filterEntryAttributeQuery(ReliquaryModifyFilterQuery $event)
	{
		// TODO
	}

	public static function filterTagAttributeQuery(ReliquaryModifyFilterQuery $event)
	{
		// TODO
	}

	public static function filterUserAttributeQuery(ReliquaryModifyFilterQuery $event)
	{
		// TODO
	}
}
