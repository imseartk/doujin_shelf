<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddC108UpdateNoticeFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('c108_circles')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('update_notice_text', 'c108_circles')) {
            $fields['update_notice_text'] = [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'imported_at',
            ];
        }

        if (! $this->db->fieldExists('update_detected_at', 'c108_circles')) {
            $fields['update_detected_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'update_notice_text',
            ];
        }

        if (! $this->db->fieldExists('update_read', 'c108_circles')) {
            $fields['update_read'] = [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 0,
                'after' => 'update_detected_at',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('c108_circles', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('c108_circles')) {
            return;
        }

        foreach (['update_read', 'update_detected_at', 'update_notice_text'] as $field) {
            if ($this->db->fieldExists($field, 'c108_circles')) {
                $this->forge->dropColumn('c108_circles', $field);
            }
        }
    }
}
