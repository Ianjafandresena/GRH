<?php
namespace App\Models\competence;

use CodeIgniter\Model;

class CompetenceModel extends Model
{
    protected $table = 'competence';
    protected $primaryKey = 'comp_code';
    protected $allowedFields = [
        'comp_intitule',
        'comp_domaine',
        'comp_description',
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'comp_intitule'    => 'required|max_length[50]',
        'comp_domaine'     => 'permit_empty|max_length[50]',
        'comp_description' => 'permit_empty|max_length[50]',
    ];
}

