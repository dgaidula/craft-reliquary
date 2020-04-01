/*
 * This file contains functionality related to creating a basic Reliquary search
 * form without relying upon any third party libraries or tools. Much of this
 * functionality can be retrofitted or supplemented to fit with whatever tools
 * or process you would like.
 */

(function() {

	window.Reliquary = window.Reliquary || {};

	/**
	 * Creates and manages a new search form.
	 * @param {Element} containerElement The container to place form controls within.
	 * @param {string} siteUrl The base URL to send search actions to.
	 */
	function FormHandler(containerElement, siteUrl) {

		/**
		 * The element the form is built within.
		 * @type {Element}
		 */
		this.container = containerElement;

		/**
		 * The base URL of the site the form sends requests to.
		 * @type {string}
		 */
		this.baseUrl = new URL(siteUrl, window.location).href; // In case of a site URL that is just a path (such as `/es/`), construct a full absolute URL.

		console.log('Creating search form for ' + this.baseUrl);

		clearContent(this.container);
		addContent(this.container, '<p class="load-placeholder">Loading...</p>');

		window.Reliquary.getSearchGroups(this.baseUrl).then(this.buildSearchGroupInterface.bind(this), this.formError.bind(this));

		this.container.removeEventListener('click', FormHandler.prototype.clicked);
		this.container.addEventListener('click', FormHandler.prototype.clicked);
	};
	window.Reliquary.FormHandler = FormHandler;

	/**
	 * Displays an error.
	 * @param {string} error The error to display.
	 */
	FormHandler.prototype.formError = function(error) {
		console.log('Form error: ' + error);
		clearContent(this.container);
		addContent(this.container, error);
	};

	/**
	 * Handles the click event for search score explanations.
	 * @param {Event} e The event.
	 */
	FormHandler.prototype.clicked = function(e) {
		if (e.target.classList.contains('explain-search')) {
			var card = closest(e.target, '.element-card');
			var elementId = card.getAttribute('data-id');
			console.log(elementId); // TODO
			e.target.remove();
			return false;
		}
	};

	/**
	 * Builds a search group interface.
	 * @param {object} data The search group data provided back by the action.
	 */
	FormHandler.prototype.buildSearchGroupInterface = function(data) {

		// Clear current search form.
		clearContent(this.container);

		// No search groups, notify user.
		if (!data.length) {
			addContent(this.container, 'No search groups');
			return;
		}

		// Build the selection interface for the search groups.
		var addedHtml = '';
		addedHtml += '<h2>Search group to use</h2>';
		addedHtml += '<select name="group">';
		addedHtml += '<option disabled selected>Choose a search group</option>';
		data.forEach(function(item) {
			addedHtml += '<option value="' + item.id + '">' + item.name + '</option>';
		});
		addedHtml += '</select>';
		addedHtml += '<div class="rq-filter-container"></div>';
		addContent(this.container, addedHtml);

		// Allow search groups to be selected.
		var selectElement = this.container.querySelector('select');
		selectElement.addEventListener('change', function() {
			var searchGroup = selectElement.value;
			var filterContainer = this.container.querySelector('.rq-filter-container');
			clearContent(filterContainer);
			addContent(filterContainer, '<p class="load-placeholder">Loading...</p>');
			window.Reliquary.getFiltersForSearchGroup(searchGroup, this.baseUrl).then(this.buildFilterInterface.bind(this), this.formError.bind(this));
		}.bind(this));
	};

	/**
	 * Builds a filter interface.
	 * @param {object} data The filter data provided by the action.
	 */
	FormHandler.prototype.buildFilterInterface = function(data) {

		// Clear current filter container.
		var filterContainer = this.container.querySelector('.rq-filter-container');
		clearContent(filterContainer);

		var addedHtml = '';
		addedHtml += '<h2>Filters</h2>';
		addedHtml += '<h3>Search Text</h3>';
		addedHtml += createTextInput('options[0][value]');
		data.forEach(function(item) {
			addedHtml += '<h3>' + item.name + ':</h3>';
			addedHtml += '<div id="filter-options-' + item.id + '" class="rq-options-container"></div>';
		});

		if (!data.length) { // No filters, notify user.
			addedHtml += '<p>No filters</p>';
		}

		addedHtml += '<input class="submit" type="submit" disabled value="Search">';
		addedHtml += '<div class="rq-results-container"></div>';
		addContent(filterContainer, addedHtml);

		if (!data.length) { // No filters to get, allow searching immediately.
			allowSearch.apply(this);
		}

		var optionsToGet = data.length;
		function gotOptions(filterId, optionData) {
			optionsToGet -= 1;
			this.buildOptionInterface(filterId, optionData);
			if (optionsToGet <= 0) {
				allowSearch.apply(this);
			}
		}

		data.forEach(function(item) {
			window.Reliquary.getFilterOptions(item.id, null, this.baseUrl).then(gotOptions.bind(this, item.id), this.formError.bind(this));
		}.bind(this));

		function allowSearch() {
			filterContainer.querySelector('.submit').removeAttribute('disabled');
			filterContainer.querySelector('.submit').addEventListener('click', this.submitForm.bind(this));
		}
	};

	FormHandler.prototype.submitForm = function(e) {
		e.preventDefault();
		var data = new FormData(e.currentTarget.form);
		window.Reliquary.callAction('search-debug/raw-search', 'POST', data, 'json', this.baseurl).then(window.Reliquary.parseXMLHttpRequestJson).then(this.gotSearchResults.bind(this), this.formError.bind(this));
		return false;
	};

	FormHandler.prototype.gotSearchResults = function(results) {
		var resultsContainer = this.container.querySelector('.rq-results-container');
		clearContent(resultsContainer);
		var resultHtml = '';
		if (results) {
			resultHtml += '<p>Found ' + results.totalElements + ' elements in ' + Math.round(results.queryTime) + 'ms</p>';
			resultHtml += '<p>Showing elements ' + results.firstElement + ' through ' + Math.round(results.lastElement) + '</p>';
			results.elements.forEach(function(item) {
				resultHtml += '<div class="element-card" data-id="' + item.id + '">';
				resultHtml += '<p>';
				if (item.editUrl) {
					resultHtml += '<a href="' + item.editUrl + '">' + item.title + '</a>';
				} else {
					resultHtml += item.title;
				}
				resultHtml += '<br>';
				resultHtml += 'Element #' + item.id + ' (' + item.element + ')';
				if (item.searchScore) {
					resultHtml += '<br><em>Search Score: ' + item.searchScore + '</em> <a class="explain-search" href="#">Explain</a>';
				}
				resultHtml += '</p>';
				resultHtml += '</div>';
			});
		} else {
			resultHtml = '<p>Search error</p>'
		}
		resultsContainer.innerHTML = resultHtml;
	};

	/**
	 * Builds an option selector for a filter.
	 * @param {string} filterId The ID of the filter to build the interface for.
	 * @param {object} data The option data provided by the action.
	 */
	FormHandler.prototype.buildOptionInterface = function(filterId, data) {

		var optionContainer = this.container.querySelector('#filter-options-' + filterId);
		addContent(optionContainer, createHiddenInput('options[' + filterId + '][filter]', filterId));

		switch (data.type) {
			case 'single':
				addContent(optionContainer, createMultiselectInput('options[' + filterId + '][value][]', data.options, null));
				break;
			case 'multiple':
				addContent(optionContainer, createMultiselectInput('options[' + filterId + '][value][]', data.options, null));
				break;
			case 'string':
				addContent(optionContainer, createTextInput('options[' + filterId + '][value]'));
				break;
			case 'number':
				addContent(optionContainer, createTextInput('options[' + filterId + '][value]')); // TODO
				break;
			case 'date':
				addContent(optionContainer, createTextInput('options[' + filterId + '][value]')); // TODO
				break;
			case 'map':
				addContent(optionContainer, createTextInput('options[' + filterId + '][value][lat]'));
				addContent(optionContainer, createTextInput('options[' + filterId + '][value][lon]'));
				addContent(optionContainer, createTextInput('options[' + filterId + '][value][rad]'));
				break;
			default:
				this.formError('Unsupported option type ' + data.type);
				break;
		}
	};

	/**
	 * Creates a plain text input.
	 * @param {string} name The name of the input.
	 */
	function createTextInput(name) {
		return '<input name="' + name + '" type="text">';
	}

	/**
	 * Creates a hidden input.
	 * @param {string} name The name of the input.
	 * @param {string} value The value of the input.
	 */
	function createHiddenInput(name, value) {
		return '<input name="' + name + '" type="hidden" value="' + value + '">';
	}

	/**
	 * Creates a select.
	 * @param {string} name The name of the input.
	 * @param {array} items The set of items to display within the select.
	 * @param {string} initialValue Either the label to use as the initial value
	 * or the item from `items` to use. If null, will allow selection of a blank
	 * option.
	 * @param {function} transform Optional function that can be applied to an
	 * item from `items` in order to retrieve a value/label pair, by default
	 * will try to use the keys `value` and `label`.
	 */
	function createSelectInput(name, items, initialValue, transform) {
		if (!transform) {
			transform = function(item) {
				return [item.value, item.label];
			}
		}
		var options = items.map(transform);
		var hasInitialOption = options.some(function(option) {
			return option[0] == initialValue;
		});
		var builtHtml = '';
		builtHtml += '<select name="' + name + '">';
		if (!hasInitialOption && initialValue) {
			builtHtml += '<option disabled selected>' + initialValue + '</option>';
		} else if (initialValue === null) {
			builtHtml += '<option selected></option>';
		}
		options.forEach(function(option) {
			if (initialValue == option[0]) {
				builtHtml += '<option value="' + option[0] + '" selected>' + option[1] + '</option>';
			} else {
				builtHtml += '<option value="' + option[0] + '">' + option[1] + '</option>';
			}
		});
		builtHtml += '</select>';
		return builtHtml;
	}

	/**
	 * Creates a select.
	 * @param {string} name The name of the input.
	 * @param {array} items The set of items to display within the select.
	 * @param {function} transform Optional function that can be applied to an
	 * item from `items` in order to retrieve a value/label pair, by default
	 * will try to use the keys `value` and `label`.
	 */
	function createMultiselectInput(name, items, transform) {
		var input = createSelectInput(name, items, false, transform);
		return input.replace('<select ', '<select multiple ');
	}

	/**
	 * Empties an element.
	 * @param {Element} element The element to clear.
	 */
	function clearContent(element) {
		element.innerHTML = '';
	};

	/**
	 * Checks the ancestry of an element and finds the closest element that
	 * matches the provided selector.
	 * @param {Element} element The element to check the ancestry of.
	 * @param {String} selector The selector to match.
	 */
	function closest(element, selector) {
		while (element) {
			if (element.matches(selector)) {
				return element;
			}
			element = element.parentNode;
		}
		return null;
	}

	/**
	 * Adds new content to an element.
	 * @param {Element} element The element to add content to.
	 * @param {string|Node|Node[]} content The new content to add.
	 */
	function addContent(element, content) {
		if (typeof content == 'string') { // String content, convert to elements.
			var tempElement = document.createElement('div');
			tempElement.innerHTML = content;
			content = [].slice.call(tempElement.childNodes);
		}
		if (!content.length) { // Single node, convert to array.
			content = [content];
		}
		content.forEach(function(item) { // Add all nodes.
			element.appendChild(item);
		});
	};

})();