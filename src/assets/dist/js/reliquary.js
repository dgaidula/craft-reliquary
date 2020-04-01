window.Reliquary = window.Reliquary || {};

window.Reliquary.GroupEditor = Garnish.Base.extend({
	init: function(settings) {

		var _self = this;

		var newId = $('.rq-card').length + 1;

		// Allow filters to be sorted via drag and drop.
		var elementDragSort = new Garnish.DragSort($('#elements .rq-card'), {
			handle: '.move',
			axis: Garnish.Y_AXIS,
		});

		// Allow filters to be sorted via drag and drop.
		var filterDragSort = new Garnish.DragSort($('#filters .rq-card'), {
			handle: '.move',
			axis: Garnish.Y_AXIS,
		});

		// Replace entry placeholder data.
		$('#elements .rq-card').each(function() {
			var card = $(this);
			var typeClass = card.data('type-class');
			var typeId = card.data('type-id');
			var type = Reliquary.elements[typeClass];
			var subType = type['subtypes'][typeId];
			if (subType) {
				card.find('.rq-card-head').text(subType.name);
				card.find('.rq-card-subhead').text(type.name);
				card.find('.rq-card-handle').text(typeClass);
			} else {
				card.find('.rq-card-head').text('Missing/Deleted Type');
				card.find('.rq-card-head').addClass('error');
				card.find('.rq-card-head').attr('data-icon', 'alert');
			}
		});

		// Allow search elemenets to be deleted.
		$('#elements').on('click', '.icon.delete', function() {
			$(this).closest('.rq-card').remove();

			// Check currently selected filters against ones that are available
			// now that a search element has been removed.
			// Remove any filters that no longer apply to any of the elements.
			var availableFilters = _self.getExistingFilters();
			$('#filters .rq-card').each(function() {
				var card = $(this);
				var filterId = card.data('field-id') || card.data('attribute');
				if (!availableFilters[filterId]) {
					card.remove();
				}
			});
		});

		// Search element add modal.
		$('#elements + .btn.add').on('click', function() {
			// Determine existing elements.
			var existingElements = {};
			$('#elements .rq-card').each(function() {
				var card = $(this);
				var typeClass = card.data('type-class');
				if (!existingElements[typeClass]) {
					existingElements[typeClass] = {};
				}
				existingElements[typeClass][card.data('type-id')] = true;
			});

			// Build base element modal.
			var modalFrame = $('<div class="modal rq-selector-modal">');
			var modalContents = $('<div class="body" style="overflow: auto; height: 100%;">').appendTo(modalFrame);
			$('<div class="footer"><div class="buttons right"><div class="btn">Cancel</div></div></div>').appendTo(modalFrame);
			var modal = new Garnish.Modal(modalFrame);
			var dataTable = $('<table class="data fullwidth">');
			dataTable.append($('<thead>'));
			var tableContents = $('<tbody>').appendTo(dataTable);
			var tableLabels = $('<tr>').appendTo(dataTable.find('thead'));
			tableLabels.append($('<th scope="col">Type Name</th>'));
			tableLabels.append($('<th scope="col">Element</th>'));
			tableLabels.append($('<th scope="col">Class</th>'));
			tableLabels.append($('<th>'));

			// Place data in table.
			for (elementClass in Reliquary.elements) {
				var elementDef = Reliquary.elements[elementClass];
				for (subtypeId in elementDef.subtypes) {
					var subtype = elementDef.subtypes[subtypeId];
					var dataRow = $('<tr>');
					if (existingElements[elementClass] && existingElements[elementClass][subtypeId]) {
						dataRow.addClass('disabled');
					}
					dataRow.attr('data-type-class', elementClass);
					dataRow.attr('data-type-id', subtypeId);
					dataRow.append($('<td>').text(subtype.name));
					dataRow.append($('<td>').text(elementDef.name));
					dataRow.append($('<td class="code">').text(elementClass));
					dataRow.append($('<td><div class="btn" data-icon="check">Make searchable</div></td>'));
					tableContents.append(dataRow);
				}
			}
			modalContents.append(dataTable);

			// Allow selecting new elements.
			dataTable.on('click', '.btn', function() {
				var row = $(this).closest('tr');
				if (row.hasClass('disabled')) { // Don't allow selecting disabled rows.
					return;
				}

				// Create an element card for the newly selected search element.
				var card = $('<div class="rq-card">');
				var elementClass = row.attr('data-type-class');
				var subtypeId = row.attr('data-type-id');
				card.attr('data-type-class', elementClass);
				card.attr('data-type-id', subtypeId);

				// Add controls to the card.
				card.append('<div class="rq-card-bar">'
					+ '<a class="delete icon" title="Delete" role="button"></a>'
					+ '<a class="move icon" title="Reorder" role="button"></a>'
					+ '</div>');

				// Assign fields to the card.
				var newIndex = 'new' + newId;
				newId += 1;
				$('<input type="hidden">').attr('name', 'elements[' + newIndex + '][type]').val(elementClass).appendTo(card);
				$('<input type="hidden">').attr('name', 'elements[' + newIndex + '][typeId]').val(subtypeId).appendTo(card);

				// Add contents to the card.
				$('<h2 class="rq-card-head">').text(row.find('td:eq(0)').text()).appendTo(card);
				$('<div>')
					.append(
						$('<span class="rq-card-subhead">').text(row.find('td:eq(1)').text())
					)
					.append(
						$('<span class="rq-card-handle">').text(elementClass)
					)
					.appendTo(card);

				$('#elements').append(card);
				elementDragSort.addItems(card);

				modal.hide();
			});

			// Allow modal to be closed.
			modalFrame.find('.footer .buttons.right .btn').on('click', modal.hide.bind(modal));

			// Destroy modal contents when fully closed.
			modal.on('fadeOut', function() {
				modal.$shade.remove();
				modal.destroy();
			});
		});

		// Allow search filters to be deleted.
		$('#filters').on('click', '.icon.delete', function() {
			$(this).closest('.rq-card').remove();
		});

		// Filters add modal.
		$('#filters + .btn.add').on('click', function() {
			// Determine existing filters.
			var existingFilters = {};
			$('#filters .rq-card').each(function() {
				var card = $(this);
				var filterId = card.data('field-id') || card.data('attribute');
				existingFilters[filterId] = true;
			});

			// Determine available filters.
			var availableFilters = _self.getExistingFilters();

			// Build base element modal.
			var modalFrame = $('<div class="modal rq-selector-modal">');
			var modalContents = $('<div class="body" style="overflow: auto; height: 100%;">').appendTo(modalFrame);
			$('<div class="footer"><div class="buttons right"><div class="btn">Cancel</div></div></div>').appendTo(modalFrame);
			var modal = new Garnish.Modal(modalFrame);

			// Allow modal to be closed.
			modalFrame.find('.footer .buttons.right .btn').on('click', modal.hide.bind(modal));

			// Destroy modal contents when fully closed.
			modal.on('fadeOut', function() {
				modal.$shade.remove();
				modal.destroy();
			});

			// Show a message if no filters/attributes are available.
			var itemCount = 0;
			for (var key in availableFilters) {
				itemCount += 1;
			};
			if (itemCount == 0) {
				modalContents.append($('<div>No fields or attributes available for the selected element types.</div>'));
				return;
			}

			// Build data table for modal.
			var dataTable = $('<table class="data fullwidth">');
			dataTable.append($('<thead>'));
			var tableContents = $('<tbody>').appendTo(dataTable);
			var tableLabels = $('<tr>').appendTo(dataTable.find('thead'));
			tableLabels.append($('<th scope="col">Filter</th>'));
			tableLabels.append($('<th scope="col">Type</th>'));
			tableLabels.append($('<th>'));

			// Place data in table.
			for (var key in availableFilters) {
				var filter = availableFilters[key];
				var dataRow = $('<tr>');
				if (existingFilters[key]) {
					dataRow.addClass('disabled');
				}
				if (isNaN(parseInt(key))) {
					dataRow.attr('data-attribute', key);
				} else {
					dataRow.attr('data-field-id', key);
				}
				dataRow.append($('<td>').text(filter.name));
				dataRow.append($('<td>').text(filter.type));
				dataRow.append($('<td><div class="btn" data-icon="check">Add filter</div></td>'));
				tableContents.append(dataRow);
			}
			modalContents.append(dataTable);

			// Allow selecting new filters.
			dataTable.on('click', '.btn', function() {
				var row = $(this).closest('tr');
				if (row.hasClass('disabled')) { // Don't allow selecting disabled rows.
					return;
				}

				// Create a filter card for the newly selected search filter.
				var card = $('<div class="rq-card with-bar">');
				var fieldId = row.attr('data-field-id');
				var attribute = row.attr('data-attribute');
				card.attr('data-field-id', fieldId);
				card.attr('data-attribute', attribute);

				// Add controls to the card.
				card.append('<div class="rq-card-bar">'
					+ '<a class="delete icon" title="Delete" role="button"></a>'
					+ '<a class="move icon" title="Reorder" role="button"></a>'
					+ '</div>');

				// Assign fields to the card.
				var newIndex = 'new' + newId;
				newId += 1;
				$('<input type="hidden">').attr('name', 'filters[' + newIndex + '][fieldId]').val(fieldId).appendTo(card);
				$('<input type="hidden">').attr('name', 'filters[' + newIndex + '][attribute]').val(attribute).appendTo(card);

				// Add contents to the card.
				var titlePrefix = '';
				if (fieldId) {
					titlePrefix = 'Field: ';
				} else {
					titlePrefix = 'Element Attribute: ';

				}
				$('<h2 class="rq-card-head">').text(titlePrefix + row.find('td:eq(0)').text()).appendTo(card);
				Craft.ui.createTextField({
					label: 'Filter Name',
					instructions: 'The name used to represent the filter on the frontend.',
					id: 'filters-' + newIndex + '-name',
					name: 'filters[' + newIndex + '][name]',
					value: '',
					required: true,
					fieldClass: 'first',
				}).appendTo(card);
				Craft.ui.createTextField({
					label: 'Filter Handle',
					instructions: 'The key used to reference this filter.',
					id: 'filters-' + newIndex + '-handle',
					name: 'filters[' + newIndex + '][handle]',
					value: '',
					required: true,
				}).appendTo(card);

				$('#filters').append(card);
				filterDragSort.addItems(card);
				new Craft.HandleGenerator('#filters-' + newIndex + '-name', '#filters-' + newIndex + '-handle');

				modal.hide();
			});
		});
	},
	getExistingFilters: function() {
		var availableAttributes = {};
		var availableFields = {};
		$('#elements .rq-card').each(function() {
			var card = $(this);
			var elementType = Reliquary.elements[card.data('type-class')];
			for (var attributeIdx in elementType.attributes) {
				var attribute = elementType.attributes[attributeIdx];
				availableAttributes[attribute.handle] = {
					name: attribute.name,
					type: 'Element Attribute',
				}
			}
			var typeId = card.data('type-id');
			var layoutId = elementType.subtypes[typeId].layoutId;
			if (!layoutId) { // No layout on this element type.
				return;
			}
			var layout = Reliquary.fieldLayouts[layoutId];
			layout.forEach(function(fieldId) {
				var field = Reliquary.fields[fieldId];
				if (field.type != null) {
					availableFields[fieldId] = {
						name: field.name,
						type: field.type,
					};
				}
			});
		});
		for (var key in availableFields) {
			availableAttributes[key] = availableFields[key];
		}
		return availableAttributes;
	}
}, {

});