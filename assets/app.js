// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import 'select2';

const $ = require('jquery');
const $select2 = $('#lang');

const Cropper = require('cropperjs');
import 'cropperjs/dist/cropper.css';

/**
 * Populate and re-initialize the Select2 input with languages supported by the given engine.
 * @param {String} engine Supported engine ID such as 'google' or 'tesseract'.
 */
function updateSelect2Options(engine)
{
    $.getJSON(`/api/available_langs?engine=${engine}`).then(response => {
        const langs = response.available_langs;
        let data = [];

        // Transform to data structure needed by Select2.
        Object.keys(langs).forEach(lang => {
            data.push({
                id: lang,
                text: `${lang} – ${langs[lang]}`,
            });
        });

        // First clear any existing selections and empty all options.
        $select2.val(null)
            .empty()
            .trigger('change');

        // Update Select2 with the new options.
        data.forEach(datum => {
            const option = new Option(datum.text, datum.id, false, false);
            $select2.append(option);
        });

        // Update language with engine cached values.
        let selectedLangs = JSON.parse(localStorage.getItem('selected-langs'));
        $select2.val(selectedLangs).trigger('change');
    });
}

$(function () {
    // Remove nojs class, for styling non-Javascript users.
    $('html').removeClass('nojs');

    // Initiate Select2, which allows dynamic entry of languages.
    $select2.select2({
        theme: 'bootstrap',
        placeholder: $select2.data('placeholder'),
    });

    // Clear previously cached languages
    localStorage.removeItem('selected-langs');

    // Listen for language select event and update cache
    $select2.on('select2:select', e => {
        let selectedValue = e.params.data.id;
        let storedLangs = [];
        if (localStorage.getItem("selected-langs") === null) {
            storedLangs.push(selectedValue);
        }else{
            storedLangs = JSON.parse(localStorage.getItem('selected-langs'));
            if(!storedLangs.includes(selectedValue)){
                storedLangs.push(selectedValue);
            }
        }
        localStorage.setItem('selected-langs', JSON.stringify(storedLangs));
    });

    // Listen for language unselect event and update cache
    $select2.on('select2:unselect', e => {
        let selectedValue = e.params.data.id;
        let storedLangs = JSON.parse(localStorage.getItem('selected-langs'));
        storedLangs = storedLangs.filter( value => {
            return selectedValue !== value;
        });
        localStorage.setItem('selected-langs', JSON.stringify(storedLangs));
    });

    // Show engine-specific options.
    $('[name=engine]').on('change',  e => {
        updateSelect2Options(e.target.value);
        $('.engine-options').addClass('hidden');
        $(`#${e.target.value}-options`).removeClass('hidden');
    });

    // For the result page. Makes the 'Copy' button copy the transcription to the clipboard.
    const $copyButton = $('.copy-button');
    if ($copyButton.length) {
        $copyButton.on('click', e => {
            e.preventDefault();
            const $textarea = $('#text');
            $textarea.select();
            document.execCommand('copy');
            $copyButton.text($copyButton.data('copied-text'));
        });
    }

    var $ocrOutputDiv = $('.ocr-output');
    if ($ocrOutputDiv.length) {
        // Cropper.
        const img = document.getElementById('source-image'),
            x = document.querySelector('[name="crop[x]"]'),
            y = document.querySelector('[name="crop[y]"]'),
            width  = document.querySelector('[name="crop[width]"]'),
            height = document.querySelector('[name="crop[height]"]'),
            $modeButtons = $('.drag-mode');
        new Cropper(img, {
            viewMode: 2,
            dragMode: 'move',
            // Remove double-click drag mode toggling, because we've got buttons for that.
            toggleDragModeOnDblclick: false,
            // Only show a crop area if it is defined.
            autoCrop: width.value > 0 && height.value > 0,
            responsive: true,
            ready () {
                // Make textarea match height of image.
                $('#text').css({
                    height: this.cropper.getContainerData().height,
                });
                // React to changes in the crop-mode buttons.
                $modeButtons.on( 'click', event => {
                    const $button = $(event.currentTarget);
                    $modeButtons.removeClass('active');
                    $button.addClass('active');
                    this.cropper.setDragMode($button.data('drag-mode'));
                });
            },
            data: {
                x: Number.parseFloat(x.value),
                y: Number.parseFloat(y.value),
                width: Number.parseFloat(width.value),
                height: Number.parseFloat(height.value),
            },
            crop(event) {
                x.value = Math.round(event.detail.x);
                y.value = Math.round(event.detail.y);
                width.value = Math.round(event.detail.width);
                height.value = Math.round(event.detail.height);
                // Enable the cropping buttons. No need to disable them ever because there's no way to remove the crop box.
                $('.btn.submit-crop').attr('disabled', false).removeClass('disabled');
                $modeButtons.attr('disabled', false);
            }
        });

        // When setting a new image URL, remove the preview and the crop dimensions.
        $('[name=image]').on('change', e => {
            $ocrOutputDiv.remove();
        });

        // When submitting the main 'transcribe' button, do not send crop dimensions.
        $('.submit-btn .btn').on('click', e => {
            x.value = null;
            y.value = null;
            width.value = null;
            height.value = null;
        });
    }
});
