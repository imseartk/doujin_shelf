<?php

namespace App\Models;

use CodeIgniter\Model;

class BookSourceModel extends Model
{
    protected $table = 'book_sources';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'book_id',
        'shop_id',
        'price',
        'item_url',
        'availability_status',
        'note',
        'checked_at',
    ];
}
