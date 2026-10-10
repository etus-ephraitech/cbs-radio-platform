<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVendorFieldsToGiftsTable extends Migration
{
    private const FK_NAME    = 'fk_gifts_vendor_id';
    private const INDEX_NAME = 'idx_gifts_vendor_id';

    public function up()
    {
        // 1. New columns — each guarded so a half-applied earlier run
        //    (columns committed, migration never recorded) can't fail
        //    with "duplicate column name" on the next attempt.
        $columns = [
            'source' => [
                'type'       => 'ENUM',
                'constraint' => ['local', 'vendor'],
                'default'    => 'local',
                'null'       => false,
                'after'      => 'type',
            ],
            'vendor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'source',
            ],
            // The vendor's own product identifier — used to match an
            // existing row on re-sync instead of duplicating it.
            'vendor_product_ref' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'vendor_id',
            ],
            'synced_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'vendor_product_ref',
            ],
        ];

        $missing = array_filter(
            $columns,
            fn(string $field): bool => ! $this->db->fieldExists($field, 'gifts'),
            ARRAY_FILTER_USE_KEY
        );

        if ($missing !== []) {
            $this->forge->addColumn('gifts', $missing);
        }

        // 2. Widen `type` to cover vendor-supplied physical items (flowers,
        //    cakes, hampers, merchandise) alongside the existing digital types.
        //    Done before the keys: on SQLite modifyColumn() rebuilds the table.
        $this->forge->modifyColumn('gifts', [
            'type' => [
                'name'       => 'type',
                'type'       => 'ENUM',
                'constraint' => ['ecard', 'airtime', 'shoutout', 'dedication', 'physical'],
                // modifyColumn() redefines the whole column, so NOT NULL must
                // be restated or the column silently becomes nullable.
                'null'       => false,
            ],
        ]);

        // 3. Index + foreign key through Forge. processIndexes() issues
        //    ALTER TABLE ... ADD CONSTRAINT on MySQL and rebuilds the table
        //    on SQLite (which has no ADD CONSTRAINT), and applies the table
        //    prefix itself — no raw SQL needed.
        //    SQLite can't store constraint names, so CI4 throws if one is
        //    passed there; it generates its own name instead.
        $fkName = $this->isSQLite() ? '' : self::FK_NAME;

        $this->forge->addKey('vendor_id', false, false, self::INDEX_NAME);
        $this->forge->addForeignKey('vendor_id', 'gift_vendors', 'id', 'CASCADE', 'SET NULL', $fkName);
        $this->forge->processIndexes('gifts');
    }

    public function down()
    {
        // Order matters on MySQL: the FK must go before the index it relies
        // on, and both before the column. On SQLite Forge rebuilds the table
        // for each step, so the same order works there too.
        $fkName = $this->isSQLite() ? $this->findForeignKeyName('gifts', 'vendor_id') : self::FK_NAME;

        if ($fkName !== null) {
            $this->forge->dropForeignKey('gifts', $fkName);
        }

        // Third argument false: the index was created with an explicit,
        // unprefixed name, so don't let dropKey() prepend DBPrefix.
        $this->forge->dropKey('gifts', self::INDEX_NAME, false);

        $this->forge->dropColumn('gifts', ['source', 'vendor_id', 'vendor_product_ref', 'synced_at']);

        $this->forge->modifyColumn('gifts', [
            'type' => [
                'name'       => 'type',
                'type'       => 'ENUM',
                'constraint' => ['ecard', 'airtime', 'shoutout', 'dedication'],
                'null'       => false,
            ],
        ]);
    }

    private function isSQLite(): bool
    {
        return $this->db->DBDriver === 'SQLite3';
    }

    /**
     * SQLite foreign keys carry a CI-generated name rather than ours, so
     * resolve it from the table's actual FK metadata instead of guessing
     * the format. Returns null if no FK covers the column.
     */
    private function findForeignKeyName(string $table, string $column): ?string
    {
        foreach ($this->db->getForeignKeyData($table) as $name => $fk) {
            if (in_array($column, (array) $fk->column_name, true)) {
                return $fk->constraint_name ?? (string) $name;
            }
        }

        return null;
    }
}
