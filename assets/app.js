// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import 'select2';

const $ = require('jquery');
const $select2 = $('#lang');
var selectedLanguages = [];
const $lineDetectionSelect = $('#line-id');
var lineModels = null;

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

    // --- Image workspace (crop, rotate, OCR results) ---
    var $ocrOutputDiv = $('.ocr-output');
    const img = document.getElementById('source-image');
    const x = document.querySelector('[name="crop[x]"]');
    const y = document.querySelector('[name="crop[y]"]');
    const width = document.querySelector('[name="crop[width]"]');
    const height = document.querySelector('[name="crop[height]"]');
    const rotate = document.getElementById('rotate');
    const $modeButtons = $('.drag-mode');
    let cropperInstance = null;

    /**
     * Initialize (or re-initialize) Cropper.js on the source image.
     */
    function initCropper() {
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }

        new Cropper(img, {
            viewMode: 2,
            dragMode: 'move',
            toggleDragModeOnDblclick: false,
            autoCrop: width.value > 0 && height.value > 0,
            responsive: true,
            ready () {
                cropperInstance = this.cropper;

                // Make textarea match height of image.
                $('#text').css({
                    height: this.cropper.getContainerData().height,
                });
                // React to changes in the crop-mode buttons.
                $modeButtons.off('click.cropmode').on('click.cropmode', event => {
                    const $button = $(event.currentTarget);
                    $modeButtons.removeClass('active');
                    $button.addClass('active');
                    this.cropper.setDragMode($button.data('drag-mode'));
                });
                // Initialize rotation if present
                if (rotate && rotate.value && rotate.value !== '0') {
                    this.cropper.rotate(Number.parseFloat(rotate.value));
                }

                // Update rotation value from cropper
                const updateRotationValue = () => {
                    if (rotate && cropperInstance) {
                        const currentRotate = cropperInstance.getData().rotate || 0;
                        // Normalize to 0-359 range
                        const normalizedRotate = ((currentRotate % 360) + 360) % 360;
                        rotate.value = Math.round(normalizedRotate);
                    }
                };

                // Rotation buttons
                $('.rotate-left').off('click.rotate').on('click.rotate', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (cropperInstance && typeof cropperInstance.rotate === 'function') {
                        cropperInstance.rotate(-90);
                        updateRotationValue();
                    }
                });

                $('.rotate-right').off('click.rotate').on('click.rotate', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (cropperInstance && typeof cropperInstance.rotate === 'function') {
                        cropperInstance.rotate(90);
                        updateRotationValue();
                    }
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
                // Only enable the crop-submit button when a real crop area has been drawn by the user.
                if (event.detail.width > 0 && event.detail.height > 0 && cropperInstance && cropperInstance.cropped) {
                    $('.btn.submit-crop').attr('disabled', false).removeClass('disabled');
                    $modeButtons.attr('disabled', false);
                }
            }
        });
    }

    // Initialize cropper when image loads (works for both server-rendered and JS-set src).
    $(img).on('load', function() {
        $ocrOutputDiv.removeClass('hidden');
        initCropper();
    });

    // If image is already loaded (cached), initialize immediately.
    if (img.src && img.complete && img.naturalWidth > 0) {
        initCropper();
    }

    // Hide output section on image load error (only when no OCR text present).
    $(img).on('error', function() {
        if (!$('#text').val()) {
            $ocrOutputDiv.addClass('hidden');
        }
    });

    // When the image URL input changes, update the source image.
    $('[name=image]').on('input', function() {
        const url = $(this).val();
        if (url) {
            // Reset crop and rotation for new image.
            x.value = '';
            y.value = '';
            width.value = '';
            height.value = '';
            if (rotate) {
                rotate.value = 0;
            }
            // Clear any existing OCR text.
            $('#text').val('');
            $('.copy-button').addClass('hidden');

            // Destroy existing cropper before changing src.
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }
            img.src = url;
            // The 'load' event will show the output div and init cropper.
        } else {
            $ocrOutputDiv.addClass('hidden');
        }
    });

    // When submitting the main 'transcribe' button, do not send crop dimensions.
    // Rotation is preserved — the user intentionally set it.
    $('.submit-full').on('click', e => {
        x.value = null;
        y.value = null;
        width.value = null;
        height.value = null;
    });
});
