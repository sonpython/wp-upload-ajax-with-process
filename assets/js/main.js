(function($) {
	"use strict";

	window.tp_remove_data_upload = function (selector) {
        
        var data = [];

        $(''+ selector +'').click(function(e) {

        	var ajax_url = tp_ajax_url.ajax_url,
        	$this = $(this),
			attach_id = $(this).data('delete'),
			r = confirm("Press OK to delete this file, Cancel to leave!");

			if (r == true) {

				$.ajax({
					type: 'POST',
					url: ajax_url,
					data: {
						action: 'tp_delete_upload',
						attach_id: attach_id
					},
					success: function(respon)
					{
						$this.parent('.success').remove();
						if( ! $this.parent('#files').find('.success').length ){
							$('#upload-results').hide();
						}
					}
				});
			}
        	return false;
        });

    };

})(jQuery);