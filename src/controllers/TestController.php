<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\controllers;

use jaredlindo\reliquary\Reliquary;
use jaredlindo\reliquary\models\SearchGroup;
use jaredlindo\reliquary\models\SearchGroupElement;
use jaredlindo\reliquary\models\SearchGroupFilter;

use Craft;
use craft\base\Field;
use craft\helpers\FileHelper;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\EntryType;
use craft\models\FieldGroup;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use craft\models\TagGroup;
use craft\web\Controller;

use yii\web\ForbiddenHttpException;
use yii\helpers\Json;

/**
 * A dummy test controller for facilitating faster manual testing.
 */
class TestController extends Controller
{
	/**
	 * @inheritdoc
	 */
	public function init(): void
	{
		parent::init();

		if (!Craft::$app->getConfig()->general->devMode) {
			throw new ForbiddenHttpException('Test data can only be managed in dev mode.');
		}
	}

	/**
	 * Creates a small set of disposable test data isolated within two mock sites.
	 */
	public function actionCreateTestData()
	{
		// Create test sites.
		$site_a = new Site([
			'name' => 'Reliquary Test Site A',
			'handle' => 'reliquaryTestSiteA',
			'groupId' => Craft::$app->getSites()->getPrimarySite()->groupId,
			'language' => Craft::$app->getSites()->getPrimarySite()->language,
			'baseUrl' => '/reliquary/site-a',
		]);
		if (!Craft::$app->getSites()->saveSite($site_a)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 1])]);
		}

		$site_b = new Site([
			'name' => 'Reliquary Test Site B',
			'handle' => 'reliquaryTestSiteB',
			'groupId' => Craft::$app->getSites()->getPrimarySite()->groupId,
			'language' => Craft::$app->getSites()->getPrimarySite()->language,
			'baseUrl' => '/reliquary/site-b',
		]);
		if (!Craft::$app->getSites()->saveSite($site_b)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 2])]);
		}

		// Create asset volume storage.
		if (!FileHelper::createDirectory(Craft::getAlias('@webroot/reliquary-assets'))) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 3])]);
		}

		// Create asset volume.
		$volume = Craft::$app->getVolumes()->createVolume([
			'type' => \craft\volumes\Local::class,
			'name' => 'Reliquary Test Assets',
			'handle' => 'reliquaryTestAssets',
			'hasUrls' => true,
			'url' => '@web/reliquary-assets',
			'settings' => [
				'path' => '@webroot/reliquary-assets'
			]
		]);
		$fieldlayout = new FieldLayout([
			'type' => Asset::class,
		]);
		$volume->setFieldLayout($fieldlayout);
		if (!Craft::$app->getVolumes()->saveVolume($volume)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 4])]);
		}

		// Create category group.
		$categorygroup = new CategoryGroup([
			'name' => 'Reliquary Test Categories',
			'handle' => 'reliquaryTestCategories',
		]);
		$categorygroup_sitesettings = [];
		foreach (Craft::$app->getSites()->getAllSites() as $site) {
			$categorygroup_sitesettings[$site->id] = new CategoryGroup_SiteSettings([
				'siteId' => $site->id,
				'hasUrls' => false,
				'uriFormat' => null,
				'template' => null,
			]);
		}
		$categorygroup->setSiteSettings($categorygroup_sitesettings);
		$fieldlayout = new FieldLayout([
			'type' => Category::class,
		]);
		$categorygroup->setFieldLayout($fieldlayout);
		if (!Craft::$app->getCategories()->saveGroup($categorygroup)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 5])]);
		}

		// Create tag group.
		$taggroup = new TagGroup([
			'name' => 'Reliquary Test Tags',
			'handle' => 'reliquaryTestTags',
		]);
		$fieldlayout = new FieldLayout([
			'type' => Tag::class,
		]);
		$taggroup->setFieldLayout($fieldlayout);
		if (!Craft::$app->getTags()->saveTagGroup($taggroup)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 6])]);
		}

		// Create field group.
		$fieldgroup = new FieldGroup([
			'name' => 'Reliquary Fields',
		]);
		if (!Craft::$app->getFields()->saveGroup($fieldgroup)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 7])]);
		}

		// Create asset field.
		$assetfield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Assets::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Asset Field',
			'handle' => 'reliquaryAssetField',
			'instructions' => 'Test asset field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_NONE,
			'translationKeyFormat' => null,
			'settings' => [
				'useSingleFolder' => true,
				'viewMode' => 'list',
				'defaultUploadLocationSource' => 'folder:' . Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id,
				'defaultUploadLocationSubpath' => '',
				'singleUploadLocationSource' => 'folder:' . Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id,
				'singleUploadLocationSubpath' => '',
				'localizeRelations' => true,
			]
		]);
		if (!Craft::$app->getFields()->saveField($assetfield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 8])]);
		}

		// Create text field.
		$textfield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\PlainText::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Text Field',
			'handle' => 'reliquaryTextField',
			'instructions' => 'Test text field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_SITE,
			'translationKeyFormat' => null,
			'settings' => []
		]);
		if (!Craft::$app->getFields()->saveField($textfield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 9])]);
		}

		// Create checkbox field.
		$checkboxfield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Checkboxes::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Checkbox Field',
			'handle' => 'reliquaryCheckboxField',
			'instructions' => 'Test checkbox field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_SITE,
			'translationKeyFormat' => null,
			'settings' => [
				'options' => [
					[
						'label' => 'Option A',
						'value' => 'optionA',
						'default' => false,
					], [
						'label' => 'Option B',
						'value' => 'optionB',
						'default' => false,
					], [
						'label' => 'Option C',
						'value' => 'optionC',
						'default' => false,
					]
				]
			]
		]);
		if (!Craft::$app->getFields()->saveField($checkboxfield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 10])]);
		}

		// Create dropdown field.
		$dropdownfield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Dropdown::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Dropdown Field',
			'handle' => 'reliquaryDropdownField',
			'instructions' => 'Test dropdown field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_SITE,
			'translationKeyFormat' => null,
			'settings' => [
				'options' => [
					[
						'label' => 'Option A',
						'value' => 'optionA',
						'default' => true,
					], [
						'label' => 'Option B',
						'value' => 'optionB',
						'default' => false,
					], [
						'label' => 'Option C',
						'value' => 'optionC',
						'default' => false,
					]
				]
			]
		]);
		if (!Craft::$app->getFields()->saveField($dropdownfield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 11])]);
		}

		// Create category field.
		$categoryfield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Categories::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Category Field',
			'handle' => 'reliquaryCategoryField',
			'instructions' => 'Test category field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_NONE,
			'translationKeyFormat' => null,
			'settings' => [
				'source' => 'group:' . $categorygroup->id,
				'branchLimit' => false,
				'localizeRelations' => true,
			]
		]);
		if (!Craft::$app->getFields()->saveField($categoryfield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 12])]);
		}

		// Create tag field.
		$tagfield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Tags::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Tag Field',
			'handle' => 'reliquaryTagField',
			'instructions' => 'Test tag field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_NONE,
			'translationKeyFormat' => null,
			'settings' => [
				'source' => 'taggroup:' . $taggroup->id,
				'localizeRelations' => true,
			]
		]);
		if (!Craft::$app->getFields()->saveField($tagfield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 13])]);
		}

		// Create table field.
		$tablefield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Table::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Table Field',
			'handle' => 'reliquaryTableField',
			'instructions' => 'Test table field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_SITE,
			'translationKeyFormat' => null,
			'settings' => [
				'columns' => [
					'col1' => [
						'heading' => 'Column A',
						'handle' => 'columnA',
						'width' => null,
						'type' => 'lightswitch'
					],
					'col2' => [
						'heading' => 'Column B',
						'handle' => 'columnB',
						'width' => null,
						'type' => 'number'
					],
					'col3' => [
						'heading' => 'Column C',
						'handle' => 'columnC',
						'width' => null,
						'type' => 'singleline'
					]
				],
				'defaults' => []
			]
		]);
		if (!Craft::$app->getFields()->saveField($tablefield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 14])]);
		}

		// Create matrix field.
		$matrixfield = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Matrix::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Reliquary Matrix Field',
			'handle' => 'reliquaryMatrixField',
			'instructions' => 'Test matrix field for Reliquary.',
			'translationMethod' => Field::TRANSLATION_METHOD_NONE,
			'translationKeyFormat' => null,
			'settings' => [
				'minBlocks' => null,
				'maxBlocks' => null,
				'localizeBlocks' => true,
				'blockTypes' => [
					'new1' => [
						'name' => 'Block A',
						'handle' => 'blockA',
						'fields' => [
							'new1' => [
								'type' => \craft\fields\URL::class,
								'groupId' => $fieldgroup->id,
								'name' => 'Matrix URL Field',
								'handle' => 'matrixUrlField',
								'instructions' => 'Inner URL field.',
								'translationMethod' => Field::TRANSLATION_METHOD_NONE,
								'translationKeyFormat' => null,
								'settings' => []
							],
							'new2' => [
								'type' => \craft\fields\Color::class,
								'groupId' => $fieldgroup->id,
								'name' => 'Matrix Color Field',
								'handle' => 'matrixColorField',
								'instructions' => 'Inner Color field.',
								'translationMethod' => Field::TRANSLATION_METHOD_NONE,
								'translationKeyFormat' => null,
								'settings' => [
									'defaultColor' => '#ff0000',
								]
							]
						]
					],
					'new2' => [
						'name' => 'Block B',
						'handle' => 'blockB',
						'fields' => [
							'new1' => [
								'type' => \craft\fields\Email::class,
								'groupId' => $fieldgroup->id,
								'name' => 'Matrix Email Field',
								'handle' => 'matrixEmailField',
								'instructions' => 'Inner Email field.',
								'translationMethod' => Field::TRANSLATION_METHOD_NONE,
								'translationKeyFormat' => null,
								'settings' => []
							],
							'new2' => [
								'type' => \craft\fields\Number::class,
								'groupId' => $fieldgroup->id,
								'name' => 'Matrix Number Field',
								'handle' => 'matrixNumberField',
								'instructions' => 'Inner Number field.',
								'translationMethod' => Field::TRANSLATION_METHOD_NONE,
								'translationKeyFormat' => null,
								'settings' => [
									'defaultValue' => null,
									'min' => 0,
									'max' => null,
									'decimals' => 0,
									'size' => null,
								]
							]
						]
					]
				]
			]
		]);
		if (!Craft::$app->getFields()->saveField($matrixfield)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 15])]);
		}

		// Create some sections.
		$channel = new Section([
			'name' => 'Reliquary Test Channel',
			'handle' => 'reliquaryTestChannel',
			'type' => Section::TYPE_CHANNEL,
			'siteSettings' => [
				new Section_SiteSettings([
					'siteId' => $site_a->id,
					'enabledByDefault' => true,
					'hasUrls' => true,
					'uriFormat' => 'reliquary/channel/{slug}',
					'template' => '_reliquaryChannel',
				]),
				new Section_SiteSettings([
					'siteId' => $site_b->id,
					'enabledByDefault' => true,
					'hasUrls' => true,
					'uriFormat' => 'reliquary/channel/{slug}',
					'template' => '_reliquaryChannel',
				])
			]
		]);
		if (!Craft::$app->getSections()->saveSection($channel)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 16])]);
		}

		$structure = new Section([
			'name' => 'Reliquary Test Structure',
			'handle' => 'reliquaryTestStructure',
			'type' => Section::TYPE_STRUCTURE,
			'siteSettings' => [
				new Section_SiteSettings([
					'siteId' => $site_a->id,
					'enabledByDefault' => true,
					'hasUrls' => true,
					'uriFormat' => 'reliquary/structure/{slug}',
					'template' => '_reliquaryStructure',
				]),
				new Section_SiteSettings([
					'siteId' => $site_b->id,
					'enabledByDefault' => true,
					'hasUrls' => true,
					'uriFormat' => 'reliquary/structure/{slug}',
					'template' => '_reliquaryStructure',
				])
			]
		]);
		if (!Craft::$app->getSections()->saveSection($structure)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 17])]);
		}

		// Remove default entry types for the sections.
		$entrytype = Craft::$app->getSections()->getEntryTypesByHandle('reliquaryTestChannel')[0];
		if (!Craft::$app->getSections()->deleteEntryType($entrytype)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 18])]);
		}

		$entrytype = Craft::$app->getSections()->getEntryTypesByHandle('reliquaryTestStructure')[0];
		if (!Craft::$app->getSections()->deleteEntryType($entrytype)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 19])]);
		}

		// Create entry types for the sections.
		$entrytype = new EntryType([
			'name' => 'Simple Channel Item',
			'handle' => 'simpleChannelItem',
			'sectionId' => $channel->id,
		]);
		$fieldlayout = new FieldLayout([
			'type' => Entry::class,
		]);
		$tab = new FieldLayoutTab([
			'name' => 'Content',
			'sortOrder' => 1,
		]);
		$tab->setFields([$textfield, $checkboxfield]);
		$fieldlayout->setTabs([$tab]);
		$fieldlayout->setFields($tab->getFields());
		$entrytype->setFieldLayout($fieldlayout);
		if (!Craft::$app->getSections()->saveEntryType($entrytype)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 20])]);
		}

		$entrytype = new EntryType([
			'name' => 'Advanced Channel Item',
			'handle' => 'advancedChannelItem',
			'sectionId' => $channel->id,
		]);
		$fieldlayout = new FieldLayout([
			'type' => Entry::class,
		]);
		$tab = new FieldLayoutTab([
			'name' => 'Content',
			'sortOrder' => 1,
		]);
		$tab->setFields([$tablefield, $tagfield]);
		$fieldlayout->setTabs([$tab]);
		$fieldlayout->setFields($tab->getFields());
		$entrytype->setFieldLayout($fieldlayout);
		if (!Craft::$app->getSections()->saveEntryType($entrytype)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 21])]);
		}

		$entrytype = new EntryType([
			'name' => 'Simple Structure Item',
			'handle' => 'simpleStructureItem',
			'sectionId' => $structure->id,
		]);
		$fieldlayout = new FieldLayout([
			'type' => Entry::class,
		]);
		$tab = new FieldLayoutTab([
			'name' => 'Content',
			'sortOrder' => 1,
		]);
		$tab->setFields([$dropdownfield, $assetfield]);
		$fieldlayout->setTabs([$tab]);
		$fieldlayout->setFields($tab->getFields());
		$entrytype->setFieldLayout($fieldlayout);
		if (!Craft::$app->getSections()->saveEntryType($entrytype)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 22])]);
		}

		$entrytype = new EntryType([
			'name' => 'Advanced Structure Item',
			'handle' => 'advancedStructureItem',
			'sectionId' => $structure->id,
		]);
		$fieldlayout = new FieldLayout([
			'type' => Entry::class,
		]);
		$tab = new FieldLayoutTab([
			'name' => 'Content',
			'sortOrder' => 1,
		]);
		$tab->setFields([$categoryfield, $matrixfield]);
		$fieldlayout->setTabs([$tab]);
		$fieldlayout->setFields($tab->getFields());
		$entrytype->setFieldLayout($fieldlayout);
		if (!Craft::$app->getSections()->saveEntryType($entrytype)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 23])]);
		}

		// Create categories.
		$category = new Category([
			'title' => 'Category A',
			'slug' => 'category-a',
			'enabled' => true,
			'groupId' => $categorygroup->id,
			'fieldLayoutId' => $categorygroup->fieldLayoutId,
		]);
		if (!Craft::$app->getElements()->saveElement($category)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 24])]);
		}

		$category = new Category([
			'title' => 'Category B',
			'slug' => 'category-b',
			'enabled' => true,
			'groupId' => $categorygroup->id,
			'fieldLayoutId' => $categorygroup->fieldLayoutId,
		]);
		if (!Craft::$app->getElements()->saveElement($category)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 25])]);
		}

		$category = new Category([
			'title' => 'Category C',
			'slug' => 'category-c',
			'enabled' => true,
			'groupId' => $categorygroup->id,
			'fieldLayoutId' => $categorygroup->fieldLayoutId,
		]);
		if (!Craft::$app->getElements()->saveElement($category)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 26])]);
		}

		// Create tags.
		$tag = new Tag([
			'groupId' => $taggroup->id,
			'title' => 'Tag A',
		]);
		if (!Craft::$app->getElements()->saveElement($tag)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 27])]);
		}

		$tag = new Tag([
			'groupId' => $taggroup->id,
			'title' => 'Tag B',
		]);
		if (!Craft::$app->getElements()->saveElement($tag)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 28])]);
		}

		$tag = new Tag([
			'groupId' => $taggroup->id,
			'title' => 'Tag C',
		]);
		if (!Craft::$app->getElements()->saveElement($tag)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 29])]);
		}

		$tag = new Tag([
			'groupId' => $taggroup->id,
			'title' => 'Tag D',
		]);
		if (!Craft::$app->getElements()->saveElement($tag)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 30])]);
		}

		// Create assets.
		$filename = uniqid('placeholder1.png');
		@copy(
			Craft::$app->getPath()->getVendorPath() . '/jaredlindo/reliquary/src/examples/placeholder1.png',
			Craft::$app->getPath()->getTempPath() . '/' . $filename
		);
		$asset = new Asset([
			'tempFilePath' => Craft::$app->getPath()->getTempPath() . '/' . $filename,
			'filename' => 'placeholder1.png',
			'newFolderId' => Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id,
			'volumeId' => $volume->id,
			'scenario' => Asset::SCENARIO_CREATE,
		]);
		if (!Craft::$app->getElements()->saveElement($asset)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 31])]);
		}

		$filename = uniqid('placeholder2.png');
		@copy(
			Craft::$app->getPath()->getVendorPath() . '/jaredlindo/reliquary/src/examples/placeholder2.png',
			Craft::$app->getPath()->getTempPath() . '/' . $filename
		);
		$asset = new Asset([
			'tempFilePath' => Craft::$app->getPath()->getTempPath() . '/' . $filename,
			'filename' => 'placeholder2.png',
			'newFolderId' => Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id,
			'volumeId' => $volume->id,
			'scenario' => Asset::SCENARIO_CREATE,
		]);
		if (!Craft::$app->getElements()->saveElement($asset)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 32])]);
		}

		// Create entries.
		$entry = new Entry([
			'sectionId' => $channel->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleChannelItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Channel Entry 1: Alpha (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryTextField' => 'Example text content for site A.',
			'reliquaryCheckboxField' => [
				'optionA',
				'optionB',
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 33])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Channel Entry 1: Alpha (Site B)';
		$entry->setFieldValues([
			'reliquaryTextField' => 'Example text content for site B.',
			'reliquaryCheckboxField' => [
				'optionB'
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 34])]);
		}

		$entry = new Entry([
			'sectionId' => $channel->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleChannelItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Channel Entry 2: Bravo (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryTextField' => 'Example text content for site A.',
			'reliquaryCheckboxField' => [
				'optionA'
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 35])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Channel Entry 2: Bravo (Site B)';
		$entry->setFieldValues([
			'reliquaryTextField' => 'Example text content for site B.',
			'reliquaryCheckboxField' => [],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 36])]);
		}

		$entry = new Entry([
			'sectionId' => $channel->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedChannelItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Channel Entry 3: Charlie (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryTableField' => [
				[
					'col1' => true,
					'col2' => '42',
					'col3' => 'First example text column for site A.',
				], [
					'col1' => false,
					'col2' => '12',
					'col3' => 'Second example text column for site A.',
				]
			],
			'reliquaryTagField' => [
				Tag::find()->groupId($taggroup->id)->title('Tag A')->one()->id,
				Tag::find()->groupId($taggroup->id)->title('Tag C')->one()->id,
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 37])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Channel Entry 3: Charlie (Site B)';
		$entry->setFieldValues([
			'reliquaryTableField' => [
				[
					'col1' => false,
					'col2' => '8675309',
					'col3' => 'Only text column for site B.',
				]
			],
			'reliquaryTagField' => [
				Tag::find()->groupId($taggroup->id)->title('Tag B')->one()->id,
				Tag::find()->groupId($taggroup->id)->title('Tag C')->one()->id,
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 38])]);
		}

		$entry = new Entry([
			'sectionId' => $channel->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedChannelItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Channel Entry 4: Delta (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryTableField' => [
				[
					'col1' => true,
					'col2' => '299792458',
					'col3' => 'Only text column for site B.',
				]
			],
			'reliquaryTagField' => [
				Tag::find()->groupId($taggroup->id)->title('Tag A')->one()->id,
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 39])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Channel Entry 4: Delta (Site B)';
		$entry->setFieldValues([
			'reliquaryTableField' => [],
			'reliquaryTagField' => [
				Tag::find()->groupId($taggroup->id)->title('Tag B')->one()->id,
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 40])]);
		}

		$entry = new Entry([
			'sectionId' => $structure->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleStructureItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Structure Entry 1: Echo (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryDropdownField' => 'optionA',
			'reliquaryAssetField' => [
				Asset::find()->volumeId($volume->id)->filename('placeholder1.png')->one()->id,
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 41])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Structure Entry 1: Echo (Site B)';
		$entry->setFieldValues([
			'reliquaryDropdownField' => 'optionB',
			'reliquaryAssetField' => [
				Asset::find()->volumeId($volume->id)->filename('placeholder2.png')->one()->id,
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 42])]);
		}

		$entry = new Entry([
			'sectionId' => $structure->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleStructureItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Structure Entry 2: Foxtrot (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryDropdownField' => null,
			'reliquaryAssetField' => [
				Asset::find()->volumeId($volume->id)->filename('placeholder2.png')->one()->id,
				Asset::find()->volumeId($volume->id)->filename('placeholder1.png')->one()->id,
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 43])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Structure Entry 2: Foxtrot (Site B)';
		$entry->setFieldValues([
			'reliquaryDropdownField' => 'optionB',
			'reliquaryAssetField' => [],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 44])]);
		}

		$entry = new Entry([
			'sectionId' => $structure->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedStructureItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Structure Entry 3: Golf (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryCategoryField' => [
				Category::find()->groupId($categorygroup->id)->slug('category-a')->one()->id,
			],
			'reliquaryMatrixField' => [
				'new1' => [
					'type' => 'blockA',
					'enabled' => '1',
					'fields' => [
						'matrixUrlField' => 'https://craftcms.com/',
						'matrixColorField' => '#00ffff',
					],
				],
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 45])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Structure Entry 3: Golf (Site B)';
		$entry->setFieldValues([
			'reliquaryCategoryField' => [
				Category::find()->groupId($categorygroup->id)->slug('category-b')->one()->id,
			],
			'reliquaryMatrixField' => [
				'new1' => [
					'type' => 'blockB',
					'enabled' => '1',
					'fields' => [
						'matrixEmailField' => 'example@example.com',
						'matrixNumberField' => '65535',
					],
				],
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 46])]);
		}

		$entry = new Entry([
			'sectionId' => $structure->id,
			'siteId' => $site_a->id,
			'typeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedStructureItem')[0]->id,
			'authorId' => Craft::$app->getUser()->getIdentity()->id,
			'enabled' => true,
			'title' => 'Structure Entry 4: Hotel (Site A)',
		]);
		$entry->setFieldValues([
			'reliquaryCategoryField' => [
				Category::find()->groupId($categorygroup->id)->slug('category-a')->one()->id,
				Category::find()->groupId($categorygroup->id)->slug('category-b')->one()->id,
			],
			'reliquaryMatrixField' => [],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 47])]);
		}

		$entry = Craft::$app->getEntries()->getEntryById($entry->id, $site_b->id);
		$entry->title = 'Structure Entry 4: Hotel (Site B)';
		$entry->setFieldValues([
			'reliquaryCategoryField' => [],
			'reliquaryMatrixField' => [
				'new1' => [
					'type' => 'blockA',
					'enabled' => '1',
					'fields' => [
						'matrixUrlField' => '',
						'matrixColorField' => '#000000',
					],
				],
				'new2' => [
					'type' => 'blockB',
					'enabled' => '1',
					'fields' => [
						'matrixEmailField' => 'test@example.com',
						'matrixNumberField' => '',
					],
				]
			],
		]);
		if (!Craft::$app->getElements()->saveElement($entry)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 48])]);
		}

		return $this->asJson(['success' => true]);
	}

	/**
	 * Cleans up the disposable test data.
	 */
	public function actionDeleteTestData()
	{
		// Delete sections.
		$section = Craft::$app->getSections()->getSectionByHandle('reliquaryTestStructure');
		if ($section) {
			Craft::$app->getSections()->deleteSection($section);
		}

		$section = Craft::$app->getSections()->getSectionByHandle('reliquaryTestChannel');
		if ($section) {
			Craft::$app->getSections()->deleteSection($section);
		}

		// Delete matrix field.
		$field = Craft::$app->getFields()->getFieldByHandle('reliquaryMatrixField');
		if ($field) {
			Craft::$app->getFields()->deleteField($field);
		}

		// Delete table field.
		$field = Craft::$app->getFields()->getFieldByHandle('reliquaryTableField');
		if ($field) {
			Craft::$app->getFields()->deleteField($field);
		}

		// Delete tag field.
		$field = Craft::$app->getFields()->getFieldByHandle('reliquaryTagField');
		if ($field) {
			Craft::$app->getFields()->deleteField($field);
		}

		// Delete category field.
		$field = Craft::$app->getFields()->getFieldByHandle('reliquaryCategoryField');
		if ($field) {
			Craft::$app->getFields()->deleteField($field);
		}

		// Delete dropdown field.
		$field = Craft::$app->getFields()->getFieldByHandle('reliquaryDropdownField');
		if ($field) {
			Craft::$app->getFields()->deleteField($field);
		}

		// Delete checkbox field.
		$field = Craft::$app->getFields()->getFieldByHandle('reliquaryCheckboxField');
		if ($field) {
			Craft::$app->getFields()->deleteField($field);
		}

		// Delete text field.
		$field = Craft::$app->getFields()->getFieldByHandle('reliquaryTextField');
		if ($field) {
			Craft::$app->getFields()->deleteField($field);
		}

		// Delete field group.
		$fieldgroups = Craft::$app->getFields()->getAllGroups();
		foreach ($fieldgroups as $fieldgroup) {
			if ($fieldgroup->name == 'Reliquary Fields') {
				Craft::$app->getFields()->deleteGroup($fieldgroup);
			}
		}

		// Delete category group.
		$categorygroup = Craft::$app->getCategories()->getGroupByHandle('reliquaryTestCategories');
		if ($categorygroup) {
			Craft::$app->getCategories()->deleteGroup($categorygroup);
		}

		// Delete tag group.
		$taggroup = Craft::$app->getTags()->getTagGroupByHandle('reliquaryTestTags');
		if ($taggroup) {
			Craft::$app->getTags()->deleteTagGroup($taggroup);
		}

		// Delete asset volume.
		$volume = Craft::$app->getVolumes()->getVolumeByHandle('reliquaryTestAssets');
		if ($volume) {
			Craft::$app->getVolumes()->deleteVolume($volume);
		}

		// Delete asset storage.
		FileHelper::removeDirectory(Craft::getAlias('@webroot/reliquary-assets'));

		// Delete test sites.
		$site = Craft::$app->getSites()->getSiteByHandle('reliquaryTestSiteA');
		if ($site) {
			Craft::$app->getSites()->deleteSite($site);
		}

		$site = Craft::$app->getSites()->getSiteByHandle('reliquaryTestSiteB');
		if ($site) {
			Craft::$app->getSites()->deleteSite($site);
		}

		return $this->asJson(['success' => true]);
	}

	/**
	 * Creates a few example groups to go along with the test data.
	 */
	public function actionCreateTestGroups()
	{
		// Search group 1
		$searchGroup = new SearchGroup([
			'siteId' => Craft::$app->getSites()->getSiteByHandle('reliquaryTestSiteA')->id,
			'handle' => 'reliquaryChannelSearchGroupA',
			'name' => 'Reliquary Channel Search Group (Site A)',
			'template' => '_reliquaryChannelSearch',
			'pageSize' => 3,
			'searchOrder' => 'title',
			'sortOrder' => 1,
		]);
		if (!Reliquary::getInstance()->searchGroups->saveGroup($searchGroup)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 1])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleChannelItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 2])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedChannelItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 3])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryCheckboxField')->id,
			'name' => 'Checkboxes - Simple Channel Items (Site A)',
			'sortOrder' => 1,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 4])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryTagField')->id,
			'name' => 'Tags - Advanced Channel Items (Site A)',
			'sortOrder' => 2,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 5])]);
		}

		// Search group 2
		$searchGroup = new SearchGroup([
			'siteId' => Craft::$app->getSites()->getSiteByHandle('reliquaryTestSiteA')->id,
			'handle' => 'reliquaryCombinedSearchGroupA',
			'name' => 'Reliquary Combined Search Group (Site A)',
			'template' => '_reliquaryCombinedSearch',
			'pageSize' => 3,
			'searchOrder' => 'title desc',
			'sortOrder' => 2,
		]);
		if (!Reliquary::getInstance()->searchGroups->saveGroup($searchGroup)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 6])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleChannelItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 7])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleStructureItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 8])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedStructureItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 9])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryCheckboxField')->id,
			'name' => 'Checkboxes - Simple Channel Items (Site A)',
			'sortOrder' => 1,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 10])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryDropdownField')->id,
			'name' => 'Dropdown - Simple Section Items (Site A)',
			'sortOrder' => 2,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 11])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryCategoryField')->id,
			'name' => 'Category - Advanced Section Items (Site A)',
			'sortOrder' => 3,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 12])]);
		}

		// Search group 3
		$searchGroup = new SearchGroup([
			'siteId' => Craft::$app->getSites()->getSiteByHandle('reliquaryTestSiteB')->id,
			'handle' => 'reliquaryStructureSearchGroupB',
			'name' => 'Reliquary Structure Search Group (Site B)',
			'template' => '_reliquaryStructureSearch',
			'pageSize' => 3,
			'searchOrder' => 'date',
			'sortOrder' => 1,
		]);
		if (!Reliquary::getInstance()->searchGroups->saveGroup($searchGroup)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 13])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleStructureItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 14])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedStructureItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 15])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryAssetField')->id,
			'name' => 'Assets - Simple Section Items (Site B)',
			'sortOrder' => 1,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 16])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryMatrixField')->id,
			'name' => 'Matrix - Advanced Section Items (Site B)',
			'sortOrder' => 2,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 17])]);
		}

		// Search group 4
		$searchGroup = new SearchGroup([
			'siteId' => Craft::$app->getSites()->getSiteByHandle('reliquaryTestSiteB')->id,
			'handle' => 'reliquaryCombinedSearchGroupB',
			'name' => 'Reliquary Combined Search Group (Site B)',
			'template' => '_reliquaryCombinedSearch',
			'pageSize' => 3,
			'searchOrder' => 'date desc',
			'sortOrder' => 2,
		]);
		if (!Reliquary::getInstance()->searchGroups->saveGroup($searchGroup)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 18])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleChannelItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 19])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('advancedChannelItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 20])]);
		}

		$searchGroupElement = new SearchGroupElement([
			'groupId' => $searchGroup->id,
			'elementType' => Entry::class,
			'elementTypeId' => Craft::$app->getSections()->getEntryTypesByHandle('simpleStructureItem')[0]->id,
		]);
		if (!Reliquary::getInstance()->searchGroupElements->saveSearchElement($searchGroupElement)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 21])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryCheckboxField')->id,
			'name' => 'Checkboxes - Simple Channel Items (Site B)',
			'sortOrder' => 1,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 22])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryTagField')->id,
			'name' => 'Tags - Advanced Channel Items (Site B)',
			'sortOrder' => 2,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 23])]);
		}

		$searchGroupFilter = new SearchGroupFilter([
			'groupId' => $searchGroup->id,
			'fieldId' => Craft::$app->getFields()->getFieldByHandle('reliquaryAssetField')->id,
			'name' => 'Assets - Simple Section Items (Site B)',
			'sortOrder' => 3,
		]);
		if (!Reliquary::getInstance()->searchGroupFilters->saveFilter($searchGroupFilter)) {
			return $this->asJson(['error' => Craft::t('reliquary', 'Test data failed ({code}).', ['code' => 24])]);
		}

		return $this->asJson(['success' => true]);
	}

	/**
	 * Deletes all test search groups.
	 */
	public function actionDeleteTestGroups()
	{
		$groups = Reliquary::getInstance()->searchGroups->getAllGroups();
		foreach ($groups as $group) {
			if (substr($group->handle, 0, 9) != 'reliquary') { // Only delete groups with handles that start with 'reliquary'.
				continue;
			}
			Reliquary::getInstance()->searchGroups->deleteGroup($group);
		}

		return $this->asJson(['success' => true]);
	}
}
