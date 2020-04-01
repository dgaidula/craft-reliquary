<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\adapters;

use jaredlindo\reliquary\events\ReliquaryExtendElementTypeQuery;
use jaredlindo\reliquary\Reliquary;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;

use yii\base\Event;
use yii\db\Expression;

/**
 * Adapter that attaches event handlers to field classes in order to allow
 * retrieval of available field options.
 */
class ElementTypeQueries
{
	public static function setup()
	{
		Event::on(
			Asset::class,
			Reliquary::EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY,
			[self::class, 'buildAssetExpression']
		);

		Event::on(
			Category::class,
			Reliquary::EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY,
			[self::class, 'buildCategoryExpression']
		);

		Event::on(
			Entry::class,
			Reliquary::EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY,
			[self::class, 'buildEntryExpression']
		);

		Event::on(
			Tag::class,
			Reliquary::EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY,
			[self::class, 'buildTagExpression']
		);

		Event::on(
			User::class,
			Reliquary::EVENT_RELIQUARY_EXTEND_ELEMENT_TYPE_QUERY,
			[self::class, 'buildUserExpression']
		);
	}

	public static function buildAssetExpression(ReliquaryExtendElementTypeQuery $event)
	{
		$event->handled = true;

		$event->query = (new Query())
			->select([
				'e.id as id',
				'r_etq.volumeId as typeId',
			])
			->from('{{%assets}} r_etq')
			->innerJoin('{{%elements}} e', 'r_etq.id = e.id')
			->where([
				'r_etq.volumeId' => $event->searchElement->elementTypeId,
			]);
	}

	public static function buildCategoryExpression(ReliquaryExtendElementTypeQuery $event)
	{
		$event->handled = true;

		$event->query = (new Query())
			->select([
				'e.id as id',
				'r_etq.groupId as typeId',
			])
			->from('{{%categories}} r_etq')
			->innerJoin('{{%elements}} e', 'r_etq.id = e.id')
			->where([
				'r_etq.groupId' => $event->searchElement->elementTypeId,
			]);
	}

	public static function buildEntryExpression(ReliquaryExtendElementTypeQuery $event)
	{
		$event->handled = true;

		$event->query = (new Query())
			->select([
				'e.id as id',
				'r_etq.typeId as typeId',
			])
			->from('{{%entries}} r_etq')
			->innerJoin('{{%elements}} e', 'r_etq.id = e.id')
			->where([
				'and',
				['r_etq.typeId' => $event->searchElement->elementTypeId],
				['not', ['r_etq.postDate' => null]],
				['<', 'r_etq.postDate', new Expression('NOW()')],
				[
					'or',
					['r_etq.expiryDate' => null],
					['>', 'r_etq.expiryDate', new Expression('NOW()')],
				]
			]);
	}

	public static function buildTagExpression(ReliquaryExtendElementTypeQuery $event)
	{
		$event->handled = true;

		$event->query = (new Query())
			->select([
				'e.id as id',
				'r_etq.groupId as typeId',
			])
			->from('{{%tags}} r_etq')
			->innerJoin('{{%elements}} e', 'r_etq.id = e.id')
			->where([
				'r_etq.groupId' => $event->searchElement->elementTypeId,
			]);
	}

	public static function buildUserExpression(ReliquaryExtendElementTypeQuery $event)
	{
		$event->handled = true;

		$event->query = (new Query())
			->select([
				'e.id as id',
				'NULL as typeId',
			])
			->from('{{%users}} r_etq')
			->innerJoin('{{%elements}} e', 'r_etq.id = e.id');
	}
}
