<?php

namespace App\Models;

use CodeIgniter\Model;

class CircleModel extends Model
{
    protected $table = 'circles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'name_kana',
        'is_tracked',
        'priority',
        'pixiv_url',
        'twitter_url',
        'website_url',
        'booth_url',
        'melonbooks_url',
        'toranoana_url',
        'webcatalog_circle_id',
        'note',
    ];
}
