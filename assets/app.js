// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import 'select2';

const $ = require('jquery');

$(function () {
    // Initiate Select2, which allows dynamic entry of languages.
    $('#lang').select2({
        theme: 'bootstrap',
    });

    // Show engine-specific options.
    // TODO: Update the Select2 options with available languages.
    $('#engine').on('change',  e => {
        $('.engine-options').addClass('hidden');
        $(`#${e.target.value}-options`).removeClass('hidden');
    }).trigger('change');

    // For the result page. Makes the 'Copy' button copy the transcription to the clipboard.
    const $copyButton = $('#copy-button');
    if ($copyButton.length) {
        $copyButton.on('click', () => {
            const $textarea = $('#text');
            $textarea.select();
            document.execCommand('copy');
            $copyButton.text($copyButton.data('copied-text'));
        });
    }
});
