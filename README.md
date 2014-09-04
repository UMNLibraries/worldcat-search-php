# worldcat-search-php

A PHP client package for the WorldCat Search API.

## Installing

Install via [Composer](http://getcomposer.org). In your project's `composer.json`:

```json
  "require": {
    "umnlib/worldcat-search": "1.0.*"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:UMNLibraries/worldcat-search-php.git"
    }
  ]
```

## OCLC Authenticaton

This package uses [OCLC Authentication and Authorization](http://www.oclc.org/developer/develop/authentication.en.html), which requires a [WSKey](https://platform.worldcat.org/wskey/).

## Running the Tests

Running the PHPUnit tests requires configuration. Notice that `phpunit.xml.dist` contains a place to put `your-wskey-here`. Do not modify that file! Instead, copy the file to `phpunit.xml`, which will override `phpunit.xml.dist`, and insert your WSKey into that file. This repository is configured to ignore `phpunit.xml`, which helps to prevent exposing passwords, like WSKeys, to public source control repositories.

## TODO

The `RequestIterator` and `RequestIteratorGeneric` classes are almost identical. One of them may be unnecessary. There is also a fair amount of probably needless code duplication among classes. Lots of room for improvement in the design architecture.

## Older Versions

For older versions of this package that did not use Composer, see the `0.x.y` releases.
