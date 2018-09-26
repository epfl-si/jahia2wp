jQuery(document).ready(function($){
  
  $('html').addClass('redpandas-will-rule-the-world');
  
  // Magnific popup
  
//  $('.lightbox').magnificPopup({
//          type: 'image',
//          closeOnContentClick: true,
//          mainClass: 'lightbox',
//          image: {
//            verticalFit: true
//          }
//          
//        });
  
  // Toggle box 
  
  $(".collapsible:not(.open) .collapsible-content").hide();
  
  $(".collapsible .collapse-link").click(function(event){
    event.preventDefault();
    $(this).parents(".collapsible").find(".collapsible-content").slideToggle("fast");
    $(this).parents(".collapsible").toggleClass("open");
    $(this).parents(".collapsible").toggleClass("close");
  });
  
  $(".toggler-content").hide();
  
  $(".toggler").click( function(event) {
    event.preventDefault();
    $(this).toggleClass("toggled-active");
    $(this).next(".toggler-content").slideToggle("fast");
  } );
  
  // Sitemap
  
  $(".simple-sitemap-page .page_item_has_children").prepend('<button class="children-toggle"><span class="visuallyhidden">Afficher / masquer les enfants</span></button>');
  
  $(".simple-sitemap-page .page_item_has_children .children-toggle").click(function(){
    $(this).toggleClass("open");
    $(this).siblings(".children").toggle();
  });
  
  // EPFL header
  
  $(".search-filter .selected-field").click(function(){
    $(this).siblings(".menu").toggleClass("hidden");
  });
  
  $(".search-filter .menu").mouseleave(function(){
      $(this).addClass("hidden");
  });
  
  $(".search-filter .menu").find("label").click(function(event){
    event.preventDefault();
    var textLabel = $(this).text();
    $(this).addClass("current");
    $(this).parent("li").siblings("li").find("label").removeClass("current");
    $(".search-filter .selected-field").text(textLabel);
    $(".search-filter .menu").addClass("hidden");
  });
  
  // Secondary navigation
  
  function secondaryNavigation() {
      
    // Add sub-menu control
    $('.sidebar .sub-menu li.menu-item-has-children').each( function(){
      $(this).prepend('<button class="sub-menu-control"></button>');
      $(this).addClass('open');
    });
    
    $('.sidebar .nav').find('button.sub-menu-control').click(function(){
      $(this).siblings('.sub-menu').toggle();
      $(this).parent('.menu-item-has-children').toggleClass('open');
    });
  
  } secondaryNavigation();  
  
  
	
	// Add dropdown toggle that displays child menu items.
  
  var dropdownToggle = $( '<button />', { 'class': 'dropdown-toggle', 'aria-expanded': false })
      .append( $( '<span />', { 'class': 'screen-reader-text', text: epfl_l10n.expand }) );
  
  $('.main-navigation').find( '.menu-item-has-children > a, .page_item_has_children > a' ).after( dropdownToggle );

  $('.main-navigation').find('.dropdown-toggle').click( function(e) {
    screenReaderSpan = $(this).find( '.screen-reader-text' );
    e.preventDefault();
    
    $(this).toggleClass('toggled-on');
    $(this).next( '.children, .sub-menu' ).toggleClass( 'toggled-on' );
    $(this).attr( 'aria-expanded', $(this).attr( 'aria-expanded' ) === 'false' ? 'true' : 'false' );
    
    screenReaderSpan.text( screenReaderSpan.text() === epfl_l10n.expand ? epfl_l10n.collapse : epfl_l10n.expand );
  } );
  
  // Remove link border form around images
  
  $('a img').parent('a').css('border','none');
  $('a span.read-more').parent('a').css('border','none');
  
  
});