<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\models;

use jaredlindo\reliquary\Reliquary;
use jaredlindo\reliquary\records\SearchGroupFilter as SearchGroupFilterRecord;

use Craft;
use craft\base\Model;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

/**
 * A searchable filter that can be used on elements within a search group.
 */
class SearchGroupFilter extends Model
{
	/**
	 * @var int|null The filter's primary key.
	 */
	public $id;

	/**
	 * @var int|null The ID of the group that this is a filter for.
	 * Make sure that the field defined by this filter is actually used in this group.
	 */
	public $groupId;

	/**
	 * @var int|null The underlying field that can be searched for.
	 * Make sure that the group contains elements that actually use this field.
	 */
	public $fieldId;

	/**
	 * @var string|null The underlying attribute that can be searched for.
	 * Make sure that the group contains elements that actually have this attribute.
	 */
	public $attribute;

	/**
	 * @var string|null The unique textual key used to identify this group.
	 */
	public $handle;

	/**
	 * @var string|null The display name for the filter.
	 */
	public $name;

	/**
	 * @var int|null Indicates where this filter should be situated among the filters for its group.
	 */
	public $sortOrder;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['id', 'groupId', 'fieldId', 'sortOrder'], 'number', 'integerOnly' => true],
			[['groupId', 'name'], 'required'],
			[['handle', 'name'], 'string', 'max' => 255],
			[['fieldId', 'attribute'], 'validateOnlyOneTarget'],
			[['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
			[['handle'], UniqueValidator::class, 'targetClass' => SearchGroupFilterRecord::class, 'targetAttribute' => ['handle', 'fieldId', 'attribute']],
		];
	}

	/**
	 * Retrieves the search group this element is attached to.
	 */
	public function getGroup()
	{
		return Reliquary::getInstance()->searchGroups->getGroupById($this->groupId);
	}

	/**
	 * Retrieves the search group this element is attached to.
	 */
	public function getField()
	{
		return Craft::$app->getFields()->getFieldById($this->fieldId);
	}

	/**
	 * Ensures that only one of fieldId or attribute are set on this model.
	 */
	public function validateOnlyOneTarget($attribute, $params)
	{
		if ($this->hasErrors('fieldId') || $this->hasErrors('attribute')) { // Ignore this validation on other validation errors.
			return;
		}
		if (!(empty($this->fieldId) xor empty($this->attribute))) {
			$this->addError($attribute, 'Only one of fieldId or attribute may be set.');
		}
	}
}
