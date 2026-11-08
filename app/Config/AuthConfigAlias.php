<?php

/**
 * Makes ephraitech/ci4-auth use this application's Config\Auth.
 *
 * The package asks for config(\Ephraitech\Auth\Config\Auth::class). CodeIgniter (4.4 and later) swaps in an
 * app's own Config\<Name> only for a short, un-namespaced name, so without this alias every setting in
 * app/Config/Auth.php was silently ignored: registration was open to anyone, staff landed on the public homepage
 * after signing in, and the account pages were the package's own.
 *
 * Listed in Config\Autoload::$files, so it runs right after the autoloader starts: before anything asks for the
 * auth config, for web requests, spark commands and tests alike. (Config\Events loads too late: the config may
 * already have been made by then.)
 */

use CodeIgniter\Config\Factories;

Factories::define('config', \Ephraitech\Auth\Config\Auth::class, \Config\Auth::class);
