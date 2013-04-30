/**
 * Coachlog TinyMCE plugin.
 */

(function() {
	var DOM = tinymce.DOM;

	tinymce.create('tinymce.plugins.Pullquote', {
		init : function(ed, url) {
            // Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mcepullquote');
            ed.addCommand('mcepullquote', function(){
                ed.execCommand( 'mceInsertContent', false, '[pullquote]' + ed.selection.getContent() + '[/pullquote]' );
				ed.execCommand( 'mceRepaint' );
            });

			ed.addButton('pullquote', // name to add to toolbar button list
			{
				title : 'Insert Pull Quote', // tooltip text seen on mouseover
				cmd: 'mcepullquote',
			});
		}
	});

	// Register plugin
	tinymce.PluginManager.add('pullquote', tinymce.plugins.Pullquote);
})();
