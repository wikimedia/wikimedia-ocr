// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import 'select2';

const $ = require('jquery');
const $select2 = $('#lang');

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
        $select2.trigger({
            type: 'select2:select',
            params: { data }
        });
    });
}

$(function () {
    // Initiate Select2, which allows dynamic entry of languages.
    $select2.select2({
        theme: 'bootstrap',
        placeholder: $select2.data('placeholder'),
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
        $copyButton.on('click', () => {
            const $textarea = $('#text');
            $textarea.select();
            document.execCommand('copy');
            $copyButton.text($copyButton.data('copied-text'));
        });
    }
});
