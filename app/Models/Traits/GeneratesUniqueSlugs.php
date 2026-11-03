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

    /**
     * Saves changes to one row, with its id included in what is validated.
     *
     * Both models check `slug` with `is_unique[table.slug,id,{id}]`, where {id} means "ignore this row's own
     * slug". CodeIgniter fills {id} in only from the data it is validating, and update($id, $data) does not put
     * the id there. Without this, saving a row *with its slug unchanged* is refused as a duplicate of itself:
     * an administrator who edits only a description, or an address, is told nothing and nothing is saved.
     *
     * The id is stripped again before anything is written, because it is not an allowed field. (CodeIgniter also
     * requires the using model to declare a rule for `id` itself, since {id} is a placeholder for that field.)
     */
    public function update($id = null, $row = null): bool
    {
        if (is_array($row) && (is_int($id) || is_string($id)) && ! array_key_exists($this->primaryKey, $row)) {
            $row[$this->primaryKey] = $id;
        }

        return parent::update($id, $row);
    }
}
