<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMessageTemplatesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // NULL means "offer this for any occasion".
            'category_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // Free text on purpose (English, Luganda, ...): the staff who write the
            // templates decide which languages exist, not the code.
            'language'    => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'English'],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 80],
            // May contain {recipient} and {sender}, filled in from the booking form.
            'body'        => ['type' => 'TEXT'],
            'sort_order'  => ['type' => 'INT', 'constraint' => 5, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('category_id');
        $this->forge->addKey('is_active');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('message_templates',true);
    }

    public function down()
    {
        $this->forge->dropTable('message_templates', true);
    }
}
