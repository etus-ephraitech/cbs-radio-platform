<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVendorFieldsToGiftsTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('gifts', [
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
        ]);

        // Widen `type` to cover vendor-supplied physical items (flowers,
        // cakes, hampers, merchandise) alongside the existing digital types.
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

        // Forge's addForeignKey() is reliable when building a table from
        // scratch but not for altering one that already exists — raw SQL
        // is the unambiguous way to add a constraint here.
        $this->db->query('ALTER TABLE gifts ADD INDEX idx_gifts_vendor_id (vendor_id)');
        $this->db->query(
            'ALTER TABLE gifts ADD CONSTRAINT fk_gifts_vendor_id '
            . 'FOREIGN KEY (vendor_id) REFERENCES gift_vendors(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE gifts DROP FOREIGN KEY fk_gifts_vendor_id');
        $this->db->query('ALTER TABLE gifts DROP INDEX idx_gifts_vendor_id');

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
}
