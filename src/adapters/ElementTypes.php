<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\adapters;

use jaredlindo\reliquary\events\ReliquaryGetElementTypes;
use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;

use yii\base\Event;

/**
 * Adapter that attaches an event handler to the Reliquary plugin to allow
 * searching base Craft element types.
 */
class ElementTypes
{
	public static function setup()
	{
		Event::on(
			Reliquary::class,
			Reliquary::EVENT_RELIQUARY_GET_ELEMENT_TYPES,
			[self::class, 'getElementTypes']
		);
	}

	public static function getElementTypes(ReliquaryGetElementTypes $event)
	{
		$event->handled = true;

		// Add assets and all the volumes.
		$elementType = [
			'name' => Craft::t('app', 'Assets'),
			'attributes' => [[
				'name' => Craft::t('app', 'Title'),
				'handle' => 'title',
			], [
				'name' => Craft::t('app', 'Filename'),
				'handle' => 'filename',
			], [
				'name' => Craft::t('app', 'Kind'),
				'handle' => 'kind',
			]],
			'subtypes' => [],
		];
		foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
			$elementType['subtypes'][$volume->id] = [
				'name' => Craft::t('site', $volume->name),
				'layoutId' => $volume->fieldLayoutId,
			];
		}
		$event->elementTypes[Asset::class] = $elementType;

		// Add categories and all the category groups.
		$elementType = [
			'name' => Craft::t('app', 'Categories'),
			'attributes' => [[
				'name' => Craft::t('app', 'Title'),
				'handle' => 'title',
			]],
			'subtypes' => [],
		];
		foreach (Craft::$app->getCategories()->getAllGroups() as $group) {
			$elementType['subtypes'][$group->id] = [
				'name' => Craft::t('site', $group->name),
				'layoutId' => $group->fieldLayoutId,
			];
		}
		$event->elementTypes[Category::class] = $elementType;

		// Add entries and all the entry types.
		$elementType = [
			'name' => Craft::t('app', 'Entries'),
			'attributes' => [[
				'name' => Craft::t('app', 'Title'),
				'handle' => 'title',
			], [
				'name' => Craft::t('app', 'Author'),
				'handle' => 'author',
			], [
				'name' => Craft::t('app', 'Slug'),
				'handle' => 'slug',
			], [
				'name' => Craft::t('app', 'Post Date'),
				'handle' => 'postDate',
			], [
				'name' => Craft::t('app', 'Expiry Date'),
				'handle' => 'expiryDate',
			]],
			'subtypes' => [],
		];
		foreach (Craft::$app->getSections()->getAllSections() as $section) {
			foreach (Craft::$app->getSections()->getEntryTypesBySectionId($section->id) as $entryType) {
				$elementType['subtypes'][$entryType->id] = [
					'name' => Craft::t('site', $section->name) . ' - ' . Craft::t('site', $entryType->name),
					'layoutId' => $entryType->fieldLayoutId,
				];
			}
		}
		$event->elementTypes[Entry::class] = $elementType;

		// Add tags and all the tag groups.
		$elementType = [
			'name' => Craft::t('app', 'Tags'),
			'attributes' => [[
				'name' => Craft::t('app', 'Title'),
				'handle' => 'title',
			]],
			'subtypes' => [],
		];
		foreach (Craft::$app->getTags()->getAllTagGroups() as $group) {
			$elementType['subtypes'][$group->id] = [
				'name' => Craft::t('site', $group->name),
				'layoutId' => $group->fieldLayoutId,
			];
		}
		$event->elementTypes[Tag::class] = $elementType;

		// Add users, which have no individual subtypes.
		$elementType = [
			'name' => Craft::t('app', 'Users'),
			'attributes' => [[
				'name' => Craft::t('app', 'Username'),
				'handle' => 'username',
			], [
				'name' => Craft::t('app', 'First Name'),
				'handle' => 'firstName',
			], [
				'name' => Craft::t('app', 'Last Name'),
				'handle' => 'lastName',
			], [
				'name' => Craft::t('app', 'Email'),
				'handle' => 'email',
			]],
			'subtypes' => [[
				'name' => Craft::t('app', 'All Users'),
				'layoutId' => Craft::$app->getFields()->getLayoutByType(User::class)->id,
			]],
		];
		$event->elementTypes[User::class] = $elementType;
	}
}
