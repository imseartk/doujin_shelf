<?php

namespace App\Controllers;

use App\Models\LocationModel;
use CodeIgniter\HTTP\RedirectResponse;

class Locations extends BaseController
{
    public function index(): string
    {
        $model = new LocationModel();
        $locations = $model->orderBy('parent_id', 'ASC')->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();

        return view('locations/index', [
            'locations' => $locations,
            'parents' => array_values(array_filter($locations, static fn ($row) => empty($row['parent_id']))),
        ]);
    }

    public function create(): RedirectResponse
    {
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('/locations')->with('error', '請輸入位置名稱。');
        }

        (new LocationModel())->insert([
            'parent_id' => (int) $this->request->getPost('parent_id') ?: null,
            'name' => $name,
            'sort_order' => (int) $this->request->getPost('sort_order'),
        ]);

        return redirect()->to('/locations')->with('message', '已新增位置。');
    }

    public function delete(int $id): RedirectResponse
    {
        (new LocationModel())->delete($id);
        return redirect()->to('/locations')->with('message', '已刪除位置。');
    }
}
