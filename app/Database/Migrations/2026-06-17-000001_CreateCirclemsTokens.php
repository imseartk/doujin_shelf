<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCirclemsTokens extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('circlems_tokens')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'access_token' => [
                'type' => 'TEXT',
            ],
            'refresh_token' => [
                'type' => 'TEXT',
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'scope' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'last_tested_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('expires_at');
        $this->forge->createTable('circlems_tokens');
    }

    public function down(): void
    {
        $this->forge->dropTable('circlems_tokens', true);
    }
}
