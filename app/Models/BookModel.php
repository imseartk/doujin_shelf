<?php

namespace App\Models;

use CodeIgniter\Model;

class BookModel extends Model
{
    protected $table = 'books';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'type',
        'title',
        'circle_kana',
        'circle',
        'author',
        'event',
        'cover_url',
        'status',
        'location_id',
        'note',
    ];
}
