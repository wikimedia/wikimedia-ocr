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
* `symfony serve` to start the application
* `npm run dev-server` if you need to make JS/CSS changes.
  * Stop the dev-server and run `npm run build` before committing.
