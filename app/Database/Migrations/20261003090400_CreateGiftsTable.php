<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGiftsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'type'        => ['type' => 'ENUM', 'constraint' => ['ecard', 'airtime', 'shoutout', 'dedication']],
            'description' => ['type' => 'TEXT', 'null' => true],
            'price'       => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'currency'    => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'UGX'],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('gifts', true);
    }

    public function down()
    {
        $this->forge->dropTable('gifts', true);
    }
}
