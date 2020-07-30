<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\migrations;

use craft\db\Migration;

/**
 * Runs all initial setup on first install.
 */
class Install extends Migration
{

	public function safeUp()
	{
		$this->createTable('{{%reliquary_searchgroups}}', [
			'id' => $this->primaryKey(),
			'siteId' => $this->integer()->notNull(),
			'handle' => $this->string()->notNull(),
			'name' => $this->string()->notNull(),
			'template' => $this->string()->notNull(),
			'pageSize' => $this->integer()->notNull(),
			'searchOrder' => $this->string()->notNull(),
			'sortOrder' => $this->integer()->notNull(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createTable('{{%reliquary_searchgroupelements}}', [
			'id' => $this->primaryKey(),
			'groupId' => $this->integer()->notNull(),
			'elementType' => $this->string()->notNull(),
			'elementTypeId' => $this->integer(),
			'sortOrder' => $this->integer()->notNull(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createTable('{{%reliquary_searchgroupfilters}}', [
			'id' => $this->primaryKey(),
			'groupId' => $this->integer()->notNull(),
			'fieldId' => $this->integer(),
			'attribute' => $this->string(),
			'handle' => $this->string()->notNull(),
			'name' => $this->string()->notNull(),
			'sortOrder' => $this->integer()->notNull(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createIndex(null, '{{%reliquary_searchgroups}}', ['handle'], true);
		$this->createIndex(null, '{{%reliquary_searchgroupfilters}}', ['groupId', 'fieldId', 'attribute'], true);
		$this->createIndex(null, '{{%reliquary_searchgroupfilters}}', ['groupId', 'handle'], true);

		$this->addForeignKey(null, '{{%reliquary_searchgroups}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);
		$this->addForeignKey(null, '{{%reliquary_searchgroupelements}}', ['groupId'], '{{%reliquary_searchgroups}}', ['id'], 'CASCADE', null);
		$this->addForeignKey(null, '{{%reliquary_searchgroupfilters}}', ['groupId'], '{{%reliquary_searchgroups}}', ['id'], 'CASCADE', null);
		$this->addForeignKey(null, '{{%reliquary_searchgroupfilters}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
	}

	public function safeDown()
	{
		$this->dropTableIfExists('{{%reliquary_searchgroupfilters}}');
		$this->dropTableIfExists('{{%reliquary_searchgroupelements}}');
		$this->dropTableIfExists('{{%reliquary_searchgroups}}');
	}
}
