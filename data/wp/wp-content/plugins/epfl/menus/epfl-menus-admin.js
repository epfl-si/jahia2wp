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
    var spinning = false;
    $button.click(function() {
        if (spinning) return;
        var $form = window.EPFLMenus.asWPAdminPostForm('refresh');
        $form.submit();
        var $spinner = $('<span class="ajax-spinner"></span>');
        $button.append($spinner);
        spinning = true;
    });
}

jQuery( document ).ready(function($) {
  if (window.wp.screen.base === 'edit' && window.wp.screen.post_type === 'epfl-external-menu' ) {
    initExternalMenuList($);
  }

  // If you see this, nothing threw or crashed (yet).
  console.log('epfl-menus-admin.js is on duty.');
});
