<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class StatutArmpModel extends Model
{
    protected $table = 'statut_armp';
    protected $primaryKey = 'stt_armp_code';
    protected $allowedFields = [
        'stt_armp_statut'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'stt_armp_statut' => 'required|max_length[50]'
    ];

    protected $validationMessages = [
        'stt_armp_statut' => [
            'required' => 'Le statut ARMP est obligatoire',
            'max_length' => 'Le statut ne peut pas dépasser 50 caractères'
        ]
    ];
}
