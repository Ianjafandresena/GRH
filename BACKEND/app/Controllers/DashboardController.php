<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class DashboardController extends ResourceController
{
    use ResponseTrait;

    /**
     * Get employees currently on leave
     */
    public function getEmployeesOnLeave()
    {
        try {
            $db = \Config\Database::connect();
            $today = date('Y-m-d');
            
            $employees = $db->table('conge')
                ->select('employee.emp_code, employee.emp_nom, employee.emp_prenom, 
                          conge.cng_debut, conge.cng_fin,
                          type_conge.typ_appelation')
                ->join('employee', 'employee.emp_code = conge.emp_code')
                ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
                ->where('conge.cng_debut <=', $today)
                ->where('conge.cng_fin >=', $today)
                ->where('conge.cng_status', true)
                ->orderBy('conge.cng_debut', 'DESC')
                ->limit(5)
                ->get()->getResultArray();
            
            return $this->respond($employees);
        } catch (\Throwable $e) {
            log_message('error', '[getEmployeesOnLeave] Error: ' . $e->getMessage());
            return $this->respond([]);
        }
    }

    /**
     * Get pending reimbursement requests statistics
     */
    public function getPendingReimbursements()
    {
        $db = \Config\Database::connect();
        
        $stats = $db->table('demande_remb')
            ->select('COUNT(*) as count, COALESCE(SUM(rem_montant), 0) as total')
            ->where('rem_status', false)
            ->get()->getRowArray();
        
        return $this->respond($stats);
    }

    /**
     * Get recent activity feed (leaves + reimbursements)
     */
    public function getRecentActivity()
    {
        try {
            $db = \Config\Database::connect();
            $activities = [];
            
            // Recent leaves
            $conges = $db->table('conge')
                ->select("'conge' as type, employee.emp_nom, employee.emp_prenom, 
                          conge.cng_demande as date, 
                          CASE WHEN conge.cng_status = true THEN 'VALIDE' ELSE 'EN_ATTENTE' END as status,
                          type_conge.typ_appelation as label")
                ->join('employee', 'employee.emp_code = conge.emp_code')
                ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
                ->orderBy('conge.cng_demande', 'DESC')
                ->limit(3)
                ->get()->getResultArray();
            
            // Recent reimbursements
            $remb = $db->table('demande_remb')
                ->select("'remboursement' as type, employee.emp_nom, employee.emp_prenom,
                          demande_remb.rem_date as date, 
                          CASE WHEN rem_status = true THEN 'TRAITE' ELSE 'EN_ATTENTE' END as status,
                          objet_facture.obj_article as label")
                ->join('employee', 'employee.emp_code = demande_remb.emp_code')
                ->join('objet_facture', 'objet_facture.obj_code = demande_remb.obj_code', 'left')
                ->orderBy('demande_remb.rem_date', 'DESC')
                ->limit(3)
                ->get()->getResultArray();
            
            $activities = array_merge($conges, $remb);
            usort($activities, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
            
            return $this->respond(array_slice($activities, 0, 5));
        } catch (\Throwable $e) {
            log_message('error', '[getRecentActivity] Error: ' . $e->getMessage());
            return $this->respond([]);
        }
    }

    /**
     * Get reimbursement distribution for donut chart
     */
    public function getReimbursementDistribution()
    {
        $db = \Config\Database::connect();
        
        $total = $db->table('demande_remb')->countAllResults();
        
        $approuve = $db->table('demande_remb')->where('rem_status', true)->countAllResults();
        $en_attente = $db->table('demande_remb')->where('rem_status', false)->countAllResults();
        
        $montant_attente = $db->table('demande_remb')
            ->selectSum('rem_montant')
            ->where('rem_status', false)
            ->get()->getRow()->rem_montant ?? 0;
        
        return $this->respond([
            'stats' => [
                'approuve' => $approuve,
                'en_attente' => $en_attente,
                'total' => $total
            ],
            'montants' => [
                'en_attente' => $montant_attente
            ]
        ]);
    }

    /**
     * Get dashboard statistics (existing method - placeholder if not exists)
     */
    public function getDashboardStats()
    {
        $db = \Config\Database::connect();
        
        // Count active employees
        $totalEmployees = $db->table('employee')->countAllResults();
        $activeEmployees = $totalEmployees; // Can add WHERE clause for active status
        
        // Count current leaves
        $today = date('Y-m-d');
        $congesEnCours = $db->table('conge')
            ->where('cng_debut <=', $today)
            ->where('cng_fin >=', $today)
            ->where('cng_status', true)
            ->countAllResults();
        
        return $this->respond([
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'employeesEvolution' => 5, // Placeholder
            'congesEnCours' => $congesEnCours,
            'congesEvolution' => -8 // Placeholder
        ]);
    }

    /**
     * Get evolution statistics for chart (existing method - placeholder if not exists)
     */
    public function getEvolutionStats()
    {
        // Return mock data for now - you can implement real logic
        $months = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun'];
        
        return $this->respond([
            'labels' => $months,
            'conges' => [12, 18, 15, 25, 22, 19],
            'permissions' => [8, 12, 10, 15, 18, 14]
        ]);
    }
}
