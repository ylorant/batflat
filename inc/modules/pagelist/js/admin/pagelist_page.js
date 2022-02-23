// Grab the files and upload them to the server
function uploadFiles(event)
{
    event.stopPropagation(); // Stop stuff happening
    event.preventDefault(); // Totally stop stuff happening

    // START A LOADING SPINNER HERE

    // Create a formdata object and add the files
    var data = new FormData();
    data.append('file', event.target.files[0]);

	var loaderId = $(event.target).data('loader');
    var loader = $('#' + loaderId);

	loader.removeClass('d-none');

	 $.ajax({
        url: '{?=url([ADMIN, "pagelist", "editorUpload"])?}',
        type: 'POST',
        data: data,
        cache: false,
        dataType: 'json',
        processData: false, // Don't process the files
        contentType: false, // Set content type to false as jQuery will tell the server its a query string request
        success: function(data, textStatus, jqXHR)
        {
        	loader.addClass('d-none');

            if(typeof data.error === 'undefined')
            {
            	$("#input-picture").val(data.result);
            	$("#upload-thumbnail").attr('src', data.result);
            }
            else
            {
                bootbox.alert(data.result);
            }
        },
        error: function(jqXHR, textStatus, errorThrown)
        {
        	loader.addClass('d-none');
        }
    });
}

$(document).ready(function()
{
	 // Variable to store your files
	var files;

	// Add events
	$('input[type=file]').on('change', uploadFiles);
});
