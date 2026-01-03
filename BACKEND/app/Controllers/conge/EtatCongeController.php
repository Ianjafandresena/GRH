<?php

namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\SoldeCongeModel;
use App\Models\employee\EmployeeModel;
use CodeIgniter\API\ResponseTrait;

class EtatCongeController extends ResourceController
{
    use ResponseTrait;

    protected $format = 'json';

    /**
     * GET /api/etat_conge
     * Récupérer tous les employés avec leurs soldes multi-années
     * 
     * Query params:
     * - year (optional): Filtrer soldes <= année spécifiée
     * - search (optional): Recherche nom/prénom/matricule
     */
    public function index()
    {
        try {
            $year = $this->request->getGet('year');
            $search = $this->request->getGet('search');
            
            $db = \Config\Database::connect();
            
            // Récupérer tous employés actifs
            $employeeModel = new EmployeeModel();
            $employeesQuery = $employeeModel
                ->select('employee.*, direction.dir_nom, direction.dir_abreviation, poste.pst_fonction')
                ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
                ->join('direction', 'direction.dir_code = affectation.dir_code', 'left')
                ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
                ->where('employee.emp_disponibilite', true);
            
            // Filtre recherche
            if ($search) {
                $employeesQuery->groupStart()
                    ->like('employee.emp_nom', $search)
                    ->orLike('employee.emp_prenom', $search)
                    ->orLike('employee.emp_imarmp', $search)
                ->groupEnd();
            }
            
            $employees = $employeesQuery->findAll();
            
            $results = [];
            
            foreach ($employees as $emp) {
                // Récupérer soldes
                $soldesQuery = $db->table('solde_conge')
                    ->select('solde_conge.sld_anne, solde_conge.sld_initial, solde_conge.sld_restant, decision.dec_num')
                    ->join('decision', 'decision.dec_code = solde_conge.dec_code')
                    ->where('solde_conge.emp_code', $emp['emp_code'])
                    ->orderBy('solde_conge.sld_anne', 'ASC'); // FIFO
                
                // Filtre année
                if ($year) {
                    $soldesQuery->where('solde_conge.sld_anne <=', (int)$year);
                }
                
                $soldes = $soldesQuery->get()->getResultArray();
                
                // Formater soldes
                $soldesFormatted = array_map(function($s) {
                    return [
                        'annee' => (int)$s['sld_anne'],
                        'decision' => $s['dec_num'],
                        'initial' => (float)$s['sld_initial'],
                        'reste' => (float)$s['sld_restant']
                    ];
                }, $soldes);
                
                $results[] = [
                    'emp_code' => (int)$emp['emp_code'],
                    'emp_nom' => $emp['emp_nom'],
                    'emp_prenom' => $emp['emp_prenom'],
                    'emp_imarmp' => $emp['emp_imarmp'],
                    'direction' => $emp['dir_nom'] ?? 'N/A',
                    'direction_abrev' => $emp['dir_abreviation'] ?? '',
                    'fonction' => $emp['pst_fonction'] ?? 'N/A',
                    'soldes' => $soldesFormatted
                ];
            }
            
            return $this->respond($results);
            
        } catch (\Exception $e) {
            log_message('error', '[EtatConge] ' . $e->getMessage());
            return $this->fail('Erreur lors de la récupération des états de congé', 500);
        }
    }

    /**
     * GET /api/etat_conge/years
     * Récupérer les années disponibles (avec décisions sorties)
     */
    public function getAvailableYears()
    {
        try {
            $db = \Config\Database::connect();
            
            $years = $db->table('solde_conge')
                ->distinct()
                ->select('sld_anne as year')
                ->orderBy('sld_anne', 'ASC')
                ->get()
                ->getResultArray();
            
            $yearsList = array_map(function($y) {
                return (int)$y['year'];
            }, $years);
            
            return $this->respond($yearsList);
            
        } catch (\Exception $e) {
            log_message('error', '[EtatConge] getAvailableYears error: ' . $e->getMessage());
            return $this->fail('Erreur récupération années: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/etat_conge/{emp_code}
     * Récupérer état de congé pour un employé spécifique
     */
    public function show($empCode = null)
    {
        if (!$empCode) {
            return $this->fail('Code employé requis', 400);
        }

        try {
            $db = \Config\Database::connect();
            
            // Récupérer employé
            $employeeModel = new EmployeeModel();
            $employee = $employeeModel
                ->select('employee.*, direction.dir_nom, poste.pst_fonction')
                ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
                ->join('direction', 'direction.dir_code = affectation.dir_code', 'left')
                ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
                ->where('employee.emp_code', $empCode)
                ->first();
            
            if (!$employee) {
                return $this->failNotFound('Employé non trouvé');
            }
            
            // Récupérer soldes
            $soldes = $db->table('solde_conge')
                ->select('solde_conge.sld_anne, solde_conge.sld_initial, solde_conge.sld_restant, decision.dec_num')
                ->join('decision', 'decision.dec_code = solde_conge.dec_code')
                ->where('solde_conge.emp_code', $empCode)
                ->orderBy('solde_conge.sld_anne', 'ASC')
                ->get()
                ->getResultArray();
            
            $soldesFormatted = array_map(function($s) {
                return [
                    'annee' => (int)$s['sld_anne'],
                    'decision' => $s['dec_num'],
                    'initial' => (float)$s['sld_initial'],
                    'reste' => (float)$s['sld_restant']
                ];
            }, $soldes);
            
            return $this->respond([
                'emp_code' => (int)$employee['emp_code'],
                'emp_nom' => $employee['emp_nom'],
                'emp_prenom' => $employee['emp_prenom'],
                'emp_imarmp' => $employee['emp_imarmp'],
                'direction' => $employee['dir_nom'] ?? 'N/A',
                'fonction' => $employee['pst_fonction'] ?? 'N/A',
                'soldes' => $soldesFormatted
            ]);
            
        } catch (\Exception $e) {
            log_message('error', '[EtatConge] ' . $e->getMessage());
            return $this->fail('Erreur récupération état', 500);
        }
    }
}
