<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRateTiersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'category_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'package_level'    => ['type' => 'VARCHAR', 'constraint' => 100],
            // Free-text summary of what's included (on-air readout, voice note, banner, etc.)
            'features'         => ['type' => 'TEXT', 'null' => true],
            // Whether this tier includes an on-air readout — the Milestone/Achievement
            // tier was flagged as ambiguous on this point in the original proposal;
            // CBS's confirmed answer should be reflected here per tier.
            'includes_on_air'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'price'            => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'currency'         => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'UGX'],
            'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('category_id');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('rate_tiers');
    }

    public function down()
    {
        $this->forge->dropTable('rate_tiers');
    }
}
