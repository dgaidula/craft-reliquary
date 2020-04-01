<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\adapters;

use jaredlindo\reliquary\events\ReliquaryGetFieldOptions;
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
use craft\fields\Matrix;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Tags;
use craft\fields\Users;
use craft\helpers\Db;
use craft\db\Table;

use yii\base\Event;

/**
 * Adapter that attaches event handlers to field classes in order to allow
 * retrieval of available field options.
 */
class FieldOptions
{
	public static function setup()
	{
		// -----
		// First-party fields.
		// -----

		Event::on(
			Assets::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getAssetsOptions']
		);

		Event::on(
			Categories::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getCategoriesOptions']
		);

		Event::on(
			Checkboxes::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getCheckboxesOptions']
		);

		Event::on(
			Date::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getDateOptions']
		);

		Event::on(
			Dropdown::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getDropdownOptions']
		);

		Event::on(
			Entries::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getEntriesOptions']
		);

		Event::on(
			Lightswitch::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getLightswitchOptions']
		);

		Event::on(
			MultiSelect::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getMultiSelectOptions']
		);

		Event::on(
			Number::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getNumberOptions']
		);

		Event::on(
			PlainText::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getPlainTextOptions']
		);

		Event::on(
			RadioButtons::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getRadioButtonsOptions']
		);

		Event::on(
			Tags::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getTagsOptions']
		);

		Event::on(
			Users::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getUsersOptions']
		);

		// -----
		// Third-party fields (temporary).
		// -----

		Event::on(
			\ether\simplemap\fields\MapField::class,
			Reliquary::EVENT_RELIQUARY_GET_FIELD_OPTIONS,
			[self::class, 'getMapFieldOptions']
		);
	}

	public static function getAssetsOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		$folderUids = [];

		if ($event->field->useSingleFolder) {
			$folderUids[] = substr($event->field->singleUploadLocationSource, 7); // Remove 'volume:' prefix.
		} else {
			if (is_array($event->field->sources)) {
				foreach ($event->field->sources as $folder) {
					$folderUids[] = substr($folder, 7); // Remove 'volume:' prefix.
				}
			} else { // Must be *, meaning all sources.
				foreach (Asset::sources('settings') as $folder) {
					// Skip the 'any' source, as well as any headers.
					// Not quite applicable to Assets, but just in case...
					if (!array_key_exists('key', $folder) || $folder['key'] == '*') {
						continue;
					}
					$folderUids[] = $folder['criteria']['folderId'];
				}
			}
		}

		$folderIds = Db::idsByUids(Table::VOLUMES, $folderUids);

		$query = Asset::find()
			->folderId($folderIds);

		$event->total = $query->count();

		if (is_numeric($event->hint)) {
			$query->offset((intval($event->hint) - 1) * 50);
		}

		$query->limit(50);

		foreach ($query->all() as $item) {
			$event->options[] = [
				'value' => $item->id,
				'label' => $item->title,
			];
		}

		if ($event->total > 50) {
			$event->partial = true;
		}
	}

	public static function getCategoriesOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		$groupUid = substr($event->field->source, 6); // Remove 'group:' prefix.
		$groupIds = Db::idsByUids(Table::CATEGORYGROUPS, [$groupUid]);

		$query = Category::find()
			->groupId($groupIds);

		$event->total = $query->count();

		if (is_numeric($event->hint)) {
			$query->offset((intval($event->hint) - 1) * 50);
		}

		$query->limit(50);

		foreach ($query->all() as $item) {
			$event->options[] = [
				'value' => $item->id,
				'label' => $item->title,
			];
		}

		if ($event->total > 50) {
			$event->partial = true;
		}
	}

	public static function getCheckboxesOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		foreach ($event->field->options as $option) {
			$event->options[] = [
				'value' => $option['value'],
				'label' => Craft::t('site', $option['label']),
			];
		}
		$event->total = count($event->options);
	}

	public static function getDateOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'date';
	}

	public static function getDropdownOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'single';

		foreach ($event->field->options as $option) {
			$event->options[] = [
				'value' => $option['value'],
				'label' => Craft::t('site', $option['label']),
			];
		}
		$event->total = count($event->options);
	}

	public static function getEntriesOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		$sectionUids = [];

		if (is_array($event->field->sources)) {
			foreach ($event->field->sources as $section) {
				$sectionUids[] = substr($section, 8); // Remove 'section:' prefix.
			}
		} else { // Must be *, meaning all sources.
			foreach (Entry::sources('settings') as $section) {
				// Skip the 'any' source, as well as any headers.
				if (!array_key_exists('key', $section) || $section['key'] == '*') {
					continue;
				}
				$sectionUids[] = $section['criteria']['sectionId'];
			}
		}

		$sectionIds = Db::idsByUids(Table::SECTIONS, $sectionUids);

		$query = Entry::find()
			->sectionId($sectionIds);

		$event->total = $query->count();

		if (is_numeric($event->hint)) {
			$query->offset((intval($event->hint) - 1) * 50);
		}

		$query->limit(50);

		foreach ($query->all() as $item) {
			$event->options[] = [
				'value' => $item->id,
				'label' => $item->title,
			];
		}

		if ($event->total > 50) {
			$event->partial = true;
		}
	}

	public static function getLightswitchOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'single';

		$event->options = [
			[
				'value' => true,
				'label' => 'True',
			], [
				'value' => false,
				'label' => 'False',
			]
		];
		$event->total = count($event->options);
	}

	public static function getMultiSelectOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		foreach ($event->field->options as $option) {
			$event->options[] = [
				'value' => $option['value'],
				'label' => Craft::t('site', $option['label']),
			];
		}
		$event->total = count($event->options);
	}

	public static function getNumberOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'number';

		$event->options = [
			'min' => $event->field->max,
			'max' => $event->field->min,
			'decimalPoints' => $event->field->decimals,
		];
	}

	public static function getPlainTextOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'string';
	}

	public static function getRadioButtonsOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'single';

		foreach ($event->field->options as $option) {
			$event->options[] = [
				'value' => $option['value'],
				'label' => Craft::t('site', $option['label']),
			];
		}
		$event->total = count($event->options);
	}

	public static function getTagsOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		$groupUid = substr($event->field->source, 9); // Remove 'taggroup:' prefix.
		$groupIds = Db::idsByUids(Table::TAGGROUPS, [$groupUid]);

		$query = Tag::find()
			->groupId($groupIds);

		$event->total = $query->count();

		if (is_numeric($event->hint)) {
			$query->offset((intval($event->hint) - 1) * 50);
		}

		$query->limit(50);

		foreach ($query->all() as $item) {
			$event->options[] = [
				'value' => $item->id,
				'label' => $item->title,
			];
		}

		if ($event->total > 50) {
			$event->partial = true;
		}
	}

	public static function getUsersOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		$query = User::find();

		$event->total = $query->count();

		if (is_numeric($event->hint)) {
			$query->offset((intval($event->hint) - 1) * 50);
		}

		$query->limit(50);

		foreach ($query->all() as $item) {
			$event->options[] = [
				'value' => $item->id,
				'label' => $item->name,
			];
		}

		if ($event->total > 50) {
			$event->partial = true;
		}
	}

	public static function getMapFieldOptions(ReliquaryGetFieldOptions $event)
	{
		$event->handled = true;
		$event->type = 'map';
	}
}
