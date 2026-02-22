// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import 'select2';
import {cleanText} from './textCleaner.js';

const $ = require('jquery');
const $select2 = $('#lang');
var selectedLanguages = [];
const $lineDetectionSelect = $('#line-id');
var lineModels = null;
let originalTranscript = "";
let currentTranscript = "";

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

        // Update selected languages.
        $select2.val(selectedLanguages).trigger('change');
    });
}

function fetchLineModelsJSON () {
    $.getJSON('/api/transkribus/available_line_ids').then(response => {
        lineModels = response.available_line_ids;
    });
}

/**
 * Populate the select input field for line detection model IDs with
 * the line detection model IDs available for the Transkribus engine
 */
function updateLineModelOptions () {
    const staticOptions = $lineDetectionSelect[0].options;
    let staticOptionData = [];
    Array.prototype.forEach.call(staticOptions, option => {
        staticOptionData.push({
            text: option.text,
            value: option.value
        });
    });

    // clear existing selections and options
    $lineDetectionSelect.val(null).empty().trigger('change');

    // append static options
    staticOptionData.slice(0, 2).forEach(staticOption => {
        $lineDetectionSelect.append(new Option(staticOption.text, staticOption.value, staticOption.value === null, false));
    });

    // append all other line detection models as options
    Object.keys(lineModels).forEach(model => {
        const option = new Option(lineModels[model], model, false, false);
        $lineDetectionSelect.append(option);
    });
}

$(function () {

    // fetch line detection model data
    fetchLineModelsJSON();

    // Remove nojs class, for styling non-Javascript users.
    $('html').removeClass('nojs');

    // Initiate Select2, which allows dynamic entry of languages.
    $select2.select2({
        theme: 'bootstrap',
        placeholder: $select2.data('placeholder'),
    });

    // Listen for language select event and update selectedLanguages
    $select2.on('select2:select', e => {
        let selectedValue = e.params.data.id;
        if(!selectedLanguages.includes(selectedValue)){
            selectedLanguages.push(selectedValue);
        }
    });

    // Listen for language unselect event and update selectedLanguages
    $select2.on('select2:unselect', e => {
        let selectedValue = e.params.data.id;
        selectedLanguages = selectedLanguages.filter( value => {
            return selectedValue !== value;
        });
    });

    let previousDataPlaceholder = $select2.attr('data-placeholder');

    // Show engine-specific options.
    $('[name=engine]').on('change',  e => {
        let engine = e.target.value;
        updateSelect2Options(engine);
        $('.engine-options').addClass('hidden');
        $(`#${engine}-options`).removeClass('hidden');
        if(engine === 'tesseract' || engine === 'google') {
            $select2.prop('required', false);
            $select2.attr('data-placeholder', previousDataPlaceholder);
            $select2.data('select2').selection.placeholder.text = previousDataPlaceholder;
            $('#transkribus-lang-label').addClass('hidden');
            $('#optional-lang-label').removeClass('hidden');
        } else {
            updateLineModelOptions();
            $('#transkribus-help').removeClass('hidden');
            $select2.prop('required', true);
            $select2.attr('data-placeholder', '');
            $select2.data('select2').selection.placeholder.text = '';
            $('#optional-lang-label').addClass('hidden');
            $('#transkribus-lang-label').removeClass('hidden');
        }
    });

    // modify selected engine after loading the page with preselected engine
    let $engineRadioFields = $('[name=engine]:checked');
    if($engineRadioFields.val() === 'transkribus') {
        $select2.attr('data-placeholder', '')
    } else {
        $select2.attr('data-placeholder', previousDataPlaceholder);
    }

    const $modal = $('#filterModal');
    const $closeModalBtn = $('#closeModal');
    $closeModalBtn.on('click', e => {       // close modal on close button click
        e.preventDefault();
        $modal.addClass('hidden');
    });
    $modal.on('click', e => {       // close modal if click outside of modal content
        if (e.target === $modal[0]) {
            $modal.addClass('hidden');
        }
    });
    //refresh cancels modal automatically
    window.addEventListener("load", () => {
    $modal.addClass('hidden');
    });

    const $cleanup = $('.cleanup');
    $cleanup.on('click', e => {
        e.preventDefault();
        $modal.removeClass('hidden');
        $cleanup.blur();
    });

    const $applyBtn = $('#applyBtn');
    $applyBtn.on('click', e => {
        e.preventDefault(); 
        const optBasic = $('#optBasic');
        const optOCR = $('#optOCR');
        const optLinebreaks = $('#optLinebreaks');
        //if first time, store original
        const $textarea = $('#text');
        if (!originalTranscript) {
            originalTranscript = $textarea.val();
        }

        const options = {
            basic: optBasic.prop('checked'),
            ocr: optOCR.prop('checked'),
            linebreaks: optLinebreaks.prop('checked')
        };
        if (!options.basic && !options.ocr && !options.linebreaks) {
            // if no options selected, reset to original
            currentTranscript = originalTranscript;
        }
        else {
            // ALWAYS clean from original
            const cleaned = cleanText(originalTranscript, options);
            console.log("Cleaned transcript:", cleaned);
            currentTranscript = cleaned;
        }
        
        $textarea.val(currentTranscript);

        $modal.addClass('hidden');
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

    const $submitBtns = $('.submit-full, .submit-crop')
    $submitBtns.closest('form').on('submit', e => {
        $submitBtns.attr("disabled", true);
        $(".loader").removeClass('hidden');
    });
    // Re-enable submit buttons on pagehide, so that they are re-enabled if returned to via browser history
    $(window).on('pagehide', e => {
        $submitBtns.attr("disabled", false);
        $(".loader").addClass('hidden');
    });

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
        $('.submit-full').on('click', e => {
            x.value = null;
            y.value = null;
            width.value = null;
            height.value = null;
        });
    }
});
