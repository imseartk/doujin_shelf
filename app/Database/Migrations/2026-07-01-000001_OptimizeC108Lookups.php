<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class OptimizeC108Lookups extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('c108_circles') || ! $this->db->tableExists('circles')) {
            return;
        }

        $this->addIndexIfMissing('circles', 'circles_webcatalog_circle_id', ['webcatalog_circle_id']);
        $this->addIndexIfMissing('c108_circles', 'c108_day_map_filename_idx', ['day', 'map_filename']);
        $this->addIndexIfMissing('c108_circles', 'c108_circlems_id_idx', ['circlems_id']);

        $this->db->query(
            'UPDATE `c108_circles` c108
             JOIN `circles` c ON c.`webcatalog_circle_id` = c108.`wcid`
             SET c108.`circle_id` = c.`id`
             WHERE c108.`circle_id` IS NULL'
        );

        $this->db->query(
            'UPDATE `c108_circles` c108
             JOIN `circles` c ON c.`webcatalog_circle_id` = c108.`circlems_id`
             SET c108.`circle_id` = c.`id`
             WHERE c108.`circle_id` IS NULL'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('c108_circles', 'c108_circlems_id_idx');
        $this->dropIndexIfExists('c108_circles', 'c108_day_map_filename_idx');
        $this->dropIndexIfExists('circles', 'circles_webcatalog_circle_id');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $columnSql = implode('`, `', $columns);
        $this->db->query('ALTER TABLE `' . $table . '` ADD INDEX `' . $indexName . '` (`' . $columnSql . '`)');
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `' . $indexName . '`');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$indexName]);

        return $result->getNumRows() > 0;
    }
}
