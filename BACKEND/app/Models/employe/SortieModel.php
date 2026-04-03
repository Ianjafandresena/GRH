<?php
namespace App\Models\employe;

use CodeIgniter\Model;

class SortieModel extends Model
{
    protected $table = 'sortie';
    protected $primaryKey = 'emp_code';
    protected $useAutoIncrement = false; // Clé primaire composite, pas d'auto-incrémentation
    protected $allowedFields = [
        'emp_code',
        's_type_code',
        'commentaire',
        'date_sortie'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    /**
     * Récupérer toutes les sorties d'un employé
     */
    public function getSortiesByEmploye($empCode)
    {
        return $this->select('sortie.*, sortie_type.s_type_motif')
            ->join('sortie_type', 'sortie_type.s_type_code = sortie.s_type_code', 'left')
            ->where('sortie.emp_code', $empCode)
            ->orderBy('sortie.date_sortie', 'DESC')
            ->findAll();
    }

    /**
     * Récupérer la dernière sortie d'un employé
     */
    public function getDerniereSortie($empCode)
    {
        try {
            $db = \Config\Database::connect();
            $result = $db->table('sortie')
                ->select('sortie.*, sortie_type.s_type_motif')
                ->join('sortie_type', 'sortie_type.s_type_code = sortie.s_type_code', 'left')
                ->where('sortie.emp_code', $empCode)
                ->orderBy('sortie.date_sortie', 'DESC')
                ->limit(1)
                ->get();
            
            if ($result && $result !== false) {
                return $result->getRowArray();
            }
            return null;
        } catch (\Exception $e) {
            log_message('error', 'Erreur getDerniereSortie: ' . $e->getMessage());
            return null;
        }
    }
}

