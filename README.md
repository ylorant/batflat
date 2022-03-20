Batflat
=======

Batflat was created as a lightweight alternative to heavy and outdated CMS.
Many people use complex solutions for simple pages, unnecessarily. Building this content management system, we focused on simplicity - even novice webmaster adapt his template and writes his own module. To achieve this, we implemented a simple template system and trivial application architecture.

Batflat does not require MySQL database, because all the data are collected in a single file. This provides perfect portability when changing your hosting provider. Just copy all the files from one account to another. That's all. There's nothing to configure or to change. However, if you SQLite does not meet your requirements, you can quickly change the database type thanks to PDO.

What's more, Batflat does not have installation wizard, because there is no such need. Right after uploading a package to your server, a simple "composer install" will be enough ;)

Each page can have it's own individual name and URL, that makes Batflat SEO friendly. Your site may be available in multiple languages. Currently Batflat supports translation to Polish, English, French, Turkish, Swedish, Russian, Italian, Spanish, Dutch and Indonesian.

Control panel and the default template is fully responsive, which makes it accessible from any mobile device, even on the phone thanks to used CSS framework - Bootstrap. Each of our module is adapted to it.

This fork is intended to be JQuery-free and use last PHP improvements as possible.

## Why this fork ?

The [original project](https://github.com/sruupl/batflat) is sadly not really updated : old Bootstrap version, annoying bugs, etc ...
Years ago, we decided to use it to redo our [speedrun association](https://speedthemall.com) website with Batflat.
But more we need new features, more it was painful.

So i decided first to upgrade to Bootstrap 4. then Bootstrap 5. Then PHP 7+ ...
And one day, i realized it was too much than "few modifications".
So here we come !

## Project page

https://github.com/RomainOdeval/batflat

## Contributing

I continue to clean this fork before i will set a "1.0" version.
But help will be very appreciated.
Only you have to do is to open [pull requests](https://github.com/RomainOdeval/batflat/pulls) following those [rules](CONTRIBUTING.md).

## Tutorials
* [Setup Batflat on Ubuntu with Nginx](https://websiteforstudents.com/setup-batflat-on-ubuntu-18-04-16-04-18-10-with-nginx-mariadb-and-php-7-2-fpm/)

## Credits

* **[Eztharia](https://github.com/Eztharia)** - French translation of website and CMS
* **[Ladeyshchikov Valery](mailto:hizimart@gmail.com)** - Russian translation of CMS
* **Artem Sharovatov** - Russian translation of website
* **[Birkan Ergüç](https://github.com/pppedant)** - Turkish translation of CMS
* **[Michael Thell](mailto:michael.silverunit@gmail.com)** - Swedish translation of CMS
* **[Giuseppe Marino](mailto:info@gpmdev.it)** - Italian translation of CMS
* **[Javier Igal](mailto:javier@igal.es)** - Spanish translation of website and CMS
* **[RJ Adriaansen](https://github.com/rjadr)** - Dutch translation of CMS
* **[Komputronika](https://github.com/komputronika)** - Indonesian translation of CMS
* **[Renato Frota](https://github.com/renatofrota)** - Portuguese translation of CMS
