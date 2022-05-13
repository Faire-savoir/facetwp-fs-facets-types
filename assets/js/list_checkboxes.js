(function ($) {

	/* ======== List Checkboxes ======== */
    FWP.hooks.addAction('facetwp/refresh/fs_list_checkboxes', function($this, facet_name) {
        var selected_values = [];
        $this.find('.facetwp-checkbox.checked').each(function() {
            selected_values.push(
                $(this).attr('data-value')
            );
        });
        FWP.facets[facet_name] = selected_values;
    });

    FWP.hooks.addFilter('facetwp/selections/fs_list_checkboxes', function(output, params) {
        var choices = [];
        $.each(params.selected_values, function(val) {
            var $item = params.el.find('.facetwp-checkbox[data-value="' + val + '"]');
            if ($item.len()) {
                var choice = $($item.html());
                choice.find('.facetwp-counter').remove();
                choice.find('.facetwp-expand').remove();
                choices.push({
                    value: val,
                    label: choice.text()
                });
            }
        });
        return choices;
    });
    

    $().on('click', '.facetwp-type-fs_list_checkboxes .facetwp-expand, .facetwp-type-fs_hybride_selecte .facetwp-expand', function(e) {
        var $wrap = $(this).closest('.facetwp-checkbox').next('.facetwp-depth');
        $wrap.toggleClass('visible');
        var content = $wrap.hasClass('visible') ? FWP_JSON['collapse'] : FWP_JSON['expand'];
        $(this).html(content);
        e.stopImmediatePropagation();
    });

    $().on('click', '.facetwp-type-fs_list_checkboxes .facetwp-checkbox:not(.disabled), .facetwp-type-fs_hybride_select .facetwp-checkbox:not(.disabled)', function() {
        var $cb = $(this);
        var is_checked = ! $cb.hasClass('checked');
        var is_child = $cb.closest('.facetwp-depth').len() > 0;
        var is_parent = $cb.next().hasClass('facetwp-depth');

        // if a parent is clicked, deselect all of its children
        if (is_parent) {
            $cb.next('.facetwp-depth').find('.facetwp-checkbox').removeClass('checked');
        }
        // if a child is clicked, deselects all of its parents
        if (is_child) {
            $cb.parents('.facetwp-depth').each(function() {
                $(this).prev('.facetwp-checkbox').removeClass('checked');
            });
        }

        $cb.toggleClass('checked', is_checked);
        FWP.autoload();
    });

    $().on('click', '.facetwp-type-fs_list_checkboxes .facetwp-toggle, .facetwp-type-fs_hybride_select .facetwp-toggle', function() {
        var $parent = $(this).closest('.facetwp-facet');
        $parent.find('.facetwp-toggle').toggleClass('facetwp-hidden');
        $parent.find('.facetwp-overflow').toggleClass('facetwp-hidden');
    });

    $().on('facetwp-loaded', function() {
        $('.facetwp-type-fs_list_checkboxes .facetwp-overflow, .facetwp-type-fs_hybride_select .facetwp-overflow').each(function() {
            var num = $(this).find('.facetwp-checkbox').len();
            var $el = $(this).next('.facetwp-toggle');
            $el.text($el.text().replace('{num}', num));

            // auto-expand if a checkbox within the overflow is checked
            if (0 < $(this).find('.facetwp-checkbox.checked').len()) {
                $el.trigger('click');
            }
        });

        // hierarchy expand / collapse buttons
        $('.facetwp-type-fs_list_checkboxes, .facetwp-type-fs_hybride_select').each(function() {
            var $facet = $(this);
            var name = $facet.attr('data-name');

            // error handling
            if (Object.keys(FWP.settings).length < 1) {
                return;
            }

            // expand children
            if ('yes' === FWP.settings[name]['show_expanded']) {
                $facet.find('.facetwp-depth').addClass('visible');
            }

            if (1 > $facet.find('.facetwp-expand').len()) {

                // expand groups with selected items
                $facet.find('.facetwp-checkbox.checked').each(function() {
                    $(this).parents('.facetwp-depth').addClass('visible');
                });

                // add the toggle button
                $facet.find('.facetwp-depth').each(function() {
                    var which = $(this).hasClass('visible') ? 'collapse' : 'expand';
                    $(this).prev('.facetwp-checkbox').append(' <span class="facetwp-expand">' + FWP_JSON[which] + '</span>');
                });
            }
        });
    });

})(fUtil);
