Wikisource Google OCR tool
==========================

This is a simple wrapper service around the Google Cloud Vision API,
enabling Wikisources to submit images for Optical Character Recognition
and retrieve the resultant text.

This works with more languages than the alternative service at https://tools.wmflabs.org/phetools
(used by e.g. https://wikisource.org/wiki/MediaWiki:OCR.js and similar scripts
on other Wikisources).

Some caveats:

* Requests can only come from Wikisources.
* Images must be less than 10 MB.

## Usage

Send two parameters to `index.php`:

    https://tools.wmflabs.org/ws-google-ocr/index.php?lang=[LANG_CODE]&image=[IMAGE_URL]

And get back a JSON response with either 'text' or 'error' top-level items set:

    {
      'text': 'Lorem ipsum...',
      'error': {
        'code': '',
        'message': ''
      }
    }

Note that you should only set the `lang` parameter in some particular cases.
The [documentation](https://cloud.google.com/vision/reference/rest/v1/images/annotate#imagecontext) informs us of the following:

> In most cases, an empty value yields the best results since it enables automatic language detection.
> For languages based on the Latin alphabet, setting languageHints is not needed.
> In rare cases, when the language of the text in the image is known, setting a hint will help get better results
> (although it will be a significant hindrance if the hint is wrong).
> Text detection returns an error if one or more of the specified languages is not one of the supported languages.

## External links

* https://phabricator.wikimedia.org/T142768
* https://github.com/wikisource/google-cloud-vision-php
