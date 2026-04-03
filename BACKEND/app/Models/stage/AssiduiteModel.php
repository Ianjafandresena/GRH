<?php
namespace App\Models\stage;

use CodeIgniter\Model;

class AssiduiteModel extends Model
{
    protected $table = 'assiduite';
    protected $primaryKey = 'asdt_code';
    protected $allowedFields = [
        'asdt_remarque',
        'asdt_nb_abscence',
        'asdt_nb_retard',
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'asdt_nb_abscence' => 'permit_empty|integer',
        'asdt_nb_retard'   => 'permit_empty|integer',
        'asdt_remarque'    => 'permit_empty|max_length[50]',
    ];
}

