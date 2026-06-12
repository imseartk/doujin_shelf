<?php

namespace App\Controllers;

use App\Models\CircleModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Circles extends BaseController
{
    private const PRIORITY_OPTIONS = [
        'normal' => '普通',
        'high' => '優先',
        'must' => '必看',
    ];

    public function index(): string
    {
        $q = trim((string) $this->request->getGet('q'));
        $tracked = trim((string) $this->request->getGet('tracked'));
        $priority = trim((string) $this->request->getGet('priority'));
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = 30;
        $db = db_connect();

        $builder = $db->table('circles c')
            ->select('c.*')
            ->select('COUNT(b.id) AS book_count', false)
            ->select("SUM(CASE WHEN b.status = 'wishlist' THEN 1 ELSE 0 END) AS wishlist_count", false)
            ->join('books b', 'b.circle_id = c.id', 'left');

        if ($q !== '') {
            $builder->groupStart()
                ->like('c.name', $q)
                ->orLike('c.name_kana', $q)
                ->orLike('c.note', $q)
                ->groupEnd();
        }

        if ($tracked === '1' || $tracked === '0') {
            $builder->where('c.is_tracked', (int) $tracked);
        }

        if ($priority !== '' && array_key_exists($priority, self::PRIORITY_OPTIONS)) {
            $builder->where('c.priority', $priority);
        }

        $countBuilder = $db->table('circles c')->select('COUNT(*) AS total', false);
        if ($q !== '') {
            $countBuilder->groupStart()
                ->like('c.name', $q)
                ->orLike('c.name_kana', $q)
                ->orLike('c.note', $q)
                ->groupEnd();
        }
        if ($tracked === '1' || $tracked === '0') {
            $countBuilder->where('c.is_tracked', (int) $tracked);
        }
        if ($priority !== '' && array_key_exists($priority, self::PRIORITY_OPTIONS)) {
            $countBuilder->where('c.priority', $priority);
        }

        $total = (int) $countBuilder->get()->getRow('total');
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $circles = $builder
            ->groupBy('c.id')
            ->orderBy('c.is_tracked', 'DESC')
            ->orderBy("FIELD(c.priority, 'must', 'high', 'normal')", '', false)
            ->orderBy('c.name', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return view('circles/index', [
            'circles' => $circles,
            'q' => $q,
            'tracked' => $tracked,
            'priority' => $priority,
            'priorityOptions' => self::PRIORITY_OPTIONS,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $circleModel = new CircleModel();
        if (! $circleModel->find($id)) {
            return redirect()->to('/circles')->with('error', '找不到這個社團。');
        }

        $circleModel->update($id, [
            'name_kana' => $this->nullablePost('name_kana'),
            'priority' => $this->priorityPost(),
            'pixiv_url' => $this->nullablePost('pixiv_url'),
            'twitter_url' => $this->nullablePost('twitter_url'),
            'website_url' => $this->nullablePost('website_url'),
            'booth_url' => $this->nullablePost('booth_url'),
            'melonbooks_url' => $this->nullablePost('melonbooks_url'),
            'toranoana_url' => $this->nullablePost('toranoana_url'),
            'note' => $this->nullablePost('note'),
        ]);

        return redirect()->to($this->safeReturnTo())->with('message', '已更新社團資料。');
    }

    public function toggleTrack(int $id): ResponseInterface
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => 'not found',
                'csrf' => csrf_hash(),
            ]);
        }

        $isTracked = empty($circle['is_tracked']) ? 1 : 0;
        $circleModel->update($id, ['is_tracked' => $isTracked]);

        return $this->response->setJSON([
            'is_tracked' => $isTracked,
            'label' => $isTracked ? '追蹤中' : '未追蹤',
            'csrf' => csrf_hash(),
        ]);
    }

    private function priorityPost(): string
    {
        $priority = (string) $this->request->getPost('priority');
        return array_key_exists($priority, self::PRIORITY_OPTIONS) ? $priority : 'normal';
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function safeReturnTo(): string
    {
        $returnTo = (string) $this->request->getPost('return_to');
        if ($returnTo === '' || str_starts_with($returnTo, '//') || ! str_starts_with($returnTo, '/circles')) {
            return '/circles';
        }

        return $returnTo;
    }
}
