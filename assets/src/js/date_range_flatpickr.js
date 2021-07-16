(function ($) {

	/* ======== Date Range (FlatPickr) ======== */

	const DB_DATE_FORMAT = "Y-m-d";

	$(document).on('facetwp-loaded', function () {

		$('.facetwp-type-date_range_flatpickr').each(function(){
			var facet_element = $(this);
			var facet_name = facet_element.attr('data-name');

			var flatpickr_defaults_options = {
				altInput: true,
				altInputClass: 'flatpickr-alt',
				altFormat: FWP.settings[facet_name].format,
				disableMobile: false,
				locale: FWP_JSON.date_range_flatpickr.locale,
				// On disable les dates antérieures à aujourd'hui
				minDate : "today",
				mode : "range",
				onChange : function (dateObj, dateStr, instance) {
					// Quand la date_début et la date_fin sont renseignés
					if (dateObj.length > 1) {
						facet_element.find('.facetwp-date-min').attr('value', flatpickr.formatDate(dateObj[0], DB_DATE_FORMAT));
						facet_element.find('.facetwp-date-max').attr('value', flatpickr.formatDate(dateObj[1], DB_DATE_FORMAT));
						// On lance la recherche ajax si on a activé le refresh auto
						if (FWP.auto_refresh) {
							FWP.autoload();
						}
					}
				},
				onReady : function (dateObj, dateStr, instance) {
					var datefin = facet_element.find('.facetwp-date-max').attr('value');
					// init calendar
					if (datefin != '') {
						datefin = flatpickr.parseDate(datefin, DB_DATE_FORMAT);
						dateObj.push(datefin);
						instance.setDate(dateObj);
					}
					// Comportement normal
					var clearBtn = '<div class="flatpickr-clear">' + FWP_JSON.date_range_flatpickr.clearText + '</div>';
					$(clearBtn).on('click', function () {
						instance.clear();
						facet_element.find('.facetwp-date-max').attr('value', '');
						instance.close();
						if (FWP.auto_refresh) {
							FWP.autoload();
						}
					}).appendTo($(instance.calendarContainer));
				},
			};

			facet_element.find('.facetwp-date-min:not(".ready, .flatpickr-alt")').each(function () {
				var opts = FWP.hooks.applyFilters('facetwp/set_options/date_range_flatpickr', flatpickr_defaults_options, {
					'facet_name': facet_name,
					'element': $(this)
				});
				new flatpickr(this, opts);
				$(this).addClass('ready');
			});
		});
	});

	FWP.hooks.addFilter('facetwp/selections/date_range_flatpickr', function (output, params) {
		
		var out = '';
		var selected_values = params.selected_values;
		var facet_name = $(params.el[0]).attr('data-name');
		var format = FWP.settings[facet_name].format;
		
		if ('' !== selected_values[0]) {
			out += ' ' + flatpickr.formatDate(flatpickr.parseDate(selected_values[0], DB_DATE_FORMAT), format);
		}
		if ('' !== selected_values[1] && selected_values[0] !== selected_values[1]) {
			out += ' ' + flatpickr.l10ns[FWP_JSON.date_range_flatpickr.locale].rangeSeparator + ' ' + flatpickr.formatDate(flatpickr.parseDate(selected_values[1], DB_DATE_FORMAT), format);
		}

		return out;
	});

	FWP.hooks.addAction('facetwp/refresh/date_range_flatpickr', function (element, facet_name) {		
		var min = element.find('.facetwp-date-min').val() || '';
		var max = element.find('.facetwp-date-max').val() || '';
		FWP.facets[facet_name] = ('' !== min || '' !== max) ? [min, max] : [];
	});

})(jQuery);
