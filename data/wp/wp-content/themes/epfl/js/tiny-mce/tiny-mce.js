(function() {


   tinymce.create('tinymce.plugins.mp4', {
      init : function(ed, url) {
         ed.addButton('mp4', {
            title : 'MP4',
            image : url+'/mp4.png',
            onclick : function() {
               url = prompt("Enter url","");
               ed.execCommand( 'mceInsertContent', false, '[mp4 src="' + url + '" width="720" height="480"]');
            }
         });
      },
      getInfo : function() {
         return {
            longname : "MP4",
            author : 'EPFL',
            authorurl : '',
            infourl : '',
            version : "1.0"
         };
      }
   });
   tinymce.PluginManager.add('mp4', tinymce.plugins.mp4);
})();

