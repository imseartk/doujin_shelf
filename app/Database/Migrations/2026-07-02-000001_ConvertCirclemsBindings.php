<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ConvertCirclemsBindings extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('circles') || ! $this->db->tableExists('c108_circles')) {
            return;
        }

        $this->db->query(
            'UPDATE `circles` c
             JOIN `c108_circles` c108 ON c.`webcatalog_circle_id` = c108.`wcid`
             SET c.`webcatalog_circle_id` = c108.`circlems_id`
             WHERE c108.`circlems_id` IS NOT NULL
               AND c108.`circlems_id` <> \'\''
        );

        $this->db->query(
            'UPDATE `c108_circles` c108
             JOIN `circles` c ON c.`webcatalog_circle_id` = c108.`circlems_id`
             SET c108.`circle_id` = c.`id`
             WHERE c108.`circlems_id` IS NOT NULL
               AND c108.`circlems_id` <> \'\''
        );
    }

    public function down(): void
    {
        // This migration changes stored identifiers from event-specific WCID to stable Circle.ms ID.
        // Reversing it would be ambiguous when a circle has multiple event entries.
    }
}
