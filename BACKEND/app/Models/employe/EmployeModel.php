<?php
namespace App\Models\employe;

use CodeIgniter\Model;

class EmployeModel extends Model
{
    protected $table = 'employe';
    protected $primaryKey = 'emp_code';
    protected $allowedFields = [
        'emp_matricule',
        'emp_nom',
        'emp_prenom',
        'emp_titre',
        'emp_sexe',
        'emp_datenaissance',
        'emp_im_armp',
        'emp_im_etat',
        'date_entree',
        'date_sortie',
        's_type_code',
        'e_type_code',
        'emp_mail',
        'emp_cin'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'emp_nom' => 'required|min_length[2]|max_length[50]',
        'emp_prenom' => 'required|min_length[2]|max_length[50]',
        'emp_titre' => 'required|in_list[M.,Mme,Dr.,Pr.]',
        'emp_sexe' => 'required|in_list[0,1]',
        'emp_datenaissance' => 'required|valid_date',
        'emp_im_armp' => 'required|max_length[50]|is_unique[employe.emp_im_armp,emp_code,{emp_code}]',
        'emp_im_etat' => 'permit_empty|max_length[50]|is_unique[employe.emp_im_etat,emp_code,{emp_code}]',
        'emp_cin' => 'required|max_length[20]|is_unique[employe.emp_cin,emp_code,{emp_code}]',
        'emp_mail' => 'required|valid_email|max_length[100]|is_unique[employe.emp_mail,emp_code,{emp_code}]',
        'date_entree' => 'required|valid_date',
        'e_type_code' => 'required',
        'emp_code' => 'permit_empty|integer'
    ];

    protected $validationMessages = [
        'emp_nom' => [
            'required' => 'Le nom est obligatoire',
            'min_length' => 'Le nom doit contenir au moins 2 caractères'
        ],
        'emp_im_armp' => [
            'is_unique' => 'Cet IM ARMP existe déjà'
        ],
        'emp_im_etat' => [
            'is_unique' => 'Cet IM État existe déjà'
        ],
        'emp_mail' => [
            'is_unique' => 'Cette adresse email est déjà utilisée par un autre employé',
            'valid_email' => 'L\'adresse email n\'est pas valide'
        ],
        'emp_cin' => [
            'is_unique' => 'Ce numéro de CIN existe déjà'
        ]
    ];
    protected $beforeInsert = ['castData'];
    protected $beforeUpdate = ['castData'];

    protected function castData(array $data)
    {
        if (isset($data['data']['emp_sexe'])) {
            // Conversion explicite pour PostgreSQL
            // Gérer les différents formats : 0/1 (int), '0'/'1' (string), true/false (bool)
            $sexeValue = $data['data']['emp_sexe'];
            if (is_numeric($sexeValue)) {
                $data['data']['emp_sexe'] = ((int) $sexeValue === 1) ? 't' : 'f';
            } elseif (is_bool($sexeValue)) {
                $data['data']['emp_sexe'] = $sexeValue ? 't' : 'f';
            } elseif (is_string($sexeValue)) {
                $data['data']['emp_sexe'] = ($sexeValue === '1' || $sexeValue === 'true' || $sexeValue === 't') ? 't' : 'f';
            } else {
                $data['data']['emp_sexe'] = $sexeValue ? 't' : 'f';
            }
        }

        // Conversion des chaînes vides en NULL pour les champs uniques optionnels
        $optionalUniqueFields = ['emp_im_etat', 'emp_matricule'];
        foreach ($optionalUniqueFields as $field) {
            if (isset($data['data'][$field]) && $data['data'][$field] === '') {
                $data['data'][$field] = null;
            }
        }

        return $data;
    }
}

