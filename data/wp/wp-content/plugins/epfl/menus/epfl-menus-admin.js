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
