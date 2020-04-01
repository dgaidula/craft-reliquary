/*
 * This file contains a thin API layer above some of the Reliquary actions
 * without relying upon any third party libraries or tools.
 *
 * If the browser does not support Promises, it will need a polyfill.
 * If the browser does not support FormData.entries() it will need a polyfill.
 */

(function() {

	window.Reliquary = window.Reliquary || {};

	/**
	 * Retrieves search groups available on the site.
	 * @param {string} urlOverride An optional base URL of the site to make a
	 * request to, defaults to the current site.
	 * @returns {Promise<object>} A promise that will resolve with the search
	 * group data, or reject with the error message.
	 */
	window.Reliquary.getSearchGroups = function(urlOverride) {
		return window.Reliquary.callAction('get-search-groups', 'GET', null, 'json', urlOverride).then(window.Reliquary.parseXMLHttpRequestJson);
	};

	/**
	 * Retrieves filters available for a search group.
	 * @param {string} searchGroup The id of the search group.
	 * @param {string} urlOverride An optional base URL of the site to make a
	 * request to, defaults to the current site.
	 * @returns {Promise<object>} A promise that will resolve with the filter
	 * data, or reject with the error message.
	 */
	window.Reliquary.getFiltersForSearchGroup = function(searchGroup, urlOverride) {
		return window.Reliquary.callAction('get-filters', 'GET', {group: searchGroup}, 'json', urlOverride).then(window.Reliquary.parseXMLHttpRequestJson);
	};

	/**
	 * Retrieves options and meta information for a filter.
	 * @param {string} filter The id of the filter.
	 * @param hint The hint information to provide back to the option system,
	 * depends on the type of filter.
	 * @param {string} urlOverride An optional base URL of the site to make a
	 * request to, defaults to the current site.
	 * @returns {Promise<object>} A promise that will resolve with the option
	 * data, or reject with the error message.
	 */
	window.Reliquary.getFilterOptions = function(filter, hint, urlOverride) {
		return window.Reliquary.callAction('get-filter-options', 'GET', {filter: filter, hint: hint}, 'json', urlOverride).then(window.Reliquary.parseXMLHttpRequestJson);
	};

	/**
	 * An ease of use function that iterates over all pages of options available
	 * for a filter. Not ideal for large data sets, and may not work for every
	 * kind of filter (depends on the field/adapter).
	 * @param {string} filter The id of the filter.
	 * @param {string} urlOverride An optional base URL of the site to make a
	 * request to, defaults to the current site.
	 * @returns {Promise<object>} A promise that will resolve with the option
	 * data, or reject with the error message.
	 */
	window.Reliquary.getAllOptionsByFilter = function(filter, urlOverride) {
		var promiseResolve = null;
		var returnPromise = new Promise(function(resolve,reject){
			promiseResolve = resolve;
		});
		var allData = [];
		var page = 1;
		var totalItemsSeen = 0;

		// Call recursive get filter options
		recursiveGet();

		function recursiveGet(){
			// Define our call
			var returnPromise = window.Reliquary.callAction('get-filter-options', 'GET', {filter: filter, hint: page}, 'json', urlOverride).then(window.Reliquary.parseXMLHttpRequestJson);
			returnPromise.then(function(data){
				// Increment Page
				page++;

				// Append new data to existing data
				allData = allData.concat(data.options);

				// Update total items
				totalItemsSeen += data.options.length;

				if(totalItemsSeen < data.total){
					// We still have to pull more stuff run again.
					recursiveGet();
				} else {
					// Manipulate
					data.options = allData;
					data.partial = false;
					data.hint = "";

					// Resolve
					promiseResolve(data);
				}
			});
		}
		return returnPromise;
	};


	/**
	 * Performs a search by submitting a search group and applicable filters.
	 * @param data The data to send to the search.
	 * @param {string} urlOverride An optional base URL of the site to make a
	 * request to, defaults to the current site.
	 * @returns {Promise<string>} A promise that will resolve with the rendered
	 * search result/template data, or reject with the error message.
	 */
	window.Reliquary.doSearch = function(data, urlOverride) {
		return window.Reliquary.callAction('search', 'POST', data, 'html', urlOverride).then(window.Reliquary.parseXMLHttpRequestHtml);
	};

	/**
	 * Retrieves a search parameter object using what is in the current URL.
	 * @param {string} key The key within the URL that will contain the search
	 * value. Defaults to 'searchCriteria' if not provided.
	 * @returns {object} A decoded search object from the current URL, or null
	 * if one does not exist or there was an error parsing it.
	 */
	window.Reliquary.getSearchParamsFromUrl = function(key) {
		var data = getAllParametersFromQuery(window.location.search.substring(1));
		return window.Reliquary.decodeSearchParams(data[key || 'searchCriteria']);
	};

	/**
	 * Stores a search query object within the current URL, using the specified
	 * key.
	 * @param {object} obj The object representing the search parameters, leave
	 * blank/null in order to clear the search parameter.
	 * @param {string} key The key to store the value with in the URL. Defaults
	 * to 'searchCriteria' if not provided.
	 * @param {bool} history How to handle manipulation of the history state.
	 * Pass nothing/false to only modify the current URL, or true to perform
	 * a pushState to the new URL.
	 */
	window.Reliquary.setSearchParamsToUrl = function(obj, key, history) {
		var data = getAllParametersFromQuery(window.location.search.substring(1));
		key = key || 'searchCriteria';
		if (obj) {
			data[key] = window.Reliquary.encodeSearchParams(obj);
		} else {
			delete data[key];
		}
		var search = window.Reliquary.createQueryStringFromData(data);
		if (search) {
			search = '?' + search;
		}
		var url = window.location.protocol + '//' + window.location.host + window.location.pathname + search + window.location.hash
		if (history) {
			window.history.pushState({}, '', url);
		} else {
			window.history.replaceState({}, '', url);
		}
	};

	/**
	 * Creates a query string from an object, mapping its values to its
	 * property names.
	 * @param {object} obj The object to encode as a query string.
	 * @returns {string} The encoded object.
	 */
	window.Reliquary.createQueryStringFromData = function(obj) {
		var data = [];
		for (var param in obj) {
			data.push(encodeURIComponent(param) + '=' + encodeURIComponent(obj[param]));
		}
		return data.join('&');
	};

	/**
	 * Encodes a search parameter object. Performs JSON encoding and then base64
	 * encoding.
	 * @param {object} obj The search parameter object to encode.
	 * @returns {string} The newly encoded data.
	 */
	window.Reliquary.encodeSearchParams = function(obj) {
		// Remove padding from base64 object.
		// This is to prevent cases where it ends up in the URL and URI encoding will turn the padding into %3D.
		// When converting this back to an object it's easy to forget to URI decode first before atob
		var output = btoa(JSON.stringify(obj));
		if (output.indexOf("=") > 0) {
			output = output.split("=")[0];
		}
		return output;
	};

	/**
	 * Decodes an encoded search parameter string. Performs a base64 decode and
	 * then parses the underlying JSON content.
	 * @param {string} str The string to decode search parameters from.
	 * @returns {object} The resulting parsed content, or null on error.
	 */
	window.Reliquary.decodeSearchParams = function(str) {
		try {
			var data = atob(str);
			data = JSON.parse(data);
			return data;
		} catch (e) {
			return null;
		}
	};

	/**
	 * Makes an AJAX request.
	 * @param {string} action The action being performed.
	 * @param {string} method The method to use.
	 * @param {object} data The data to send, will be converted to JSON.
	 * @param {string} expects The type of data expected (json (default),
	 * html, text).
	 * @param {string} urlOverride A base url override, if not calling an action
	 * on the current site.
	 * @returns {Promise<ProgressEvent>} A promise that will resolve with the
	 * XMLHttpRequest finished event, or reject with the error event.
	 */
	window.Reliquary.callAction = function(action, method, data, expects, urlOverride) {
		return new Promise(function(resolve, reject) {
			var request = new XMLHttpRequest();
			var types = {
				'html': 'text/html',
				'text': 'text/plain',
				'json': 'application/json',
			};
			var url = (urlOverride || '/') + '?action=reliquary/';
			if (action.indexOf('/') > -1) {
				url += action;
			} else {
				url += 'search/' + action;
			}
			if (data instanceof FormData) {
				data = convertFormData(data);
			}
			if (data && method == 'GET') {
				url += '&' + serializeObject(encodeObject(data));
			}
			request.open(method, url);
			request.setRequestHeader('Accept', types[expects] || 'application/json')
			request.onerror = reject;
			request.onload = resolve;
			if (data && method == 'POST') {
				request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				request.send(serializeObject(encodeObject(data)));
			} else {
				request.send();
			}
		});
	};

	/**
	 * Retrieves JSON data from the XMLHttpRequest and attempts to parse the
	 * data.
	 * @param {ProgressEvent} e The event provided back after the
	 * XMLHttpRequest finishes.
	 * @returns {Promise<object>} A promise that will resolve with the parsed
	 * object, or reject with the error message.
	 */
	window.Reliquary.parseXMLHttpRequestJson = function(e) {
		return new Promise(function(resolve, reject) {
			var data = JSON.parse(e.target.responseText); // Parse JSON retrieved.
			if (data.error) { // Reject promise with the error instead.
				reject(data.error);
				return;
			}
			resolve(data); // Resolve with parsed data.
		});
	}

	/**
	 * Retrieves HTML data from the XMLHttpRequest object.
	 * @param {ProgressEvent} e The event provided back after the
	 * XMLHttpRequest finishes.
	 * @returns {string} The raw response text of the request.
	 */
	window.Reliquary.parseXMLHttpRequestHtml = function(e) {
		return e.target.responseText;
	}

	/**
	 * Converts a FormData object into a plain JavaScript object.
	 * @param {FormData} data
	 * @returns {object} The plain javascript object containing the same
	 * properties stored in the FormData object.
	 */
	function convertFormData(data) {
		var newData = {};
		var iter = data.entries();
		while (true) {
			var nextItem = iter.next();
			if (nextItem.done) {
				break;
			}
			newData[nextItem.value[0]] = nextItem.value[1];
		}
		return newData;
	}

	/**
	 * Collapse any complex object down into a simple name/value array as used
	 * in application/x-www-form-urlencoded data.
	 * @param obj Any object to flatten to a single array of nested values.
	 * @param {string} prefix The prefix to use when encoding object values.
	 * @returns {array} An array that contains name value pairs of every
	 * nested property of the original object, each in order and keyed in a
	 * manner that can be parsed by PHP and Craft.
	 */
	function encodeObject(obj, prefix) {
		prefix = prefix || '';
		var arr = [];
		if (typeof obj == 'array') {
			obj.forEach(function(item, index) {
				arr = arr.concat(encodeObject(item, prefix ? (prefix + '[' + index + ']') : index));
			});
		} else if (typeof obj == 'object') { // Complex object, return each item.
			for (var key in obj) {
				arr = arr.concat(encodeObject(obj[key], prefix ? (prefix + '[' + key + ']') : key));
			}
		} else { // Plain value, return single item.
			arr.push({
				name: prefix,
				value: obj,
			});
		}
		return arr;
	}

	/**
	 * URI encodes all of the elements of the provided object and returns a
	 * string with every value.
	 * @param {array} arr An encoded array, of name value pairs, as provided by
	 * encodeObject().
	 * @returns {string} The serialized string, ready to be sent via AJAX.
	 */
	function serializeObject(arr) {
		var elements = [];
		arr.forEach(function(item) {
			elements.push(encodeURIComponent(item.name) + '=' + encodeURIComponent(item.value));
		});
		return elements.join('&');
	}

	/**
	 * Parses a query string from a query string and returns an object with
	 * properties mapped to the provided data.
	 * @param {string} query The query string to parse parameters from.
	 * @returns {object} An object containing all of the keys as properties, and
	 * the associated values set to those properties.
	 */
	function getAllParametersFromQuery(query) {
		var search = query.split('&'); // Splits into key-value pairs.
		var data = {};
		for (var i = 0; i < search.length; i += 1) {
			if (search[i].length == 0) { // Blank, do nothing.
				continue;
			}
			var pair = search[i].split('=');
			if (typeof data[pair[0]] === 'undefined') { // If first entry with this name, store value as string.
				data[pair[0]] = decodeURIComponent(pair[1]);
			} else if (typeof data[pair[0]] === 'string') { // If second entry with this name, pair old and new data as array.
				var arr = [data[pair[0]], decodeURIComponent(pair[1])];
				data[pair[0]] = arr;
			} else { // If third or later entry with this name, add to existing array.
				data[pair[0]].push(decodeURIComponent(pair[1]));
			}
		}
		return data;
	};

})();
