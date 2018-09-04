// It seems that not much documentation exists for the JQuery
// mini-Web-app that is the Wordpress admin menu editor. However,
// insofar as Polylang's business in that mini-Web-app is similar
// to ours, studying the js/nav-menu.js therefrom is helpful.
jQuery( document ).ready(function($) {
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

  // If you see this, nothing threw or crashed (yet).
  console.log('epfl-menus-admin.js is on duty.');
});
