<?php

use Nawasara\Registry\Search\AssetSearchProvider;
use Nawasara\Registry\Search\OpdSearchProvider;

/*
 * Command-palette search providers contributed by nawasara/registry.
 * Discovered by nawasara/search, which globs each package config/search.php.
 * Each entry is a class implementing Nawasara\Search\Contracts\SearchProvider.
 */
return [
    OpdSearchProvider::class,
    AssetSearchProvider::class,
];
