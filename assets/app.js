// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import 'select2';

const $ = require('jquery');

// Makes the "Copy to Clipboard" button work.
$(function () {
    // Initiate Select2, which allows dynamic entry of languages.
    $('#lang').select2({
        tags: true,
        theme: 'bootstrap',
    });

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
