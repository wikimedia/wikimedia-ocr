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

You can [create a free account](https://readcoop.eu/transkribus/?sc=Transkribus) for Transkribus, and get a small number of free credits.

To connect the OCR Tool to your account, run the following CLI command to receive an access token for the Transkribus API:

```console
$ ./bin/console app:transkribus
```

It will ask for the *username* and *password* of your Transkribus account, and will output an access token and refresh token.
All of these should be stored in your `.env.local` file:

```dotenv
APP_TRANSKRIBUS_USERNAME=
APP_TRANSKRIBUS_PASSWORD=
APP_TRANSKRIBUS_ACCESS_TOKEN=
APP_TRANSKRIBUS_REFRESH_TOKEN=
```

(The latter two will be removed after [T346306](https://phabricator.wikimedia.org/T346306) is resolved, but for now must be set manually.)

**Note**: You will require sufficient credits in your account to use the Transkribus API.

## Run the application ##
* `symfony serve` to start the application
* `npm run watch` if you need to make JS/CSS changes. Compiled assets are not committed.

## Using Redis for caching

The application caches some data.
In development this is done on the filesystem (in the `var/cache/dev/pools/` directory),
and in production in Redis
(the [Toolforge installation](https://wikitech.wikimedia.org/wiki/Help:Toolforge/Redis_for_Toolforge)).

To test the Redis configuration locally, open an SSH tunnel to Toolforge's Redis server:

```console
$ ssh -N -L 6379:redis.svc.tools.eqiad1.wikimedia.cloud:6379 login.toolforge.org
```

And set the following in `.env.local`:

```dotenv
APP_ENV=prod
REDIS_HOST=localhost
```

Then clear the application cache with

```console
$ ./bin/console c:c
```

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
