<?php
/**
 * Reliquary plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace jaredlindo\reliquary\models;

use jaredlindo\reliquary\Reliquary;
use jaredlindo\reliquary\records\SearchGroup as SearchGroupRecord;
use jaredlindo\reliquary\records\SearchGroupElement;
use jaredlindo\reliquary\records\SearchGroupFilter;

use Craft;
use craft\base\Model;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

/**
 * An individually searchable group of element types.
 */
class SearchGroup extends Model
{
	/**
	 * @var int|null The group's primary key.
	 */
	public $id;

	/**
	 * @var int|null The ID of the site the group is attached to.
	 */
	public $siteId;

	/**
	 * @var string|null The unique textual key used to identify this group.
	 */
	public $handle;

	/**
	 * @var string|null The display name of the group.
	 */
	public $name;

	/**
	 * @var string|null The template to use when rendering results from this group.
	 */
	public $template;

	/**
	 * @var int|null The number of results returned per page of the search.
	 */
	public $pageSize;

	/**
	 * @var string|null The preferred ordering of results when searching in this group.
	 */
	public $searchOrder;

	/**
	 * @var int|null Indicates where this group should be situated among the groups for its site.
	 */
	public $sortOrder;

	/**
	 * @var SearchGroupFilter[]|null Internal storage for special search group filters that should be attached to this group.
	 * Generally used for saving filters alongside a group, or providing custom ones back to the frontend.
	 */
	private $_filters = null;

	/**
	 * @var SearchGroupElement[]|null Internal storage for special search group elements that should be attached to this group.
	 * Generally used for saving elements alongside a group, or providing custom ones back to the frontend.
	 */
	private $_elements = null;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['id', 'siteId', 'pageSize', 'sortOrder'], 'number', 'integerOnly' => true],
			[['siteId', 'handle', 'name', 'template', 'pageSize', 'searchOrder'], 'required'],
			[['handle', 'name', 'searchOrder'], 'string', 'max' => 255],
			[['template'], 'string', 'max' => 1023],
			[['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
			[['handle'], UniqueValidator::class, 'targetClass' => SearchGroupRecord::class],
		];
	}

	/**
	 * Retrieves the craft Site this search group is part of.
	 */
	public function getSite()
	{
		return Craft::$app->getSites()->getSiteById($this->siteId);
	}

	/**
	 * Retrieves filters associated with this search group.
	 */
	public function getFilters(): array
	{
		if ($this->_filters !== null) {
			return $this->_filters;
		} else {
			return Reliquary::getInstance()->searchGroupFilters->getFiltersByGroup($this);
		}
	}

	/**
	 * Sets a manual override for filters provided by the
	 * SearchGroup::getFilters() function. Cleared by passing null.
	 * @param SearchGroupFilter[] $filters The filters to use.
	 */
	public function overrideFilters($filters)
	{
		$this->_filters = $filters;
	}

	/**
	 * Retrieves search group elements associated with this search group.
	 */
	public function getSearchElements(): array
	{
		if ($this->_elements !== null) {
			return $this->_elements;
		} else {
			return Reliquary::getInstance()->searchGroupElements->getSearchElementsByGroup($this);
		}
	}

	/**
	 * Sets a manual override for elements provided by the
	 * SearchGroup::getSearchElements() function. Cleared by passing null.
	 * @param SearchGroupElement[] $elements The elements to use.
	 */
	public function overrideSearchElements($elements)
	{
		$this->_elements = $elements;
	}
}
