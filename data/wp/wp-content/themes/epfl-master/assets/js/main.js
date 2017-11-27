jQuery(document).ready(function($){
  
  $('html').addClass('redpandas-will-rule-the-world');
  
  // Toggle box 
  
  $(".collapsible:not(.open) .collapsible-content").hide();
  
  $(".collapsible .collapse-link").click(function(event){
    event.preventDefault();
    $(this).parents(".collapsible").find(".collapsible-content").slideToggle("fast");
    $(this).parents(".collapsible").toggleClass("open");
  });
  
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
  
  
});