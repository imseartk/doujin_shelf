<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrders extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('orders')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'shop_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'active',
                ],
                'note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('shop_id');
            $this->forge->addKey('status');
            $this->forge->addKey('created_at');
            $this->forge->createTable('orders');
        }

        if (! $this->db->fieldExists('order_id', 'books')) {
            $this->forge->addColumn('books', [
                'order_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'status',
                ],
            ]);
            $this->db->query('ALTER TABLE `books` ADD INDEX `books_order_id` (`order_id`)');
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('order_id', 'books')) {
            $this->forge->dropColumn('books', 'order_id');
        }

        $this->forge->dropTable('orders', true);
    }
}
