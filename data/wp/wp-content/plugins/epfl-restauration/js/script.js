var if_height,
    restauration_iframe = jQuery('#epfl-restauration');


window.addEventListener('message', function(e)
{

    var h = Number( e.data.replace( /.*if_height=(\d+)(?:&|$)/, '$1' ) );

    if (!isNaN( h ) && h > 0 && h !== if_height) {
        /* Height has changed, update the iframe */
        if_height = h;
        restauration_iframe.height(h);
    }

} , false);