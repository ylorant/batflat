function insertEditor()
{
    const editor = document.getElementsByClassName('editor');

    // Add EasyMDE (No SunEditor here because we need to edit actual HTML tags)
    for (let i = 0; i < editor.length; ++i) {
        const easyMDE = new EasyMDE({element: editor[i]});
        easyMDE.codemirror.on('change', () => {
            editor[i].parentNode.getElementsByTagName('textarea')[0].value = easyMDE.value();
        });
    }

}

function sendFile(file, editor)
{
    var formData = new FormData();
    formData.append('file', file);

    $.ajax({
        xhr: function () {
            var xhr = new window.XMLHttpRequest();

            $('input[type="submit"]').prop('disabled', true);
            var progress = $('.progress:first').clone();
            progress = (progress.fadeIn()).appendTo($('.progress-wrapper'));

            xhr.upload.addEventListener('progress', function (evt) {
                if (evt.lengthComputable) {
                    var percentComplete = evt.loaded / evt.total;
                    percentComplete = parseInt(percentComplete * 100);
                    progress.children().css('width', percentComplete + '%');

                    if (percentComplete === 100) {
                        progress.fadeOut();
                        progress.remove();
                        $('input[type="submit"]').prop('disabled', false);
                    }
                }
            }, false);

            return xhr;
        },
        url: '{?=url([ADMIN, "blog", "editorUpload"])?}',
        data: formData,
        type: 'POST',
        cache: false,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function (data) {
            if (data.status === 'success') {
                $image['remove']();
            } else if (data.status === 'failure') {
                $image['remove']();
                bootbox.alert(data.result);
            }
        }
    });
}

// image resize
function ResizeImage (files, uploadHandler) {
    const uploadFile = files[0];
    const img = document.createElement('img');
    const canvas = document.createElement('canvas');
    const reader = new FileReader();

    reader.onload = function (e) {
        img.src = e.target.result
        img.onload = function () {
            let ctx = canvas.getContext("2d");
            ctx.drawImage(img, 0, 0);

            const MAX_WIDTH = 200;
            const MAX_HEIGHT = 100;
            let width = img.width;
            let height = img.height;

            if (width > height) {
                if (width > MAX_WIDTH) {
                    height *= MAX_WIDTH / width;
                    width = MAX_WIDTH;
                }
            } else {
                if (height > MAX_HEIGHT) {
                    width *= MAX_HEIGHT / height;
                    height = MAX_HEIGHT;
                }
            }

            canvas.width = width;
            canvas.height = height;

            ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(function (blob) {
                uploadHandler([new File([blob], uploadFile.name)]);
            }, uploadFile.type, 1);
        };
    };

    reader.readAsDataURL(uploadFile);
}

$(document).ready(function () {
    insertEditor();
});