var FWP_LEAFLET_MAP = FWP_LEAFLET_MAP || {};

(function ($) {

	FWP_LEAFLET_MAP.markersArray = [];
	FWP_LEAFLET_MAP.markerLookup = {};
	FWP_LEAFLET_MAP.is_filtering = false;
	FWP_LEAFLET_MAP.is_zooming = false;

	// Get markers for a given post ID
	FWP_LEAFLET_MAP.get_post_markers = function (post_id) {
		var output = [];
		if ('undefined' !== typeof FWP_LEAFLET_MAP.markerLookup[post_id]) {
			var arrayOfIndexes = FWP_LEAFLET_MAP.markerLookup[post_id];
			for (var i = 0; i < arrayOfIndexes.length; i++) {
				var index = FWP_LEAFLET_MAP.markerLookup[post_id][i];
				output.push(FWP_LEAFLET_MAP.markersArray[index]);
			}
		}
		return output;
	}

	FWP.hooks.addAction('facetwp/refresh/leaflet_map', function ($this, facet_name) {
		var selected_values = [];

		if (FWP_LEAFLET_MAP.is_filtering) {
			selected_values = FWP_LEAFLET_MAP.map.getBounds().toUrlValue().split(',');
		}

		FWP.facets[facet_name] = selected_values;
		FWP.frozen_facets[facet_name] = 'hard';
	});

	FWP.hooks.addAction('facetwp/reset', function () {
		$.each(FWP.facet_type, function (type, name) {
			if ('leaflet_map' === type) {
				FWP.frozen_facets[name] = 'hard';
			}
		});
	});

	FWP.hooks.addFilter('facetwp/selections/leaflet_map', function (label, params) {
		return FWP_JSON['leaflet_map']['resetText'];
	});

	function do_refresh() {
		if (FWP_LEAFLET_MAP.is_filtering && !FWP_LEAFLET_MAP.is_zooming) {
			FWP.autoload();
		}

		FWP_LEAFLET_MAP.is_zooming = false;
	}

	$().on('click', '.facetwp-map-filtering', function () {
		var $this = $(this);

		if ($this.hasClass('enabled')) {
			$this.text(FWP_JSON['leaflet_map']['filterText']);
			FWP_LEAFLET_MAP.is_filtering = false;
			FWP.autoload();
		}
		else {
			$this.text(FWP_JSON['leaflet_map']['resetText']);
			FWP_LEAFLET_MAP.is_filtering = true;
			FWP.autoload();
		}

		$this.toggleClass('enabled');
	});

	$().on('facetwp-loaded', function () {
		if ('undefined' === typeof FWP.settings.leaflet_map || '' === FWP.settings.leaflet_map) {
			return;
		}

		if (!FWP.loaded) {
			// Init MAP
			FWP_LEAFLET_MAP.map = L.map('facetwp-leaflet-map', FWP.settings.leaflet_map.init);
			switch (FWP.settings.leaflet_map.init.style) {
				case 'google-roadmap':
					var layer = new L.Google('ROADMAP');
					break;
				case 'google-satellite':
					var layer = new L.Google('');
					break;
				case 'google-terrain':
					var layer = new L.Google('TERRAIN');
					break;
				case 'google-hybrid':
					var layer = new L.Google('HYBRID');
					break;
				case 'mapbox-street':
					var layer = L.tileLayer('https://a.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token={accessToken}', {
						attribution: '',
						id: 'mapbox.streets',
						accessToken: 'pk.eyJ1IjoiZmFpcmUtc2F2b2lyIiwiYSI6ImNqcDQ3cTdqOTAxcGMzeG1ranV2NDlvb28ifQ.J-08viX3_VpEhkEg97VB0g',
						maxZoom: 18,
					});
					break;
				case 'mapbox-satellite':
					var layer = L.tileLayer('https://a.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token={accessToken}', {
						attribution: '',
						id: 'mapbox.satellite',
						accessToken: 'pk.eyJ1IjoiZmFpcmUtc2F2b2lyIiwiYSI6ImNqcDQ3cTdqOTAxcGMzeG1ranV2NDlvb28ifQ.J-08viX3_VpEhkEg97VB0g',
						maxZoom: 18,
					});
					break;
				default:
					var layer = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
						attribution: 'OpenStreetMap',
						maxZoom: 19
					});
					break;
			}
			FWP_LEAFLET_MAP.map.addLayer(layer);

			FWP_LEAFLET_MAP.map.on('dragend', function () {
				do_refresh();
			});

			FWP_LEAFLET_MAP.map.on('zoom_changed', function () {
				do_refresh();
			});
		}
		else {
			clearOverlays();
		}

		// this needs to re-init on each refresh
		FWP_LEAFLET_MAP.bounds = FWP_LEAFLET_MAP.map.getCenter();
		FWP_LEAFLET_MAP.contentCache = {};

		if ('yes' === FWP.settings.leaflet_map.config.cluster) {
			FWP_LEAFLET_MAP.allMarkers = L.markerClusterGroup({
				showCoverageOnHover: false,
				zoomToBoundsOnClick: false,
			});
			FWP_LEAFLET_MAP.allMarkers.on('clusterclick', function (cluster) {
				cluster.layer.zoomToBounds({ padding: [50, 50] });
			});
		} else {
			FWP_LEAFLET_MAP.allMarkers = L.featureGroup();
		}

		$.each(FWP.settings.leaflet_map.locations, function (obj, idx) {
			var args = Object.assign({}, obj);
			args.map = FWP_LEAFLET_MAP.map;

			var marker = new L.marker(obj.position, {
				icon: L.divIcon({
					iconSize: [48, 72],
					iconAnchor: [24, 72],
					popupAnchor: [0, -76],
					className: 'marker post-id-' + obj.post_id + ' type-' + obj.type + ' category-' + obj.category,
					html: '<div class="pin"><span class="content">' + parseInt(1 + idx) + '<span></div>'
				}),
			}).addTo(FWP_LEAFLET_MAP.allMarkers);

			if ( obj.hasOwnProperty('content') ){
				marker.bindPopup(obj.content);
			}else{
				marker.bindPopup((popup) => {
					var el = document.createElement('div');
					if ( FWP_LEAFLET_MAP.contentCache.hasOwnProperty(args.post_id) ){
						el.innerHTML = FWP_LEAFLET_MAP.contentCache[args.post_id];
						popup.update();
					}else{
						$.post(FWP_JSON.leaflet_map.ajaxurl, {
							action: 'facetwp_map_marker_content',
							facet_name: FWP_JSON.leaflet_map.facet_name,
							post_id: args.post_id
						}, {
							dataType: 'text',
							contentType: 'application/x-www-form-urlencoded',
							done: (resp) => {
								FWP_LEAFLET_MAP.contentCache[args.post_id] = resp;
								el.innerHTML = resp;
								marker.getPopup().update();
							}
						});
					}
					return el;
				});
			}

			FWP_LEAFLET_MAP.markersArray.push(marker);

			// Create an object to lookup markers based on post ID
			if ('undefined' !== typeof FWP_LEAFLET_MAP.markerLookup[obj.post_id]) {
				FWP_LEAFLET_MAP.markerLookup[obj.post_id].push(idx);
			}
			else {
				FWP_LEAFLET_MAP.markerLookup[obj.post_id] = [idx];
			}

		});
		FWP.hooks.doAction('facetwp/fwp_leaflet_map/marker/click', {
			'markers': FWP_LEAFLET_MAP.markersArray
		});

		if (0 < Object.keys(FWP_LEAFLET_MAP.allMarkers.getBounds()).length) {
			FWP_LEAFLET_MAP.map.addLayer(FWP_LEAFLET_MAP.allMarkers).fitBounds(FWP_LEAFLET_MAP.allMarkers.getBounds(), { padding: [50, 50] });
		}

		var config = FWP.settings.leaflet_map.config;

		var fit_bounds = (!FWP_LEAFLET_MAP.is_filtering && 0 < FWP.settings.leaflet_map.locations.length);
		fit_bounds = FWP.hooks.applyFilters('facetwp_map/fit_bounds', fit_bounds);

		if (fit_bounds) {
			FWP_LEAFLET_MAP.map.fitBounds(FWP_LEAFLET_MAP.allMarkers.getBounds(), { padding: [50, 50] });
		}
		else if (0 !== config.default_lat || 0 !== config.default_lng) {
			FWP_LEAFLET_MAP.map.setView({
				lat: parseFloat(config.default_lat),
				lng: parseFloat(config.default_lng)
			});
			FWP_LEAFLET_MAP.is_zooming = true;
			FWP_LEAFLET_MAP.map.setZoom(config.default_zoom);
		}

		if ( 'yes' == typeof config.cluster ) {
			FWP_LEAFLET_MAP.mc = new markerClusterGroup(FWP_LEAFLET_MAP.map, FWP_LEAFLET_MAP.markersArray, config.cluster);
		}

		document.body.addEventListener('mouseenter', function(e) {
			if ( e.target.hasAttribute('data-post-id-sync-map') ) {
				var post_id = e.target.dataset.postIdSyncMap;
				if (post_id != '') {
					syncronize_hover(post_id);
				}
			}
		}, true);

	});

	// Clear markers
	function clearOverlays() {
		FWP_LEAFLET_MAP.allMarkers.eachLayer(function (layer) {
			FWP_LEAFLET_MAP.allMarkers.removeLayer(layer);
		});
		FWP_LEAFLET_MAP.markersArray = [];
		FWP_LEAFLET_MAP.markerLookup = {};
	}

	function syncronize_hover(post_id) {
		$('*[data-post-id-sync-map]').removeClass('sync-hover');
		$('.marker').removeClass('sync-hover');
		$('*[data-post-id-sync-map="' + post_id + '"]').addClass('sync-hover');
		$('.marker.post-id-' + post_id).addClass('sync-hover');
		var marker_id = FWP_LEAFLET_MAP['markerLookup'][post_id];
		FWP_LEAFLET_MAP['markersArray'][marker_id].openPopup();
	}

})(fUtil);
