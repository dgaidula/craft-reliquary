<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\models;

use craft\base\Model;

/**
 * Indicate fields that should count for more or less with textual search.
 */
class CustomFieldWeight extends Model
{
	/**
	 * @var int|null The ID of the field to adjust the score of.
	 * Either this or the attribute can be set, not both.
	 */
	public $fieldId;

	/**
	 * @var string|null the attribute to adjust the score of.
	 * Either this or the fieldId can be set, not both.
	 */
	public $attribute;

	/**
	 * @var string|null The class of element that this custom weight applies to.
	 */
	public $elementType;

	/**
	 * @var int|null The ID of the underlying element type (EntryType, Category Group, Tag Group, Asset Volume, etc.).
	 * Related to whatever property of an element influences what kind of field layout it gets.
	 * This may be null for things like Users, which don't have underlying types.
	 */
	public $elementTypeId;

	/**
	 * @var float|null The additional value to multiply scores calculated for this property by.
	 */
	public $multiplier;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['fieldId', 'elementTypeId'], 'number', 'integerOnly' => true],
			[['multiplier'], 'number'],
			[['elementType', 'multiplier'], 'required'],
			[['elementType'], 'string', 'max' => 255],
			[['fieldId', 'attribute'], 'validateOnlyOneTarget'],
		];
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
