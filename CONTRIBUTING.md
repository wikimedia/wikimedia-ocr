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
  * Enable the Cloud Vision API at https://console.cloud.google.com/apis/api/vision.googleapis.com/overview
  * Create a new Google service account at https://console.cloud.google.com/iam-admin/serviceaccounts Google gives you 1,000 free lookups per month.
  * Give the service account the *Compute Engine Service Account* role.
  * Add a new key for the service account, and download the key's JSON file. Nothing needs to be changed in this file.
  * Add the path of that file to your `.env.local` as `APP_GOOGLE_KEYFILE`.
* Install [Tesseract](https://tesseract-ocr.github.io) and make sure it's in your `$PATH`
* `symfony serve` to start the application
* `npm run dev-server` if you need to make JS/CSS changes.
  * Stop the dev-server and run `npm run build` before committing.


Docker Developer Environment
============================

_(beta: this is a very raw setup and needs improvements)_

### Requirements

  - [Docker installation instructions][docker-install]

[docker-install]: https://docs.docker.com/install/

### Quickstart

Setup container
```
./docker/setup.sh
```

Run container
```
./docker/run.sh
```
