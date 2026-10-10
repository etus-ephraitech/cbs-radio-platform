<?php

namespace App\Models\Traits;

/**
 * Generates a unique slug from a name, appending -2, -3, etc. on
 * collision. Used by any Model whose table has a `slug` column meant to
 * be derived automatically rather than typed by hand (CategoryModel,
 * GiftVendorModel). Requires the using class to be a CodeIgniter Model
 * (relies on $this->where()/first()).
 */
trait GeneratesUniqueSlugs
{
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        helper('text');

        $base = cbs_slug($name);
        $slug = $base;
        $i    = 2;

        while (true) {
            $query = $this->where('slug', $slug);

            if ($excludeId !== null) {
                $query->where('id !=', $excludeId);
            }

            if ($query->first() === null) {
                return $slug;
            }

            $slug = $base . '-' . $i;
            $i++;
        }
    }
}
