<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettingChangesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'setting_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'action'      => ['type' => 'ENUM', 'constraint' => ['set', 'reset'], 'default' => 'set'],
            // The effective value before and after. Secrets are never settings,
            // so nothing sensitive can end up here.
            'old_value'   => ['type' => 'TEXT', 'null' => true],
            'new_value'   => ['type' => 'TEXT', 'null' => true],
            'changed_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('setting_key');
        $this->forge->addKey('changed_by');
        $this->forge->addForeignKey('changed_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('setting_changes');
    }

    public function down()
    {
        $this->forge->dropTable('setting_changes');
    }
}
