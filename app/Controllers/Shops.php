<?php

namespace App\Controllers;

use App\Models\ShopModel;
use CodeIgniter\HTTP\RedirectResponse;

class Shops extends BaseController
{
    public function index(): string
    {
        return view('shops/index', [
            'shops' => (new ShopModel())->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function create(): RedirectResponse
    {
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('/shops')->with('error', '請輸入店鋪名稱。');
        }

        (new ShopModel())->insert([
            'name' => $name,
            'website_url' => $this->websiteUrlPost(),
            'sort_order' => 0,
            'is_active' => 1,
        ]);

        return redirect()->to('/shops')->with('message', '已新增店鋪。');
    }

    public function update(int $id): RedirectResponse
    {
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('/shops')->with('error', '請輸入店鋪名稱。');
        }

        $shopModel = new ShopModel();
        if (! $shopModel->find($id)) {
            return redirect()->to('/shops')->with('error', '找不到這間店鋪。');
        }

        $shopModel->update($id, [
            'name' => $name,
            'website_url' => $this->websiteUrlPost(),
        ]);

        return redirect()->to('/shops')->with('message', '已儲存店鋪。');
    }

    public function delete(int $id): RedirectResponse
    {
        (new ShopModel())->delete($id);
        return redirect()->to('/shops')->with('message', '已刪除店鋪。');
    }

    private function websiteUrlPost(): ?string
    {
        $websiteUrl = trim((string) $this->request->getPost('website_url'));
        return $websiteUrl === '' ? null : $websiteUrl;
    }
}
