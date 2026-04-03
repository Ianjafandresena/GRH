<?php
namespace App\Models\stage;

use CodeIgniter\Model;

class StageModel extends Model
{
    protected $table = 'stage';
    protected $primaryKey = 'stg_code';
    protected $allowedFields = [
        'stg_duree',
        'stg_date_debut',
        'stg_date_fin',
        'stg_theme',
        'evstg_code',
        'stgr_code',
        'etab_code'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'stgr_code'      => 'required|integer',
        'stg_date_debut' => 'required|valid_date',
        'stg_date_fin'   => 'permit_empty|valid_date',
        'stg_theme'      => 'permit_empty|max_length[50]',
        'stg_duree'      => 'permit_empty|integer',
        'etab_code'      => 'permit_empty|integer',
    ];

    protected $beforeInsert = ['computeDuration'];
    protected $beforeUpdate = ['computeDuration'];

    protected function computeDuration(array $data)
    {
        if (
            empty($data['data']['stg_duree']) &&
            !empty($data['data']['stg_date_debut']) &&
            !empty($data['data']['stg_date_fin'])
        ) {
            try {
                $debut = new \DateTime($data['data']['stg_date_debut']);
                $fin   = new \DateTime($data['data']['stg_date_fin']);
                $interval = $debut->diff($fin);
                $data['data']['stg_duree'] = $interval->days;
            } catch (\Exception $e) {
                // On laisse la validation gérer les dates invalides
            }
        }

        return $data;
    }
}

