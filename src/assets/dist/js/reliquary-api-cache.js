/*
 * Name: Reliquary Local Storage Caching
 * Description: A function that can pool duplicate AJAX requests and store their results in localStorage in order to reduce round trips.
 * Version: 1
 */

var Reliquary = Reliquary || {};
Reliquary.cache = Reliquary.cachce || {};

/*
 * Example Usage
 * --

	Say your were normally calling an internal reliquary function like this:
	>>>> Reliquary.getFiltersForSearchGroup(group);

	The expected response would be a promise from the Reliquary JS API. That promise would resolve and provide back an array.

	To take advantage of the cache we would instead do this:
	>>>> Reliquary.cache.run(Reliquary.getFiltersForSearchGroup,[group],300);

	This would return back a promise, like the original function, but depending if the function is in cache it will either make a call to Reliquary.getFiltersForSearchGroup or resolve with a known response.
	The last paramater, 300, is the time in seconds the cache is kept alive.

	Note the second paramater in this instance [group] is an array. In functions with more than one argument they would all get passed into an array. e.g. [Arg1,Arg2,Arg3,etc]

 * --
 */

Reliquary.cache.requestPool = {}; // Storage for pooled API data calls, so multiple requests get the same promise.
Reliquary.cache.options = Reliquary.cache.options || [];
Reliquary.cache.options['default_expiration_time'] = 900; // Default time, in seconds, to cache ajax requests for (900 = 15 minutes).
Reliquary.cache.options['object_marker'] = "$$obj$$"; // Marks strings that need to be parsed as an object instead of kept as a string.
Reliquary.cache.debug = false; // Will give debug information about where data is coming from during the call process.

/**
 * Generates a simple hash of a string.
 * @param str The string to hash.
 * @returns The integer hash value of this request.
 */
function hashString(str) {
	var hash = 0;
	var i = 0;
	var len = str.length;
	while (i < len) {
		hash = ((hash << 5) - hash + str.charCodeAt(i++)) << 0;
	}
	return 'reliquary.cache.request' + hash;
}

function getLocalData(hash) {
	var data = localStorage.getItem(hash);
	var result = "";

	if(data.split(Reliquary.cache.options['object_marker'])[1]){
		// Remove our object identifier
		var result = JSON.parse(data.split(Reliquary.cache.options['object_marker'])[1]);
	} else {
		// No further manipulation needed
		result = data;
	}

	/*
	if (data.dataType == 'json') { // We're expecting JSON, so try to parse the data as json, and fall back to the original data on error.
		try { result = JSON.parse(result); }
		catch (err) { }
	}
	*/
	return result;
}

function runAPICall(apiCall, apiCallParams, hash, expiration) {
	var promise =  apiCall.apply(null,apiCallParams) // Make our request.
	var nowrite = false;

	// Set expiration time in local storage.
	// We set the time here just in case the response is
	try {
		localStorage.setItem(hash + '_expiration', expiration);
	} catch (err) {
		nowrite = true;
	}

	// When the request is done, the first thing we should do is store the resulting data in local storage.
	promise.then(function (data) {

		// Stringify objects
		// Mostly so we ensure any json data is turned into string first before storing.
		if (typeof data === "object") {
			data = Reliquary.cache.options['object_marker'] +  JSON.stringify(data);
		}

		if (!nowrite) {
			try {
				localStorage.setItem(hash, data); // Store in local storage.
			} catch (err) { // Couldn't store item, but expiration was stored, remove the expiration
				localStorage.removeItem(hash + '_expiration');
			}
		}
	});

	// Any additional data use will happen after being stored locally
	return promise;
}

/**
 * Removes any old cached instances in local storage, including the expiration time item.
 *
 * @param hash Hash or Key in local storage.
 */
function removeLocalStorage(hash){
	localStorage.removeItem(hash);
	localStorage.removeItem(hash + '__expiration');
}

/**
 * Returns back cached version of a request if fresh, performs call if stale.
 * Normally used as a replacement to the direct javascript API call
 *
 * @param function apiCall The javascript API to call
 * @param array apiCallParams The paramaters for the call
 * @param expiration An expiration time for data when stored in localstorage, in seconds.
 * @returns Promise
 */
Reliquary.cache.run = function(apiCall,apiCallParams,expiration){

	// Set a default expiration time.
	var expireTime = Math.floor((Date.now() / 1000) + (expiration || Reliquary.cache.options['default_expiration_time'])); // Apply default time before re-grabbing data, if none is provided.

	// Generate the hash for the request, without the extra data.
	var datahash = hashString(JSON.stringify(arguments));

	// If local storage is not enabled, run a standard ajax request.
	if (!window.localStorage || !window.sessionStorage || !localStorage) {
		// Call the api
		if(Reliquary.cache.debug) {
			console.warn("Local storage is not enabled.");
		}
		return apiCall.apply(null,apiCallParams);
	}

	// If local storage is supported but quota is 0 (Private mode safari).
	// https://www.reddit.com/r/javascript/comments/2z06aq/local_storage_is_not_supported_with_safari_in/
	try {
		localStorage.setItem('localStorageTest', '1');
		localStorage.removeItem('localStorageTest');
	} catch (err) {
		if(Reliquary.cache.debug) {
			console.warn("Local storage can not be written to.");
		}
		return apiCall.apply(null,apiCallParams);
	}

	// Check if the item in storage is expired, and unset the local storage (and the short term cache just in case).
	if (Math.floor((Date.now() / 1000)) > localStorage.getItem(datahash + '_expiration')) {
		// Remove from local storage
		removeLocalStorage(datahash);

		// Remove from requestPool
		Reliquary.cache.requestPool[datahash] = null;
	}

	// If we are allowing data cache, check the short term request cache.
	if (Reliquary.cache.requestPool[datahash]) {
		if(Reliquary.cache.debug) {
			console.warn("Data returned from request pool");
		}
		return Reliquary.cache.requestPool[datahash];
	}

	var promise; // The promise object backing the promise this function returns.

	if (localStorage.getItem(datahash)) { // If this is already exists in the local storage, retrieve the data.
		promise = new Promise(function(resolve,reject){});
		if(Reliquary.cache.debug) {
			console.warn("Using data from local storage");
		}
		promise = Promise.resolve(getLocalData(datahash));
	} else { // Otherwise make a new api call
		if(Reliquary.cache.debug) {
			console.warn("Calling the api function",apiCall,apiCallParams);
		}
		promise = runAPICall(apiCall,apiCallParams,datahash,expireTime);
	}

	Reliquary.cache.requestPool[datahash] = promise;  // If we should cache the data, store the request in our request pool.

	// Return back promise
	return promise;
};

/**
 * Returns back cached version of a request if fresh, performs call if stale, for all groups/filters/options.
 * Only call this if you want to prime the cache for an entire site.
 * This can be a costly set of calls depending on the quantity of search groups, filters, or options.
 *
 * @param expiration An expiration time for data when stored in localstorage, in seconds.
 * @returns Promise
 */
Reliquary.cache.primeCache = function(expiration){

	// Get all search groups, returns a promise
	Reliquary.cache.run(Reliquary.getSearchGroups,[])
		.then(function(group){
			// Get All Filters
			for(var i = 0;i<group.length;i++){
				Reliquary.cache.run(Reliquary.getFiltersForSearchGroup,[group[i].id],expiration)
					.then(function(filters) {
						// Get all options per filter
						for(var j = 0;j<filters.length;j++){
							Reliquary.cache.run(Reliquary.getAllOptionsByFilter,[filters[j].id],expiration)
							.catch(catchFunction)
							.finally(finallyFunction);
						}
					})
					.catch(catchFunction)
					.finally(finallyFunction);
			}
		})
		.catch(catchFunction)
		.finally(finallyFunction);

	function catchFunction() {
		console.log("Catch Error");
	};

	function finallyFunction() {

	};

}

/**
 * Clears all Reliquary cache items out of localstorage
 *
 */
Reliquary.cache.clearCache = function(){
	for (var key in localStorage){
		if(key.indexOf("reliquary.cache") > -1){
			// Remove object
			localStorage.removeItem(key);
		}
	}
}

/**
 * Clears all Expired Reliquary cache items out of localstorage
 *
 */
Reliquary.cache.clearExpiredCache = function(){
	for (var key in localStorage){
		if(key.indexOf("reliquary.cache") > -1){
			// Is a reliquary cache item
			// Check if this is the key that determines the expiration date
			if (key.indexOf("_expiration") > -1) {
				// Determine if it is fresh
				var expireTime = localStorage.getItem(key);
				if(expireTime < Math.floor(Date.now() / 1000)) {
					// Attempt to remove this key and the key that contains the data
					localStorage.removeItem(key);
					localStorage.removeItem(key.split("_expiration")[0]);
				}
			}
		}
	}
}