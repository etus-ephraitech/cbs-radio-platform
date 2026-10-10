<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGiftVendorsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'api_base_url' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // The vendor's actual API key lives in .env, keyed by slug
            // (vendor.{slug}.apiKey) — never stored in this table.
            'is_active'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('gift_vendors', true);
    }

    public function down()
    {
        $this->forge->dropTable('gift_vendors', true);
    }
}
