<?php
namespace App\Models\document;

use CodeIgniter\Model;

class DocEmpModel extends Model
{
    protected $table = 'doc_emp';
    protected $primaryKey = 'doc_emp_code';
    protected $allowedFields = [
        'tdoc_code',
        'affec_code',
        'doc_emp_statut',
        'doc_emp_date',
        'tdoc_matricule',
        'usage',
        'commentaire'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'tdoc_code' => 'required|integer',
        'affec_code' => 'required|integer',
        'doc_emp_statut' => 'permit_empty|max_length[50]',
        'tdoc_matricule' => 'permit_empty|max_length[50]',
        'usage' => 'permit_empty|max_length[100]',
        'commentaire' => 'permit_empty|max_length[255]'
    ];

    /**
     * Récupérer les demandes de documents avec les informations de l'employé
     */
    public function getDemandesWithEmploye()
    {
        return $this->select('doc_emp.*, 
                             employe.emp_code, 
                             employe.emp_matricule, 
                             employe.emp_nom, 
                             employe.emp_prenom, 
                             type_document.tdoc_nom')
                    ->join('affectation', 'affectation.affec_code = doc_emp.affec_code', 'left')
                    ->join('employe', 'employe.emp_code = affectation.emp_code', 'left')
                    ->join('type_document', 'type_document.tdoc_code = doc_emp.tdoc_code', 'left')
                    ->orderBy('doc_emp.doc_emp_date', 'DESC')
                    ->findAll();
    }

    /**
     * Récupérer une demande par son ID avec les informations de l'employé
     */
    public function getDemandeWithEmploye($id)
    {
        return $this->select('doc_emp.*, 
                             employe.emp_code, 
                             employe.emp_matricule, 
                             employe.emp_nom, 
                             employe.emp_prenom, 
                             type_document.tdoc_nom')
                    ->join('affectation', 'affectation.affec_code = doc_emp.affec_code', 'left')
                    ->join('employe', 'employe.emp_code = affectation.emp_code', 'left')
                    ->join('type_document', 'type_document.tdoc_code = doc_emp.tdoc_code', 'left')
                    ->where('doc_emp.doc_emp_code', $id)
                    ->first();
    }
}

