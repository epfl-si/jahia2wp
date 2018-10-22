// It seems that not much documentation exists for the JQuery
// mini-Web-app that is the Wordpress admin menu editor. However,
// insofar as Polylang's business in that mini-Web-app is similar
// to ours, studying the js/nav-menu.js therefrom is helpful.

function initNavMenus ($) {
  var $metabox = $('div.add-external-menu');
  $('input.submit-add-to-menu', $metabox).click(function() {
    console.log("click");

   // For inspiration regarding wpNavMenu, look at wp-admin/js/nav-menu.js
   wpNavMenu.addItemToMenu(
     {'-1': {
       'menu-item-type': 'external-menu',
       'menu-item-url': 'https://example.com/restapi/menu?lang=en_JP'
     }},
     wpNavMenu.addMenuItemToBottom,
     function() {
       console.log('Added external menu to menu');
     });

  });  // submit button's .click()
}

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
  if (window.wp.screen.base === 'nav-menus') {
    initNavMenus($);
  }

  if (window.wp.screen.base === 'edit' && window.wp.screen.post_type === 'epfl-external-menu' ) {
    initExternalMenuList($);
  }

  // If you see this, nothing threw or crashed (yet).
  console.log('epfl-menus-admin.js is on duty.');
});
