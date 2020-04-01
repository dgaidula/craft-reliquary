<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\adapters;

use jaredlindo\reliquary\events\ReliquaryGetAttributeOptions;
use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\helpers\Assets as AssetsHelper;

use yii\base\Event;

/**
 * Adapter that attaches event handlers to element classes in order to allow
 * retrieval of available attribute options.
 */
class AttributeOptions
{
	public static function setup()
	{
		Event::on(
			Asset::class,
			Reliquary::EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS,
			[self::class, 'getAssetAttributeOptions']
		);

		Event::on(
			Category::class,
			Reliquary::EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS,
			[self::class, 'getCategoryAttributeOptions']
		);

		Event::on(
			Entry::class,
			Reliquary::EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS,
			[self::class, 'getEntryAttributeOptions']
		);

		Event::on(
			Tag::class,
			Reliquary::EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS,
			[self::class, 'getTagAttributeOptions']
		);

		Event::on(
			User::class,
			Reliquary::EVENT_RELIQUARY_GET_ATTRIBUTE_OPTIONS,
			[self::class, 'getUserAttributeOptions']
		);
	}

	public static function getAssetAttributeOptions(ReliquaryGetAttributeOptions $event)
	{
		switch ($event->attribute) {
			case 'title':
				self::handleStringAttribute($event);
				break;
			case 'filename':
				self::handleStringAttribute($event);
				break;
			case 'kind':
				self::handleFileKindAttribute($event);
				break;
		}
	}

	public static function getCategoryAttributeOptions(ReliquaryGetAttributeOptions $event)
	{
		switch ($event->attribute) {
			case 'title':
				self::handleStringAttribute($event);
				break;
		}
	}

	public static function getEntryAttributeOptions(ReliquaryGetAttributeOptions $event)
	{
		switch ($event->attribute) {
			case 'title':
				self::handleStringAttribute($event);
				break;
			case 'author':
				self::handleAuthorAttribute($event);
				break;
			case 'slug':
				self::handleStringAttribute($event);
				break;
			case 'postDate':
				self::handleDateAttribute($event);
				break;
			case 'expiryDate':
				self::handleDateAttribute($event);
				break;
		}
	}

	public static function getTagAttributeOptions(ReliquaryGetAttributeOptions $event)
	{
		switch ($event->attribute) {
			case 'title':
				self::handleStringAttribute($event);
				break;
		}
	}

	public static function getUserAttributeOptions(ReliquaryGetAttributeOptions $event)
	{
		switch ($event->attribute) {
			case 'username':
				self::handleStringAttribute($event);
				break;
			case 'firstName':
				self::handleStringAttribute($event);
				break;
			case 'lastName':
				self::handleStringAttribute($event);
				break;
			case 'email':
				self::handleStringAttribute($event);
				break;
		}
	}

	private static function handleStringAttribute(ReliquaryGetAttributeOptions $event)
	{
		$event->handled = true;
		$event->type = 'string';
		$event->total = 0;
	}

	private static function handleDateAttribute(ReliquaryGetAttributeOptions $event)
	{
		$event->handled = true;
		$event->type = 'date';
		$event->total = 0;
	}

	private static function handleFileKindAttribute(ReliquaryGetAttributeOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';
		foreach (AssetsHelper::getFileKinds() as $kind => $data) {
			$event->options[] = [
				'label' => $kind,
				'value' => $data['label'],
			];
		}
		$event->total = 0;
	}

	private static function handleAuthorAttribute(ReliquaryGetAttributeOptions $event)
	{
		$event->handled = true;
		$event->type = 'multiple';

		$query = User::find()->can('editEntries');

		$event->total = $query->count();

		if (is_numeric($event->hint)) {
			$query->offset((intval($event->hint) - 1) * 50);
		}

		$query->limit(50);

		foreach ($query->all() as $item) {
			$event->options[] = [
				'value' => $item->id,
				'label' => $item->fullName,
			];
		}

		if ($event->total > 50) {
			$event->partial = true;
		}
	}
}
