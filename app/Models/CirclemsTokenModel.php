<?php

namespace App\Models;

use CodeIgniter\Model;

class CirclemsTokenModel extends Model
{
    protected $table = 'circlems_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
        'last_tested_at',
        'last_error',
    ];
}
