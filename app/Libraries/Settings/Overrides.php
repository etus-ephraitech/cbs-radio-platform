<?php

namespace App\Libraries\Settings;

/**
 * Process-wide switch used to read a Config class's *base* values — what
 * .env and the code defaults give it, before any admin override is laid on
 * top. The settings screen needs those to show "default: ..." beside a
 * customised value.
 */
final class Overrides
{
    public static bool $suspended = false;
}
