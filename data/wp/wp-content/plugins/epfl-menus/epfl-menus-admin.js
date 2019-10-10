/**
 * Interactive features for the external menus functionality
 *
 * This piece of JS is loaded on both the list view app for the
 * epfl-external-menu custom post type, and in the Appearance -> Menus
 * screen (although at the moment it does nothing on the latter).
 */

/**
 * Activate the app on the epfl-external-menu list view screen
 */
function initExternalMenuList ($) {
    $('a.page-title-action').remove();
    $('h1.wp-heading-inline').after('<button class="page-title-action">' + wp.translations.refresh_button + '</button>');
    var $button = $('h1.wp-heading-inline').next();

    $button.click(function() {
        onRefreshButton($, $button);
    });
}

function onRefreshButton ($, $button) {
    if ($button.spinner) {
        if ($button.spinner.isActive()) {
            return;
        } else {
            $button.spinner.$spinner.remove();
        }
    }
    $('p', $button).remove();  // Previous error messages (if any), see below
    $button.spinner = new Spinner($);
    $button.append($button.spinner.$spinner);

    window.EPFLMenus.post('enumerate', { data: { retryToken: null} })
        .then(function(response) {
          return enumerateRetry(response);
        }).then(
            function(response) {
                var ids = response.external_menu_item_ids;
                if (ids) {
                    $button.spinner.progress.resolve();
                    return updateRowsDeferred($, ids);
                } else {
                    console.log('AJAX ERROR:', response);
                    throw new Error(response.status);
                }
            },
            function(err) {
                $button.spinner.progress.reject();
                if (err.status === 504) {
                    $button.spinner.$spinner.removeClass().addClass('ajax-timeout');
                    $button.append('<p class="tooltip-error">Timeout in the server</p>');
                } else {
                    $button.append('<p class="tooltip-error">Server-side error</p>');
                }

            });

    function enumerateRetry(response) {
        if (! response.hasOwnProperty('retryToken')) {
            return response;
        }
        return window.EPFLMenus.post(
            'enumerate',
            {data: {retryToken: response.retryToken}})
            .then(
                enumerateRetry,
                function(err) {
                    if (err.status === 504) {
                        return enumerateRetry(response);
                    } else {
                        throw new Error(response.status);
                    }
                });
    }
}

/**
 * @param $ the jQuery framework object
 * @param ids An array of IDs returned by the 'enumerate' AJAX call,
 *            which may differ from what is visible on-screen (e.g.
 *            new child site just popped up, or there are more
 *            ExternalMenuItem's than the pagination limit)
 */
function updateRowsDeferred ($, ids) {
    var d = $.Deferred();
    var todoCount = ids.length;
    var hasInvisibleChanges = false;

    if (! todoCount) {
        // It is bad practice to resolve a promise before returning it:
        window.setTimeout(function() {
            d.resolve();
        }, 0);
        return d;
    }

    // Here, we could update the tr's in tbody#the-list to match ids,
    // perhaps with some kind of CSS animation for
    // appearing/disappearing rows.

    for (var i = 0 ; i < ids.length; i++) {
        var id = ids[i], $tr = $('tr#post-' + id);
        if (! $tr.length) {
            hasInvisibleChanges = true;
        }

        // Start all the updates in parallel at once, and let the
        // browser's outstanding XMLHTTPRrequest limit do the
        // throttling
        updateOneRowDeferred($, id, $tr).always(function() {
            if (--todoCount) return;

            if (! hasInvisibleChanges) {
                // Put a check mark in the top-most Refresh button,
                // and allow user to click it again
                d.resolve();
            } else {
                // Some rows were created/deleted (or pagination is in
                // play), and the display might currently be
                // incomplete or misleading. Rather than patching up
                // the DOM (which we could, see comment above), just
                // reload the page. This is a rare case; in
                // steady-state, the reload button doesn't create or
                // delete any ExternalMenuItem.
                location.reload();
            }
        });
    }
    return d;
}

function updateOneRowDeferred ($, id, $tr) {
    if ($tr.spinner) { $tr.spinner.$spinner.remove(); }
    var spinner = $tr.spinner = new Spinner($);
    $('td.column-date', $tr).empty().append(spinner.$spinner);

    var allRowClasses = 'sync-inprogress sync-success sync-failed';
    $tr.removeClass(allRowClasses).addClass('sync-inprogress');
    spinner.progress.then(
        function() { $tr.removeClass(allRowClasses).addClass('sync-success'); },
        function() { $tr.removeClass(allRowClasses).addClass('sync-failed'); });

    window.EPFLMenus.post('refresh_and_resubscribe_by_id', {data: {id: id}})
        .then(
            function (response) {
                if (response.status !== 'OK') {
                    spinner.progress.reject();
                } else {
                    spinner.progress.resolve();
                }
            },
            function (error) {
                console.log('POST refresh_and_resubscribe_by_id for ' + id + ': ', error);
                spinner.progress.reject();
            });
    return spinner.progress;
}

/**
 * @constructor
 */
function Spinner ($) {
    var $spinner = this.$spinner = $('<span></span>');
    var progress = this.progress = $.Deferred();

    function updateSpinner () {
        $spinner.removeClass().addClass('ajax-' + progress.state());
    }
    updateSpinner();
    progress.always(updateSpinner);
}

Spinner.prototype.isActive = function() {
    return this.progress.state() === 'pending';
}

jQuery(document).ready(function($) {
    if (window.wp.screen.base === 'edit' && window.wp.screen.post_type === 'epfl-external-menu' ) {
        initExternalMenuList($);
    }

    // If you see this, nothing threw or crashed (yet).
    console.log('epfl-menus-admin.js is on duty.');
});
