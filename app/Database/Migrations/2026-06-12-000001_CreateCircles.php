<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCircles extends Migration
{
    public function up(): void
    {
        $this->createCirclesTable();
        $this->addCircleIdToBooks();
        $this->seedCirclesFromBooks();
        $this->backfillBookCircleIds();
    }

    public function down(): void
    {
        if ($this->db->fieldExists('circle_id', 'books')) {
            $this->forge->dropColumn('books', 'circle_id');
        }

        $this->forge->dropTable('circles', true);
    }

    private function createCirclesTable(): void
    {
        if ($this->db->tableExists('circles')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'name_kana' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'is_tracked' => [
                'type'    => 'TINYINT',
                'default' => 0,
            ],
            'priority' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'normal',
            ],
            'pixiv_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'twitter_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'website_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'booth_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'melonbooks_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'toranoana_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'webcatalog_circle_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'note' => [
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
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('name_kana');
        $this->forge->addKey('is_tracked');
        $this->forge->addKey('priority');
        $this->forge->createTable('circles');
    }

    private function addCircleIdToBooks(): void
    {
        if ($this->db->fieldExists('circle_id', 'books')) {
            return;
        }

        $this->forge->addColumn('books', [
            'circle_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'circle',
            ],
        ]);
        $this->db->query('ALTER TABLE `books` ADD INDEX `books_circle_id` (`circle_id`)');
    }

    private function seedCirclesFromBooks(): void
    {
        $books = $this->db->table('books')
            ->select('circle, circle_kana')
            ->where('circle IS NOT NULL', null, false)
            ->where("TRIM(circle) <>", '')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $existingNames = [];
        foreach ($this->db->table('circles')->select('name')->get()->getResultArray() as $circle) {
            $existingNames[$this->nameKey($circle['name'])] = true;
        }

        $now = date('Y-m-d H:i:s');
        $pending = [];

        foreach ($books as $book) {
            $name = trim((string) $book['circle']);
            $nameKey = $this->nameKey($name);

            if ($name === '' || isset($existingNames[$nameKey]) || isset($pending[$nameKey])) {
                continue;
            }

            $pending[$nameKey] = [
                'name'       => $name,
                'name_kana'  => $this->nullableTrim($book['circle_kana'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($pending !== []) {
            $this->db->table('circles')->ignore(true)->insertBatch(array_values($pending));
        }
    }

    private function backfillBookCircleIds(): void
    {
        $circles = $this->db->table('circles')
            ->select('id, name')
            ->get()
            ->getResultArray();

        $circleIdsByName = [];
        foreach ($circles as $circle) {
            $circleIdsByName[$this->nameKey($circle['name'])] = (int) $circle['id'];
        }

        $books = $this->db->table('books')
            ->select('id, circle')
            ->where('circle_id IS NULL', null, false)
            ->where('circle IS NOT NULL', null, false)
            ->where("TRIM(circle) <>", '')
            ->get()
            ->getResultArray();

        foreach ($books as $book) {
            $name = trim((string) $book['circle']);
            $nameKey = $this->nameKey($name);

            if ($name === '') {
                continue;
            }

            $circleId = $circleIdsByName[$nameKey] ?? $this->findCircleIdByName($name);

            if ($circleId === null) {
                continue;
            }

            $this->db->table('books')
                ->where('id', (int) $book['id'])
                ->update(['circle_id' => $circleId]);
        }
    }

    private function findCircleIdByName(string $name): ?int
    {
        $circle = $this->db->table('circles')
            ->select('id')
            ->where('name', $name)
            ->get(1)
            ->getRowArray();

        return $circle === null ? null : (int) $circle['id'];
    }

    private function nameKey(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
