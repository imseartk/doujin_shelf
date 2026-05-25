<?php

namespace App\Controllers;

use App\Models\BookModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class BookCovers extends BaseController
{
    private const COVER_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function upload(int $id): ResponseInterface
    {
        $bookModel = new BookModel();
        $book = $bookModel->find($id);

        if (! $book) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => '找不到這本書。',
                'csrf' => csrf_hash(),
            ]);
        }

        try {
            $coverUrl = $this->storeCoverUpload($this->request->getFile('cover_file'));
        } catch (RuntimeException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'message' => $exception->getMessage(),
                'csrf' => csrf_hash(),
            ]);
        }

        $bookModel->update($id, ['cover_url' => $coverUrl]);

        return $this->response->setJSON([
            'cover_url' => $coverUrl,
            'csrf' => csrf_hash(),
        ]);
    }

    private function storeCoverUpload(?UploadedFile $file): string
    {
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('請選擇封面圖片。');
        }

        if (! $file->isValid()) {
            throw new RuntimeException('封面上傳失敗，請重新選擇檔案。');
        }

        if ($file->getSizeByUnit('mb') > 8) {
            throw new RuntimeException('封面圖片不可超過 8MB。');
        }

        if (! in_array($file->getMimeType(), self::COVER_MIME_TYPES, true)) {
            throw new RuntimeException('封面只支援 JPG、PNG、WEBP 或 GIF。');
        }

        $extension = $file->guessExtension() ?: $file->getExtension() ?: 'jpg';
        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $uploadPath = FCPATH . 'uploads/covers';

        if (! is_dir($uploadPath) && ! mkdir($uploadPath, 0775, true) && ! is_dir($uploadPath)) {
            throw new RuntimeException('無法建立封面上傳目錄。');
        }

        $file->move($uploadPath, $fileName);

        return '/uploads/covers/' . $fileName;
    }
}
