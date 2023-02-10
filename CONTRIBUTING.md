## Requirements #

* PHP 7.2+
* [Composer](http://getcomposer.org/)
* [Symfony CLI](https://symfony.com/download)
  
If you need to make asset changes:

* [Node](https://nodejs.org) with the version specified by the `.nvmrc` [nvm](https://github.com/nvm-sh/nvm#installing-and-updating) file.

## Installation ##

* `composer install`
* `npm install`

### For Google Cloud Vision Engine ###

* Add the missing values from `.env` to a `.env.local` file
  * Enable the Cloud Vision API at https://console.cloud.google.com/apis/api/vision.googleapis.com/overview
  * Create a new Google service account at https://console.cloud.google.com/iam-admin/serviceaccounts Google gives you 1,000 free lookups per month.
  * Give the service account the *Compute Engine Service Account* role.
  * Add a new key for the service account, and download the key's JSON file. Nothing needs to be changed in this file.
  * Add the path of that file to your `.env.local` as `APP_GOOGLE_KEYFILE`.

### For Tesseract OCR Engine ###
* Install [Tesseract](https://tesseract-ocr.github.io) and make sure it's in your `$PATH`

### For Transkribus OCR Engine ###
* Run the `app:transkribus` CLI command to receive an access token to use the Transkribus API. 
```bash 
./bin/console app:transkribus
```
It will ask for the *username* and *password* of your Transkribus account. You can create a free account [here](https://readcoop.eu/transkribus/?sc=Transkribus).
* Store the access token in your `.env.local` file as `APP_TRANSKRIBUS_ACCESS_TOKEN` from where it will be used by the Transkribus engine. 
> **Note**: You will require sufficient credits in your account to use the Transkribus API.

## Run the application ##
* `symfony serve` to start the application
* `npm run watch` if you need to make JS/CSS changes. Compiled assets are not committed.

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
