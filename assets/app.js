// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// Makes the "Copy to Clipboard" button work.
document.addEventListener("DOMContentLoaded", () => {
    const copyButton = document.getElementById('copy-button');
    copyButton.addEventListener('click', () => {
        const textarea = document.getElementById('text');
        textarea.select();
        document.execCommand('copy');
        copyButton.innerText = copyButton.dataset.copiedText;
    });
});
