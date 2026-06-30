<?php

namespace App\Controllers;

class C108 extends BaseController
{
    private const PER_PAGE = 100;

    public function index(): string
    {
        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $day = trim((string) $this->request->getGet('day'));
        $relation = trim((string) $this->request->getGet('relation'));
        $priority = trim((string) $this->request->getGet('priority'));
        $page = max(1, (int) $this->request->getGet('page'));

        $builder = $this->baseBuilder($db);
        $this->applyFilters($builder, $q, $day, $relation, $priority);

        $countBuilder = $this->baseBuilder($db, true);
        $this->applyFilters($countBuilder, $q, $day, $relation, $priority);

        $total = (int) $countBuilder->get()->getRow('total');
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = $builder
            ->orderBy('c108.day', 'ASC')
            ->orderBy('c108.map_id', 'ASC')
            ->orderBy('c108.block_id', 'ASC')
            ->orderBy('c108.space_no', 'ASC')
            ->orderBy('c108.space_no_sub', 'ASC')
            ->limit(self::PER_PAGE, $offset)
            ->get()
            ->getResultArray();

        return view('c108/index', [
            'rows' => $rows,
            'summary' => $this->summary($db),
            'q' => $q,
            'day' => $day,
            'relation' => $relation,
            'priority' => $priority,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
            'perPage' => self::PER_PAGE,
        ]);
    }

    private function baseBuilder($db, bool $countOnly = false)
    {
        $builder = $db->table('c108_circles c108')
            ->join('circles c', 'c.webcatalog_circle_id = c108.wcid', 'left');

        if ($countOnly) {
            return $builder->select('COUNT(*) AS total', false);
        }

        return $builder
            ->select('c108.*')
            ->select('c.id AS local_circle_id, c.name AS local_circle_name, c.is_tracked, c.priority, c.note, c.webcatalog_cut_url');
    }

    private function applyFilters($builder, string $q, string $day, string $relation, string $priority): void
    {
        if ($q !== '') {
            $builder->groupStart()
                ->like('c108.circle_name', $q)
                ->orLike('c108.circle_kana', $q)
                ->orLike('c108.pen_name', $q)
                ->orLike('c108.position_label', $q)
                ->orLike('c.note', $q)
                ->groupEnd();
        }

        if ($day === '1' || $day === '2') {
            $builder->where('c108.day', (int) $day);
        }

        if ($relation === 'known') {
            $builder->where('c.id IS NOT NULL', null, false);
        } elseif ($relation === 'tracked') {
            $builder->where('c.is_tracked', 1);
        } elseif ($relation === 'unknown') {
            $builder->where('c.id IS NULL', null, false);
        }

        if (in_array($priority, ['normal', 'high', 'must'], true)) {
            $builder->where('c.priority', $priority);
        }
    }

    private function summary($db): array
    {
        $row = $db->table('c108_circles c108')
            ->select('COUNT(*) AS total', false)
            ->select('SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) AS known_count', false)
            ->select('SUM(CASE WHEN c.is_tracked = 1 THEN 1 ELSE 0 END) AS tracked_count', false)
            ->join('circles c', 'c.webcatalog_circle_id = c108.wcid', 'left')
            ->get()
            ->getRowArray();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'known' => (int) ($row['known_count'] ?? 0),
            'tracked' => (int) ($row['tracked_count'] ?? 0),
        ];
    }
}
