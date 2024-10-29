jQuery(document).ready(function($) {
	if($("#afblhidenotice").length) {
		$("#afblhidenotice").click( function(e) {
			e.preventDefault();

			var data = {
				'action': 'afbl_hide_notice',
				'nonce': aflb_admin.nonce
			};

			$.post(ajaxurl, data, function(response) {
				
				if($("#afblnotice").length) {
					$("#afblnotice").hide("slow");
				}
			});
		});
	}
});