<?php

namespace Tests\Support\Libraries;

use Config\Database;

/**
 * A row in the starter kit's `users` table for tests to act as. Bookings, payments, moderation logs and settings
 * all point a foreign key at users.id, so the rows tests create need a user that really exists.
 *
 * This project does not own the `users` table, so it uses the first user already there and, only if there is
 * none, inserts one, filling any required column it can see with a placeholder of the right type, whatever the
 * starter kit's schema turns out to need. The row is never removed: it is not this suite's data.
 */
final class StaffUser
{
    private static ?int $id = null;

    public static function id(): int
    {
        if (self::$id !== null) {
            return self::$id;
        }

        $db = Database::connect('tests');

        $existing = $db->table('users')->select('id')->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();

        if ($existing !== null) {
            return self::$id = (int) $existing['id'];
        }

        $row = [];

        foreach ($db->getFieldData('users') as $field) {
            if (! empty($field->primary_key) || $field->nullable || $field->default !== null) {
                continue;
            }

            $row[$field->name] = self::placeholder((string) $field->type, $field->name);
        }

        $db->table('users')->insert($row);

        return self::$id = (int) $db->insertID();
    }

    private static function placeholder(string $type, string $column): string|int
    {
        $type = strtolower($type);

        return match (true) {
            str_contains($type, 'int')                                  => 1,
            str_contains($type, 'datetime'), str_contains($type, 'timestamp') => date('Y-m-d H:i:s'),
            str_contains($type, 'date')                                 => date('Y-m-d'),
            str_contains($column, 'email')                              => 'staff@example.test',
            default                                                     => 'test-staff',
        };
    }
}
