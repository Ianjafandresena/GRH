<?php
namespace App\Models\affectation;

use CodeIgniter\Model;

class AffectationModel extends Model
{
    protected $table = 'affectation';
    protected $primaryKey = 'affec_code';
    protected $allowedFields = [
        'affec_date_debut',
        'affec_date_fin',
        'affec_commentaire',
        'tcontrat_code',
        'm_aff_code',
        'emp_code',
        'pst_code',
        'affec_etat'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'affec_date_debut' => 'required|valid_date',
        'm_aff_code' => 'required|integer',
        'emp_code' => 'required|integer',
        'pst_code' => 'required|integer',
        'tcontrat_code' => 'required|integer',
        'affec_commentaire' => 'permit_empty|max_length[255]',
        'affec_etat' => 'permit_empty|in_list[active,cloture]'
    ];
}

