<?php

namespace App\Controllers;

use App\Libraries\CirclemsClient;
use App\Models\CircleModel;
use App\Models\CirclemsTokenModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

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
            ->select("(SELECT COUNT(*) FROM c108_circles c108 WHERE c108.circle_id = c.id) AS c108_binding_count", false)
            ->join('books b', "b.circle_id = c.id AND b.type <> 'comic'", 'left');

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

    public function circlems(int $id): string
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return view('errors/html/error_404');
        }

        $q = trim((string) ($this->request->getGet('q') ?: $circle['name']));
        $eventId = (int) $this->request->getGet('event_id');
        $page = max(1, (int) $this->request->getGet('page'));
        $events = [];
        $latestEventId = null;
        $candidates = [];
        $error = null;

        try {
            $client = new CirclemsClient();
            $token = $this->currentCirclemsToken();
            if (! $token) {
                throw new RuntimeException('尚未連線 Circle.ms。');
            }

            $token = $this->refreshCirclemsTokenIfNeeded($token, $client);
            $eventList = $client->eventList((string) $token['access_token']);
            $events = $this->eventOptions($eventList);
            $latestEventId = $this->latestEventId($eventList);
            if ($eventId <= 0) {
                $eventId = $latestEventId ?? 0;
            }
            if ($eventId <= 0) {
                throw new RuntimeException('Circle.ms event list did not include a latest event id.');
            }

            if ($q !== '') {
                $result = $client->queryCircle((string) $token['access_token'], $eventId, $q, $page);
                $candidates = $this->circlemsCandidates($result);
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('circles/circlems', [
            'circle' => $circle,
            'q' => $q,
            'eventId' => $eventId,
            'events' => $events,
            'latestEventId' => $latestEventId,
            'page' => $page,
            'candidates' => $candidates,
            'error' => $error,
        ]);
    }

    public function circlemsCandidatesJson(int $id): ResponseInterface
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => '找不到這個社團。',
                'csrf' => csrf_hash(),
            ]);
        }

        $q = trim((string) ($this->request->getGet('q') ?: $circle['name']));
        $eventId = (int) $this->request->getGet('event_id');
        $page = max(1, (int) $this->request->getGet('page'));

        try {
            $data = $this->circlemsCandidateData($circle, $q, $eventId, $page);
        } catch (RuntimeException $exception) {
            return $this->response->setStatusCode(400)->setJSON([
                'message' => $exception->getMessage(),
                'csrf' => csrf_hash(),
            ]);
        }

        return $this->response->setJSON($data + [
            'csrf' => csrf_hash(),
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

    public function c108CandidatesJson(int $id): ResponseInterface
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => '找不到這個社團。',
                'csrf' => csrf_hash(),
            ]);
        }

        $db = db_connect();
        if (! $db->tableExists('c108_circles')) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => '找不到 C108 匯入資料。',
                'csrf' => csrf_hash(),
            ]);
        }

        $q = trim((string) ($this->request->getGet('q') ?: $circle['name']));
        $day = trim((string) $this->request->getGet('day'));
        $page = max(1, (int) $this->request->getGet('page'));
        $limit = 20;

        $builder = $db->table('c108_circles c108')
            ->select('c108.id, c108.wcid, c108.circlems_id, c108.circle_name, c108.circle_kana, c108.pen_name, c108.day, c108.map_name, c108.block_name, c108.space_no, c108.space_no_sub, c108.position_label, c108.book_name, c108.description, c108.webcatalog_cut_url, c108.circle_id')
            ->select('c.name AS local_circle_name', false)
            ->join('circles c', 'c.id = c108.circle_id', 'left');

        if ($q !== '') {
            $this->applyC108CandidateSearch($builder, $q);
        }

        if ($day === '1' || $day === '2') {
            $builder->where('c108.day', (int) $day);
        }

        $rows = $builder
            ->orderBy($this->c108CandidateRankSql($q), '', false)
            ->orderBy('c108.day', 'ASC')
            ->orderBy('c108.map_id', 'ASC')
            ->orderBy('c108.block_id', 'ASC')
            ->orderBy('c108.space_no', 'ASC')
            ->orderBy('c108.space_no_sub', 'ASC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'circle' => [
                'id' => (int) $circle['id'],
                'name' => (string) $circle['name'],
            ],
            'q' => $q,
            'day' => $day,
            'page' => $page,
            'candidates' => array_map([$this, 'c108CandidateRow'], $rows),
            'csrf' => csrf_hash(),
        ]);
    }

    public function bindC108(int $id): ResponseInterface
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => '找不到這個社團。',
                'csrf' => csrf_hash(),
            ]);
        }

        $c108Id = (int) $this->request->getPost('c108_id');
        if ($c108Id <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'message' => '缺少 C108 攤位 ID。',
                'csrf' => csrf_hash(),
            ]);
        }

        $db = db_connect();
        $row = $db->table('c108_circles')
            ->select('id')
            ->where('id', $c108Id)
            ->get()
            ->getRowArray();
        if (! $row) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => '找不到這個 C108 攤位。',
                'csrf' => csrf_hash(),
            ]);
        }

        $db->table('c108_circles')
            ->where('id', $c108Id)
            ->update(['circle_id' => $id]);

        return $this->response->setJSON([
            'message' => '已連動 C108 攤位。',
            'binding_count' => $this->c108BindingCount($id),
            'csrf' => csrf_hash(),
        ]);
    }

    public function bindCirclems(int $id): RedirectResponse|ResponseInterface
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON([
                    'message' => '找不到這個社團。',
                    'csrf' => csrf_hash(),
                ]);
            }

            return redirect()->to('/circles')->with('error', '找不到這個社團。');
        }

        $wcid = trim((string) $this->request->getPost('wcid'));
        $circlemsId = trim((string) $this->request->getPost('circlems_id'));
        if ($circlemsId === '') {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'message' => '缺少 Circle.ms ID，無法綁定社團。',
                    'csrf' => csrf_hash(),
                ]);
            }

            return redirect()->to('/circles/' . $id . '/circlems')->with('error', '缺少 Circle.ms ID，無法綁定社團。');
        }

        $data = [
            'webcatalog_circle_id' => $circlemsId,
        ];

        $cutUrl = trim((string) $this->request->getPost('webcatalog_cut_url'));
        $cutWarning = null;
        if ($cutUrl !== '') {
            try {
                $data['webcatalog_cut_url'] = $this->storeCirclemsCutImage($cutUrl, $circlemsId);
            } catch (RuntimeException $exception) {
                $cutWarning = $exception->getMessage();
            }
        }

        if ((string) $this->request->getPost('import_social') === '1') {
            foreach ($this->circlemsSocialFieldsFromPost() as $field => $value) {
                if ($value !== null && empty($circle[$field])) {
                    $data[$field] = $value;
                }
            }

            $nameKana = trim((string) $this->request->getPost('name_kana'));
            if ($nameKana !== '' && empty($circle['name_kana'])) {
                $data['name_kana'] = $nameKana;
            }
        }

        $circleModel->update($id, $data);
        $this->syncC108CircleId($id, $circlemsId, $wcid);

        $message = '已綁定 Circle.ms 社團。';
        if ($cutWarning !== null) {
            $message .= ' 但圖片下載失敗：' . $cutWarning;
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'message' => $message,
                'circle' => $this->circleRowForJson($circleModel->find($id) ?: []),
                'csrf' => csrf_hash(),
            ]);
        }

        return redirect()->to('/circles/' . $id . '/circlems')->with('message', $message);
    }

    private function priorityPost(): string
    {
        $priority = (string) $this->request->getPost('priority');
        return array_key_exists($priority, self::PRIORITY_OPTIONS) ? $priority : 'normal';
    }

    private function c108CandidateRow(array $row): array
    {
        $space = str_pad((string) (int) ($row['space_no'] ?? 0), 2, '0', STR_PAD_LEFT) . (string) ($row['space_no_sub'] ?? '');
        $position = trim((string) ($row['position_label'] ?? ''));
        if ($position === '') {
            $position = trim((int) ($row['day'] ?? 0) . '日目 ' . (string) ($row['map_name'] ?? '') . ' ' . (string) ($row['block_name'] ?? '') . $space);
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'wcid' => (int) ($row['wcid'] ?? 0),
            'circlems_id' => (string) ($row['circlems_id'] ?? ''),
            'name' => (string) ($row['circle_name'] ?? ''),
            'name_kana' => (string) ($row['circle_kana'] ?? ''),
            'pen_name' => (string) ($row['pen_name'] ?? ''),
            'day' => (int) ($row['day'] ?? 0),
            'position' => $position,
            'book_name' => (string) ($row['book_name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'cut_url' => (string) ($row['webcatalog_cut_url'] ?? ''),
            'local_circle_id' => (int) ($row['circle_id'] ?? 0),
            'local_circle_name' => (string) ($row['local_circle_name'] ?? ''),
        ];
    }

    private function applyC108CandidateSearch($builder, string $q): void
    {
        $terms = $this->c108SearchTerms($q);
        if ($terms === []) {
            return;
        }

        $builder->groupStart();
        foreach ($terms as $term) {
            $builder->orGroupStart()
                ->like('c108.circle_name', $term)
                ->orLike('c108.circle_kana', $term)
                ->orLike('c108.pen_name', $term)
                ->orLike('c108.position_label', $term)
                ->orLike('c108.map_name', $term)
                ->orLike('c108.block_name', $term)
                ->orLike('c108.book_name', $term)
                ->orLike('c108.description', $term)
                ->groupEnd();
        }

        if (preg_match('/([\\p{Hiragana}\\p{Katakana}\\p{Han}A-Za-z])\\s*0*([0-9]{1,3})\\s*([abABａ-ｂ]?)/u', $q, $match)) {
            $builder->orGroupStart()
                ->where('c108.block_name', $match[1])
                ->where('c108.space_no', (int) $match[2]);
            if (! empty($match[3])) {
                $builder->where('c108.space_no_sub', strtolower(strtr($match[3], ['Ａ' => 'a', 'Ｂ' => 'b', 'ａ' => 'a', 'ｂ' => 'b'])));
            }
            $builder->groupEnd();
        }
        $builder->groupEnd();
    }

    private function c108SearchTerms(string $q): array
    {
        $q = trim((string) preg_replace('/[\\s　]+/u', ' ', $q));
        if ($q === '') {
            return [];
        }

        $terms = [$q];
        foreach (preg_split('/[\\s　]+/u', $q) ?: [] as $part) {
            $part = trim($part);
            if (mb_strlen($part) >= 2) {
                $terms[] = $part;
            }
        }

        $length = mb_strlen($q);
        if ($length >= 4) {
            $terms[] = mb_substr($q, 0, $length - 1);
        }
        if ($length >= 6) {
            $terms[] = mb_substr($q, 0, 5);
        }

        return array_values(array_unique(array_filter($terms, static fn (string $term): bool => $term !== '')));
    }

    private function c108CandidateRankSql(string $q): string
    {
        $db = db_connect();
        $terms = $this->c108SearchTerms($q);
        $exact = $terms[0] ?? '';
        $prefix = $terms[1] ?? $exact;

        return sprintf(
            'CASE WHEN c108.circle_name LIKE %s THEN 0 WHEN c108.circle_name LIKE %s THEN 1 WHEN c108.position_label LIKE %s THEN 2 ELSE 9 END',
            $db->escape('%' . $exact . '%'),
            $db->escape('%' . $prefix . '%'),
            $db->escape('%' . $exact . '%')
        );
    }

    private function c108BindingCount(int $circleId): int
    {
        if (! db_connect()->tableExists('c108_circles')) {
            return 0;
        }

        return db_connect()->table('c108_circles')->where('circle_id', $circleId)->countAllResults();
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function syncC108CircleId(int $circleId, string $circlemsId, string $wcid): void
    {
        $db = db_connect();
        if (! $db->tableExists('c108_circles')) {
            return;
        }

        $builder = $db->table('c108_circles')->where('circlems_id', $circlemsId);
        if ($wcid !== '') {
            $builder->orWhere('wcid', $wcid);
        }
        $builder->update(['circle_id' => $circleId]);
    }

    private function circlemsCandidateData(array $circle, string $q, int $eventId, int $page): array
    {
        $client = new CirclemsClient();
        $token = $this->currentCirclemsToken();
        if (! $token) {
            throw new RuntimeException('尚未連線 Circle.ms。');
        }

        $token = $this->refreshCirclemsTokenIfNeeded($token, $client);
        $eventList = $client->eventList((string) $token['access_token']);
        $events = $this->eventOptions($eventList);
        $latestEventId = $this->latestEventId($eventList);
        if ($eventId <= 0) {
            $eventId = $latestEventId ?? 0;
        }
        if ($eventId <= 0) {
            throw new RuntimeException('Circle.ms event list did not include a latest event id.');
        }

        $candidates = [];
        if ($q !== '') {
            $result = $client->queryCircle((string) $token['access_token'], $eventId, $q, $page);
            $candidates = $this->circlemsCandidates($result);
        }

        return [
            'circle' => [
                'id' => (int) $circle['id'],
                'name' => (string) $circle['name'],
                'circlems_id' => (string) ($circle['webcatalog_circle_id'] ?? ''),
            ],
            'q' => $q,
            'event_id' => $eventId,
            'page' => $page,
            'events' => $events,
            'candidates' => $candidates,
        ];
    }

    private function circleRowForJson(array $circle): array
    {
        return [
            'id' => (int) ($circle['id'] ?? 0),
            'name' => (string) ($circle['name'] ?? ''),
            'name_kana' => (string) ($circle['name_kana'] ?? ''),
            'webcatalog_circle_id' => (string) ($circle['webcatalog_circle_id'] ?? ''),
            'webcatalog_cut_url' => (string) ($circle['webcatalog_cut_url'] ?? ''),
            'twitter_url' => (string) ($circle['twitter_url'] ?? ''),
            'pixiv_url' => (string) ($circle['pixiv_url'] ?? ''),
            'website_url' => (string) ($circle['website_url'] ?? ''),
            'booth_url' => (string) ($circle['booth_url'] ?? ''),
            'melonbooks_url' => (string) ($circle['melonbooks_url'] ?? ''),
            'toranoana_url' => (string) ($circle['toranoana_url'] ?? ''),
        ];
    }

    private function storeCirclemsCutImage(string $url, string $circlemsId): string
    {
        if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://')) {
            throw new RuntimeException('圖片網址格式不正確。');
        }

        $uploadPath = FCPATH . 'uploads/circles';
        if (! is_dir($uploadPath) && ! mkdir($uploadPath, 0775, true) && ! is_dir($uploadPath)) {
            throw new RuntimeException('無法建立社團圖片目錄。');
        }

        $tempPath = $uploadPath . DIRECTORY_SEPARATOR . 'tmp_' . bin2hex(random_bytes(8));
        $handle = fopen($tempPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('無法寫入社團圖片。');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            fclose($handle);
            @unlink($tempPath);
            throw new RuntimeException('無法初始化圖片下載。');
        }

        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Personal Doujin Helper',
        ]);

        $ok = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);

        if ($ok === false || $statusCode < 200 || $statusCode >= 300) {
            @unlink($tempPath);
            throw new RuntimeException('HTTP ' . $statusCode . ($error !== '' ? ' / ' . $error : ''));
        }

        if ((filesize($tempPath) ?: 0) > 8 * 1024 * 1024) {
            @unlink($tempPath);
            throw new RuntimeException('圖片超過 8MB。');
        }

        $extension = $this->circleImageExtension($contentType, $tempPath);
        if ($extension === null) {
            @unlink($tempPath);
            throw new RuntimeException('圖片格式不支援。');
        }

        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $circlemsId) ?: 'circle';
        $fileName = 'circlems_' . $safeId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $uploadPath . DIRECTORY_SEPARATOR . $fileName;

        if (! rename($tempPath, $targetPath)) {
            @unlink($tempPath);
            throw new RuntimeException('無法儲存社團圖片。');
        }

        return '/uploads/circles/' . $fileName;
    }

    private function circleImageExtension(string $contentType, string $path): ?string
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0]));
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (isset($map[$contentType])) {
            return $map[$contentType];
        }

        $info = @getimagesize($path);
        if (! is_array($info)) {
            return null;
        }

        return $map[strtolower((string) ($info['mime'] ?? ''))] ?? null;
    }

    private function safeReturnTo(): string
    {
        $returnTo = (string) $this->request->getPost('return_to');
        if ($returnTo === '' || str_starts_with($returnTo, '//') || ! str_starts_with($returnTo, '/circles')) {
            return '/circles';
        }

        return $returnTo;
    }

    private function currentCirclemsToken(): ?array
    {
        return (new CirclemsTokenModel())
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function refreshCirclemsTokenIfNeeded(array $token, CirclemsClient $client): array
    {
        if (! $this->isCirclemsTokenExpired($token)) {
            return $token;
        }

        $tokenResponse = $client->refreshToken((string) $token['refresh_token']);
        $this->storeCirclemsToken($tokenResponse, $client);

        return $this->currentCirclemsToken() ?? $token;
    }

    private function storeCirclemsToken(array $tokenResponse, CirclemsClient $client): void
    {
        $data = [
            'access_token' => (string) ($tokenResponse['access_token'] ?? ''),
            'refresh_token' => (string) ($tokenResponse['refresh_token'] ?? ''),
            'expires_at' => $client->tokenExpiresAt($tokenResponse),
            'scope' => isset($tokenResponse['scope']) ? (string) $tokenResponse['scope'] : null,
            'last_error' => null,
        ];

        if ($data['access_token'] === '' || $data['refresh_token'] === '') {
            throw new RuntimeException('Circle.ms token response missing token values.');
        }

        $model = new CirclemsTokenModel();
        $current = $model->orderBy('id', 'DESC')->first();
        if ($current) {
            $model->update((int) $current['id'], $data);
            return;
        }

        $model->insert($data);
    }

    private function isCirclemsTokenExpired(?array $token): bool
    {
        if (! $token || empty($token['expires_at'])) {
            return true;
        }

        return strtotime((string) $token['expires_at']) <= time() + 300;
    }

    private function latestEventId(array $eventList): ?int
    {
        $candidates = [
            $eventList['response']['LatestEventId'] ?? null,
            $eventList['response']['latestEventId'] ?? null,
            $eventList['response']['latest_event_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function eventOptions(array $eventList): array
    {
        $list = $eventList['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $latestEventId = $this->latestEventId($eventList);
        $events = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $eventId = (int) ($row['EventId'] ?? $row['eventId'] ?? $row['event_id'] ?? 0);
            if ($eventId <= 0 || ($latestEventId !== null && $eventId > $latestEventId)) {
                continue;
            }

            $eventNo = (int) ($row['EventNo'] ?? $row['eventNo'] ?? $row['event_no'] ?? 0);
            $events[] = [
                'eventId' => $eventId,
                'label' => $eventNo > 0 ? 'C' . $eventNo . ' / Event ' . $eventId : 'Event ' . $eventId,
            ];
        }

        usort($events, static fn (array $a, array $b): int => $b['eventId'] <=> $a['eventId']);

        return $events;
    }

    private function circlemsCandidates(array $result): array
    {
        $list = $result['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $candidates = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stores = $this->circlemsStores($row['onlinestore'] ?? []);
            $candidates[] = [
                'wcid' => (string) ($row['wcid'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'name_kana' => (string) ($row['name_kana'] ?? ''),
                'circlems_id' => (string) ($row['circlemsId'] ?? ''),
                'genre' => (string) ($row['genre'] ?? ''),
                'cut_url' => (string) ($row['cut_url'] ?? $row['cut_web_url'] ?? $row['cut_base_url'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'tag' => (string) ($row['tag'] ?? ''),
                'website_url' => $this->nullableString($row['url'] ?? null),
                'pixiv_url' => $this->nullableString($row['pixiv_url'] ?? null),
                'twitter_url' => $this->nullableString($row['twitter_url'] ?? null),
                'booth_url' => $stores['booth_url'] ?? null,
                'melonbooks_url' => $stores['melonbooks_url'] ?? null,
                'toranoana_url' => $stores['toranoana_url'] ?? null,
                'stores' => $stores['links'],
            ];
        }

        return $candidates;
    }

    private function circlemsStores(mixed $stores): array
    {
        $links = [];
        $fields = [];
        if (! is_array($stores)) {
            return ['links' => [], 'booth_url' => null, 'melonbooks_url' => null, 'toranoana_url' => null];
        }

        foreach ($stores as $store) {
            if (! is_array($store)) {
                continue;
            }

            $name = trim((string) ($store['name'] ?? ''));
            $link = trim((string) ($store['link'] ?? ''));
            if ($name === '' || $link === '') {
                continue;
            }

            $links[] = ['name' => $name, 'link' => $link];
            $lowerName = mb_strtolower($name, 'UTF-8');
            if (str_contains($lowerName, 'booth')) {
                $fields['booth_url'] = $link;
            } elseif (str_contains($lowerName, 'メロン') || str_contains($lowerName, 'melon')) {
                $fields['melonbooks_url'] = $link;
            } elseif (str_contains($lowerName, 'とら') || str_contains($lowerName, 'tora')) {
                $fields['toranoana_url'] = $link;
            }
        }

        return [
            'links' => $links,
            'booth_url' => $fields['booth_url'] ?? null,
            'melonbooks_url' => $fields['melonbooks_url'] ?? null,
            'toranoana_url' => $fields['toranoana_url'] ?? null,
        ];
    }

    private function circlemsSocialFieldsFromPost(): array
    {
        return [
            'website_url' => $this->nullablePost('website_url'),
            'pixiv_url' => $this->nullablePost('pixiv_url'),
            'twitter_url' => $this->nullablePost('twitter_url'),
            'booth_url' => $this->nullablePost('booth_url'),
            'melonbooks_url' => $this->nullablePost('melonbooks_url'),
            'toranoana_url' => $this->nullablePost('toranoana_url'),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
