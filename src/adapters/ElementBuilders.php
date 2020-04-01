<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\adapters;

use jaredlindo\reliquary\events\ReliquaryGetElements;
use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;

use yii\base\Event;

/**
 * Adapter that allows
 */
class ElementBuilders
{
	public static function setup()
	{
		Event::on(
			Asset::class,
			Reliquary::EVENT_RELIQUARY_GET_ELEMENTS,
			[self::class, 'buildAssets']
		);

		Event::on(
			Category::class,
			Reliquary::EVENT_RELIQUARY_GET_ELEMENTS,
			[self::class, 'buildCategories']
		);

		Event::on(
			Entry::class,
			Reliquary::EVENT_RELIQUARY_GET_ELEMENTS,
			[self::class, 'buildEntries']
		);

		Event::on(
			Tag::class,
			Reliquary::EVENT_RELIQUARY_GET_ELEMENTS,
			[self::class, 'buildTags']
		);

		Event::on(
			User::class,
			Reliquary::EVENT_RELIQUARY_GET_ELEMENTS,
			[self::class, 'buildUsers']
		);
	}

	public static function buildAssets(ReliquaryGetElements $event)
	{
		$event->handled = true;

		$elements = \craft\elements\Asset::find()
			->id($event->ids)
			->siteId($event->siteId)
			->all();

		foreach ($elements as $element) {
			$event->elements[$element->id] = $element;
		}
	}

	public static function buildCategories(ReliquaryGetElements $event)
	{
		$event->handled = true;

		$elements = \craft\elements\Category::find()
			->id($event->ids)
			->siteId($event->siteId)
			->all();

		foreach ($elements as $element) {
			$event->elements[$element->id] = $element;
		}
	}

	public static function buildEntries(ReliquaryGetElements $event)
	{
		$event->handled = true;

		$elements = \craft\elements\Entry::find()
			->id($event->ids)
			->siteId($event->siteId)
			->all();

		foreach ($elements as $element) {
			$event->elements[$element->id] = $element;
		}
	}

	public static function buildTags(ReliquaryGetElements $event)
	{
		$event->handled = true;

		$elements = \craft\elements\Tag::find()
			->id($event->ids)
			->siteId($event->siteId)
			->all();

		foreach ($elements as $element) {
			$event->elements[$element->id] = $element;
		}
	}

	public static function buildUsers(ReliquaryGetElements $event)
	{
		$event->handled = true;

		$elements = \craft\elements\User::find()
			->id($event->ids)
			->siteId($event->siteId)
			->all();

		foreach ($elements as $element) {
			$event->elements[$element->id] = $element;
		}
	}
}
