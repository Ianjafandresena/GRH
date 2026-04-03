<?php
namespace App\Models\stage;

use CodeIgniter\Model;

class StagiaireModel extends Model
{
    protected $table = 'stagiaire';
    protected $primaryKey = 'stgr_code';
    protected $allowedFields = [
        'stgr_nom',
        'stgr_prenom',
        'stgr_nom_prenom',
        'stgr_contact',
        'stgr_filiere',
        'stgr_niveau',
        'stgr_adresse',
        'stgr_sexe'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'stgr_nom' => 'required|min_length[2]|max_length[50]',
        'stgr_prenom' => 'required|min_length[2]|max_length[50]',
        'stgr_contact' => 'required|max_length[50]|is_unique[stagiaire.stgr_contact]',
        'stgr_filiere' => 'permit_empty|max_length[50]',
        'stgr_niveau' => 'permit_empty|max_length[50]',
        'stgr_adresse' => 'required|max_length[255]',
        'stgr_sexe' => 'required'
    ];

    protected $validationMessages = [
        'stgr_nom' => [
            'required' => 'Le nom du stagiaire est obligatoire',
            'min_length' => 'Le nom doit contenir au moins 2 caractères'
        ],
        'stgr_prenom' => [
            'required' => 'Le prénom du stagiaire est obligatoire',
            'min_length' => 'Le prénom doit contenir au moins 2 caractères'
        ],
        'stgr_contact' => [
            'required' => 'Le contact est obligatoire',
            'is_unique' => 'Ce contact est déjà utilisé pour un autre stagiaire'
        ],
    ];

    protected $beforeInsert = ['buildFullName'];
    protected $beforeUpdate = ['buildFullName'];

    protected function buildFullName(array $data)
    {
        if (isset($data['data']['stgr_nom']) && isset($data['data']['stgr_prenom'])) {
            $data['data']['stgr_nom_prenom'] = trim($data['data']['stgr_nom'] . ' ' . $data['data']['stgr_prenom']);
        }

        return $data;
    }
}

