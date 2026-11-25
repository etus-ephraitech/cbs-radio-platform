<?php

/**
 * PHPUnit bootstrap: CodeIgniter's own test bootstrap (paths, environment "testing", autoloading), then the
 * test-only shim that lets audio fixtures behave like HTTP uploads.
 */

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

require __DIR__ . '/_support/overrides/uploaded_file_functions.php';
