Wikisource Google OCR tool
==========================

![CI](https://github.com/wikimedia/wikimedia-ocr/workflows/CI/badge.svg)

Main documentation: https://wikisource.org/wiki/Wikisource:Google_OCR

This is a simple wrapper service around the Google Cloud Vision API,
enabling Wikisources to submit images for Optical Character Recognition
and retrieve the resultant text.

This works with more languages than the alternative service at https://tools.wmflabs.org/phetools
(used by e.g. https://wikisource.org/wiki/MediaWiki:OCR.js and similar scripts
on other Wikisources).

Requests can only be for images hosted on Commons.

## Usage

Send up to two parameters to `api.php`:

    https://example.org/api.php?langs[]=[LANG_CODE_1]&langs[]=[LANG_CODE_2]&image=[IMAGE_URL]

And get back a JSON response with either 'text' or 'error' top-level items set:

    {
      'text': 'Lorem ipsum...',
      'error': {
        'code': '',
        'message': ''
      }
    }

### Languages

#### Google

Note that you should only set the `lang` parameter for languages that require it.
The [documentation](https://cloud.google.com/vision/reference/rest/v1/images/annotate#imagecontext) informs us of the following:

> In most cases, an empty value yields the best results since it enables automatic language detection.
> For languages based on the Latin alphabet, setting languageHints is not needed.
> In rare cases, when the language of the text in the image is known, setting a hint will help get better results
> (although it will be a significant hindrance if the hint is wrong).
> Text detection returns an error if one or more of the specified languages is not
> one of the [supported languages](https://cloud.google.com/vision/docs/languages).

#### Tesseract

Languages supported by Tesseract are [listed in the user manual](https://tesseract-ocr.github.io/tessdoc/Data-Files-in-different-versions.html).

## Contributing

See [CONTRIBUTING.md](https://github.com/wikimedia/wikimedia-ocr/blob/main/CONTRIBUTING.md)

## External links

* https://phabricator.wikimedia.org/T142768
* https://github.com/wikisource/google-cloud-vision-php
