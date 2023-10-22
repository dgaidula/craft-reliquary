<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\models;

use jaredlindo\reliquary\Reliquary;

use craft\base\Model;

/**
 * An element type that may be searched for within a group.
 */
class SearchGroupElement extends Model
{
	/**
	 * @var int|null The element's primary key.
	 */
	public $id;

	/**
	 * @var int|null The ID of the group that this is a filter for.
	 */
	public $groupId;

	/**
	 * @var string|null The class of the associated element type.
	 */
	public $elementType;

	/**
	 * @var int|null The ID of the underlying element type (EntryType, Category Group, Tag Group, Asset Volume, etc.).
	 * Related to whatever property of an element influences what kind of field layout it gets.
	 * This may be null for things like Users, which don't have underlying types.
	 */
	public $elementTypeId;

	/**
	 * @var int|null Indicates where this element should be situated among the elements for its group.
	 * This is mostly relevant within search groups that are sorted by Craft's default behavior.
	 */
	public $sortOrder;

	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['id', 'groupId', 'elementTypeId', 'sortOrder'], 'number', 'integerOnly' => true],
			[['groupId', 'elementType'], 'required'],
			[['elementType'], 'string', 'max' => 255],
		];
	}

	/**
	 * Retrieves the search group this element is attached to.
	 */
	public function getGroup()
	{
		return Reliquary::getInstance()->searchGroups->getGroupById($this->groupId);
	}
}
