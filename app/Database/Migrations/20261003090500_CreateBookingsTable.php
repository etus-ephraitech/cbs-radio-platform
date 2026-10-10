<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBookingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'category_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'rate_tier_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'show_slot_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],

            // Sender (no account required — guest submission)
            'sender_name'   => ['type' => 'VARCHAR', 'constraint' => 150],
            'sender_phone'  => ['type' => 'VARCHAR', 'constraint' => 20],
            'sender_email'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],

            'recipient_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'message_text'   => ['type' => 'TEXT'],

            // Voice note (optional, 15-30s cap enforced in app layer)
            'voice_note_path'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'voice_note_duration_seconds' => ['type' => 'INT', 'constraint' => 3, 'null' => true],

            'scheduled_date' => ['type' => 'DATE', 'null' => true],

            'status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'pending_payment',
                    'pending_moderation',
                    'approved',
                    'rejected',
                    'aired',
                    'cancelled',
                ],
                'default' => 'pending_payment',
            ],

            'amount'   => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'UGX'],

            'moderated_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'moderation_notes' => ['type' => 'TEXT', 'null' => true],
            'aired_at'         => ['type' => 'DATETIME', 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('category_id');
        $this->forge->addKey('rate_tier_id');
        $this->forge->addKey('show_slot_id');
        $this->forge->addKey('moderated_by');
        $this->forge->addKey('status');
        $this->forge->addKey('sender_phone');

        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('rate_tier_id', 'rate_tiers', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('show_slot_id', 'show_slots', 'id', 'CASCADE', 'SET NULL');
        // Assumes a `users` table (id INT UNSIGNED) already exists from the API
        // starter kit auth system — confirm the actual column type before running.
        $this->forge->addForeignKey('moderated_by', 'users', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('bookings', true);
    }

    public function down()
    {
        $this->forge->dropTable('bookings', true);
    }
}
