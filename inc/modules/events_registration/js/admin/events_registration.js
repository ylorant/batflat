function insertEditor(type)
{
    const editor = document.getElementsByClassName('editor');

    if (type === 'wysiwyg') {
        const easyMdeEditors = document.getElementsByClassName('EasyMDEContainer');
        for (let i = 0; i < editor.length; ++i) {
            if (easyMdeEditors.length) {
                for (let i = 0; i < easyMdeEditors.length; ++i) {
                    // Remove EasyMDE if exists
                    easyMdeEditors[i].parentNode.removeChild(easyMdeEditors[i]);
                }
            }

            const sunEditor = SUNEDITOR.create(editor[i], {
                lang: SUNEDITOR_LANG['en'],
                minHeight: '300px',
                buttonList: [
                    ['undo', 'redo'],
                    ['font', 'fontSize', 'formatBlock'],
                    ['paragraphStyle', 'blockquote'],
                    ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
                    ['fontColor', 'hiliteColor', 'textStyle'],
                    ['removeFormat'],
                    '/', // Line break
                    ['outdent', 'indent'],
                    ['align', 'horizontalRule', 'list', 'lineHeight'],
                    ['table', 'link', 'image', 'video', 'audio'],
                    ['fullScreen', 'showBlocks', 'codeView'],
                    ['preview', 'print'],
                    ['save'],
                ]
            });
            sunEditor.onChange = function (contents, core) {
                this.save();
            };
            /**sunEditor.onPaste = function (e, cleanData, maxCharCount, core) {
                console.log('onPaste', e);
            };**/
            sunEditor.onImageUploadBefore = function (files, info, core, uploadHandler) {
                try {
                    ResizeImage(files, uploadHandler);
                } catch (err) {
                    uploadHandler(err.toString());
                }
            };
            sunEditor.onImageUpload = function (files) {
                sendFile(files[0], this);
            };
        }
    } else {
        const sunEditors = document.getElementsByClassName('sun-editor');
        if (sunEditors.length) {
            // Remove SunEditor if exists
            for (let i = 0; i < sunEditors.length; ++i) {
                sunEditors[i].parentNode.removeChild(sunEditors[i]);
            }
        }

        // Add EasyMDE
        for (let i = 0; i < editor.length; ++i) {
            const easyMDE = new EasyMDE({element: editor[i]});
            easyMDE.codemirror.on('change', () => {
                editor[i].parentNode.getElementsByTagName('textarea')[0].value = easyMDE.value();
            });
        }
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

            xhr.upload.addEventListener("progress", function (evt) {
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

function selectEditor()
{
    const checkbox = $('input[name="markdown"]');
    if ($('.editor').data('editor') === 'wysiwyg') {
        checkbox.change(function () {
            if ($(this).is(':checked')) {
                insertEditor('html');
            } else {
                insertEditor('wysiwyg');
            }
        });

        if (checkbox.is(':checked')) {
            insertEditor('html');
        } else {
            insertEditor('wysiwyg');
        }
    } else {
        insertEditor('html');
    }
}

function to_seconds(hh, mm, ss)
{
    let h = parseInt(hh);
    let m = parseInt(mm);
    let s = parseInt(ss);
    if (isNaN(h)) {
        h = 0;
    }
    if (isNaN(m)) {
        m = 0;
    }
    if (isNaN(s)) {
        s = 0;
    }

    return h * 60 * 60 +
        m * 60 +
        s;
}

// expects 1d 11h 11m, or 1d 11h,
// or 11h 11m, or 11h, or 11m, or 1d
// returns a number of seconds.
function parseDuration(sDuration)
{
    if (sDuration == null || sDuration === '') {
        return 0;
    }
    let hrx = new RegExp(/([0-9][0-9]?)[ ]?h/);
    let mrx = new RegExp(/([0-9][0-9]?)[ ]?m/);
    let srx = new RegExp(/([0-9][0-9]?)[ ]?s/);

    let hours = 0;
    let minutes = 0;
    let seconds = 0;

    if (mrx.test(sDuration)) {
        minutes = mrx.exec(sDuration)[1];
    }
    if (hrx.test(sDuration)) {
        hours = hrx.exec(sDuration)[1];
    }
    if (srx.test(sDuration)) {
        seconds = srx.exec(sDuration)[1];
    }

    return to_seconds(hours, minutes, seconds);
}

/*
 * Outputs a duration string based on the number of seconds provided.
 * Rounded off to the nearest 1 minute.
 */
function toDurationString(iDuration)
{
    if (iDuration <= 0) {
        return '';
    }
    let h = Math.floor((iDuration / 3600) % 24);
    let m = Math.floor((iDuration / 60) % 60);
    let s = Math.floor(iDuration % 60);
    let result = ''
    if (h > 0) {
        result = result + h + "h ";
    }
    if (m > 0) {
        result = result + m + "m ";
    }
    if (s > 0) {
        result = result + s + "s ";
    }
    return result.substring(0, result.length - 1);
}

function displayOpponents()
{
    const checkBox = document.getElementById("race");

    if (checkBox !== null) {
        let input = document.getElementById("raceOpponents").parentElement;

        if (checkBox.checked === true) {
            input.style.display = "block";
        } else {
            input.style.display = "none";
        }
    }
}


$(document).ready(function () {
    selectEditor();
    displayOpponents();

    const selectElement = document.querySelector('form input.duration');
    if (selectElement !== null) {
        selectElement.addEventListener("change", (event) => {
            event.preventDefault();
            let sd = selectElement.value;
            let seconds = parseDuration(sd);
            if (sd !== '' && seconds === 0) {
                selectElement.style.color = "red";
                selectElement.focus();
            } else {
                document.getElementById("duration").value = seconds;
                selectElement.value = toDurationString(seconds);
                selectElement.style.color = "green";
            }
        });
    }
});