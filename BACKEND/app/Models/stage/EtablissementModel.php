<?php
namespace App\Models\stage;

use CodeIgniter\Model;

class EtablissementModel extends Model
{
    protected $table = 'etablissement';
    protected $primaryKey = 'etab_code';
    protected $allowedFields = [
        'etab_nom',
        'etab_adresse'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'etab_nom' => 'required|min_length[2]|max_length[50]',
        'etab_adresse' => 'permit_empty|max_length[50]'
    ];

    protected $validationMessages = [
        'etab_nom' => [
            'required' => 'Le nom de l\'établissement est obligatoire',
            'min_length' => 'Le nom doit contenir au moins 2 caractères'
        ]
    ];
}

