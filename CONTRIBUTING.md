## Requirements #

* PHP 7.2+
* [Composer](http://getcomposer.org/)
* [Symfony CLI](https://symfony.com/download)
  
If you need to make asset changes:

* [Node](https://nodejs.org) with the version specified by the `.nvmrc` [nvm](https://github.com/nvm-sh/nvm#installing-and-updating) file.

## Installation ##

* `composer install`
* `npm install`
* Add the missing values from `.env` to a `.env.local` file
  * Use `https://vision.googleapis.com/` as the `APP_GOOGLE_CLOUD_VISION_ENDPOINT`, with your own [Cloud Vision API](https://cloud.google.com/vision) key as the `APP_GOOGLE_CLOUD_VISION_KEY`. Google gives you 1,000 free lookups per month.
* `symfony serve` to start the application
* `npm run dev-server` if you need to make JS/CSS changes.
  * Stop the dev-server and run `npm run build` before committing.
