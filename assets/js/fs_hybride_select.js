(function ($) {

	$(document).on('facetwp-loaded', function() {

		// init select 
		$('.facetwp-type-fs_hybride_select').each(function() {
			$(this).parent().removeClass('hidden')
		})

		if ( window.innerWidth >= 1280 ) {
			// init select on desktop
			$('.facetwp-type-fs_hybride_select').each(function() {
				$(this).stop().slideUp(200)
				$(this).parent().removeClass('active').removeClass('open')
				$(this).siblings('.fs-label').remove()
				$(this).removeClass('hidden')
			})

			// select active
			$('.facetwp-type-fs_hybride_select.is-active').each(function() {
				var checkboxs = []
				$(this).parent().addClass('active')
				$(this).parent().find('.fs-label').remove()
				$(this).find('.facetwp-checkbox.checked').each( function() {
					checkboxs.push( this.childNodes[0].textContent )
				})
				$(this).before('<div class="fs-label">' + checkboxs.join(', ') + '</div>')
			})
		}

		// hide select if no checkbox
		$('.facetwp-type-fs_hybride_select').each( function() {
			if ( $(this).is(':empty') ){
				$(this).parent().addClass('hidden')
			}
		})
	})

	// toggle select
	$(document).on('click', '.facette--fs_hybride_select', function(e) {
		if ( window.innerWidth >= 1280 ) {			
			$('.facette--fs_hybride_select.open').removeClass('open').find('.facetwp-type-fs_hybride_select').stop().slideUp(200)
			$(this).toggleClass('open').find('.facetwp-type-fs_hybride_select').stop().slideToggle(200)
		}
	})

	// close hybride select
	$(document).on('click', function(e) {
		if ( window.innerWidth >= 1280 && $(e.target).closest('.facette--fs_hybride_select').length === 0 ) {
			$('.facette--fs_hybride_select').removeClass('open')
			$('.facetwp-type-fs_hybride_select').stop().slideUp(200)
		}
	})

})(jQuery)
