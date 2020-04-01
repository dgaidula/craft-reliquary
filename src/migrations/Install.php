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
		$this->createTable('{{%reliquary_indexqueue}}', [
			'id' => $this->primaryKey(),
			'elementId' => $this->integer()->notNull(),
			'siteId' => $this->integer()->notNull(),
			'fieldId' => $this->integer(),
			'attribute' => $this->string(),
			'value' => $this->text()->notNull(),
		]);

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

		$this->createTable('{{%reliquary_customfieldweights}}', [
			'id' => $this->primaryKey(),
			'fieldId' => $this->integer(),
			'attribute' => $this->string(),
			'elementType' => $this->string()->notNull(),
			'elementTypeId' => $this->integer(),
			'multiplier' => $this->float()->notNull(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createTable('{{%reliquary_searchrecord}}', [
			'id' => $this->primaryKey(),
			'subjectId' => $this->char(32)->notNull(),
			'time' => $this->dateTime()->notNull(),
			'term' => $this->string(),
			'filters' => $this->text(),
		]);

		$extraProperties = null;
		if ($this->db->getIsMysql()) {
			$extraProperties = 'COLLATE=\'ascii_general_ci\' ENGINE=MyISAM DEFAULT CHARSET=\'ascii\'';
		}
		$this->createTable('{{%reliquary_ngramindex}}', [
			'id' => $this->primaryKey()->unsigned(),
			'elementId' => $this->integer()->notNull(),
			'siteId' => $this->integer()->notNull(),
			'fieldId' => $this->integer(),
			'attribute' => $this->string(),
			'ngrams' => $this->integer()->notNull()->defaultValue(0),
		], $extraProperties);

		$extraProperties = null;
		if ($this->db->getIsMysql()) {
			$extraProperties = 'ROW_FORMAT=FIXED ENGINE=MyISAM';
		}
		$this->createTable('{{%reliquary_ngramdata}}', [
			'indexId' => $this->integer()->unsigned(),
			'offset' => $this->integer()->unsigned()->notNull(),
			'key' => $this->char(3)->notNull(),
		], $extraProperties);

		$this->addPrimaryKey(null, '{{%reliquary_ngramdata}}', ['indexId', 'offset']);

		$this->createIndex(null, '{{%reliquary_searchgroups}}', ['handle'], true);
		$this->createIndex(null, '{{%reliquary_customfieldweights}}', ['fieldId', 'attribute', 'elementType', 'elementTypeId'], true);
		$this->createIndex(null, '{{%reliquary_ngramindex}}', ['elementId', 'siteId', 'fieldId', 'attribute'], true);
		$this->createIndex(null, '{{%reliquary_ngramdata}}', ['key']);
		$this->createIndex(null, '{{%reliquary_searchgroupfilters}}', ['groupId', 'fieldId', 'attribute'], true);
		$this->createIndex(null, '{{%reliquary_searchgroupfilters}}', ['groupId', 'handle'], true);
		$this->createIndex(null, '{{%reliquary_searchrecord}}', ['subjectId']);
		$this->createIndex(null, '{{%reliquary_searchrecord}}', ['term']);

		$this->addForeignKey(null, '{{%reliquary_searchgroups}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);
		$this->addForeignKey(null, '{{%reliquary_searchgroupelements}}', ['groupId'], '{{%reliquary_searchgroups}}', ['id'], 'CASCADE', null);
		$this->addForeignKey(null, '{{%reliquary_searchgroupfilters}}', ['groupId'], '{{%reliquary_searchgroups}}', ['id'], 'CASCADE', null);
		$this->addForeignKey(null, '{{%reliquary_searchgroupfilters}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
		$this->addForeignKey(null, '{{%reliquary_customfieldweights}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
	}

	public function safeDown()
	{
		$this->dropTableIfExists('{{%reliquary_ngramdata}}');
		$this->dropTableIfExists('{{%reliquary_ngramindex}}');
		$this->dropTableIfExists('{{%reliquary_searchrecord}}');
		$this->dropTableIfExists('{{%reliquary_customfieldweights}}');
		$this->dropTableIfExists('{{%reliquary_searchgroupfilters}}');
		$this->dropTableIfExists('{{%reliquary_searchgroupelements}}');
		$this->dropTableIfExists('{{%reliquary_searchgroups}}');
		$this->dropTableIfExists('{{%reliquary_indexqueue}}');
	}
}
