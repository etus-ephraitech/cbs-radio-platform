<?php

/**
 * TEST-ONLY. Never loaded outside the test suite (see tests/bootstrap.php).
 *
 * CodeIgniter's UploadedFile::isValid() and ::move() call PHP's is_uploaded_file() and move_uploaded_file(), which
 * refuse any file that did not arrive through a real HTTP upload. A test cannot make one, so every voice-note test
 * would stop at "didn't upload properly" and never reach the logic being tested.
 *
 * PHP resolves an unqualified function call inside a namespace to a function of the same name in that namespace
 * before the global one. UploadedFile lives in CodeIgniter\HTTP\Files and calls both functions unqualified, so
 * defining them here makes a fixture file count as an upload for the tests, and for nothing else.
 */

namespace CodeIgniter\HTTP\Files;

if (! function_exists(__NAMESPACE__ . '\is_uploaded_file')) {
    function is_uploaded_file(string $filename): bool
    {
        return is_file($filename);
    }
}

if (! function_exists(__NAMESPACE__ . '\move_uploaded_file')) {
    function move_uploaded_file(string $from, string $to): bool
    {
        // The tests hand over a temporary copy of the fixture, so moving (not copying) is safe and mirrors PHP.
        return rename($from, $to);
    }
}
