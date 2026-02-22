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
    /**
     * Get pending reimbursement requests statistics
     */
    public function getPendingReimbursements()
    {
        $db = \Config\Database::connect();
        $startDate = $this->request->getVar('start_date');
        $endDate = $this->request->getVar('end_date');
        
        $builder = $db->table('demande_remb')
            ->select('COUNT(*) as count, COALESCE(SUM(rem_montant), 0) as total')
            ->where('rem_status', false);

        if ($startDate) $builder->where('rem_date >=', $startDate);
        if ($endDate) $builder->where('rem_date <=', $endDate);
        
        $stats = $builder->get()->getRowArray();
        
        return $this->respond($stats);
    }

    /**
     * Get recent activity feed (leaves + reimbursements)
     */
    public function getRecentActivity()
    {
        try {
            $db = \Config\Database::connect();
            $startDate = $this->request->getVar('start_date');
            $endDate = $this->request->getVar('end_date');
            
            // Recent leaves
            $congesBuilder = $db->table('conge')
                ->select("'conge' as type, employee.emp_nom, employee.emp_prenom, 
                          conge.cng_demande as date, 
                          CASE WHEN conge.cng_status = true THEN 'VALIDE' ELSE 'EN_ATTENTE' END as status,
                          type_conge.typ_appelation as label")
                ->join('employee', 'employee.emp_code = conge.emp_code')
                ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left');

            if ($startDate) $congesBuilder->where('conge.cng_demande >=', $startDate);
            if ($endDate) $congesBuilder->where('conge.cng_demande <=', $endDate);
            
            $conges = $congesBuilder->orderBy('conge.cng_demande', 'DESC')
                ->limit(5)
                ->get()->getResultArray();
            
            // Recent reimbursements
            $rembBuilder = $db->table('demande_remb')
                ->select("'remboursement' as type, employee.emp_nom, employee.emp_prenom,
                          demande_remb.rem_date as date, 
                          CASE WHEN rem_status = true THEN 'TRAITE' ELSE 'EN_ATTENTE' END as status,
                          objet_facture.obj_article as label")
                ->join('employee', 'employee.emp_code = demande_remb.emp_code')
                ->join('objet_facture', 'objet_facture.obj_code = demande_remb.obj_code', 'left');

            if ($startDate) $rembBuilder->where('demande_remb.rem_date >=', $startDate);
            if ($endDate) $rembBuilder->where('demande_remb.rem_date <=', $endDate);
            
            $remb = $rembBuilder->orderBy('demande_remb.rem_date', 'DESC')
                ->limit(5)
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
        $startDate = $this->request->getVar('start_date');
        $endDate = $this->request->getVar('end_date');
        
        $totalBuilder = $db->table('demande_remb');
        if ($startDate) $totalBuilder->where('rem_date >=', $startDate);
        if ($endDate) $totalBuilder->where('rem_date <=', $endDate);
        $total = $totalBuilder->countAllResults();
        
        $approuveBuilder = $db->table('demande_remb')->where('rem_status', true);
        if ($startDate) $approuveBuilder->where('rem_date >=', $startDate);
        if ($endDate) $approuveBuilder->where('rem_date <=', $endDate);
        $approuve = $approuveBuilder->countAllResults();

        $enAttenteBuilder = $db->table('demande_remb')->where('rem_status', false);
        if ($startDate) $enAttenteBuilder->where('rem_date >=', $startDate);
        if ($endDate) $enAttenteBuilder->where('rem_date <=', $endDate);
        $en_attente = $enAttenteBuilder->countAllResults();
        
        $montantBuilder = $db->table('demande_remb')
            ->selectSum('rem_montant')
            ->where('rem_status', false);
        if ($startDate) $montantBuilder->where('rem_date >=', $startDate);
        if ($endDate) $montantBuilder->where('rem_date <=', $endDate);
        $montant_attente = $montantBuilder->get()->getRow()->rem_montant ?? 0;
        
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

    /**
     * Get Top 5 most absent employees
     */
    public function getTopAbsentEmployees()
    {
        try {
            $db = \Config\Database::connect();
            $startDate = $this->request->getVar('start_date');
            $endDate = $this->request->getVar('end_date');
            
            $builder = $db->table('conge')
                ->select('employee.emp_nom, employee.emp_prenom, SUM(conge.cng_nb_jour) as total_jours')
                ->join('employee', 'employee.emp_code = conge.emp_code')
                ->where('conge.cng_status', true);

            if ($startDate) $builder->where('conge.cng_debut >=', $startDate);
            if ($endDate) $builder->where('conge.cng_fin <=', $endDate);

            $results = $builder->groupBy(['employee.emp_code', 'employee.emp_nom', 'employee.emp_prenom', 'employee.emp_code'])
                ->orderBy('total_jours', 'DESC')
                ->limit(5)
                ->get()->getResultArray();
            
            return $this->respond($results);
        } catch (\Throwable $e) {
            log_message('error', '[getTopAbsentEmployees] Error: ' . $e->getMessage());
            return $this->respond([]);
        }
    }

    /**
     * Get Top 5 employees with most reimbursement requests
     */
    public function getTopReimbursements()
    {
        try {
            $db = \Config\Database::connect();
            $startDate = $this->request->getVar('start_date');
            $endDate = $this->request->getVar('end_date');
            
            $builder = $db->table('demande_remb')
                ->select('employee.emp_nom, employee.emp_prenom, COUNT(*) as nb_demandes, SUM(rem_montant) as total_montant')
                ->join('employee', 'employee.emp_code = demande_remb.emp_code');

            if ($startDate) $builder->where('rem_date >=', $startDate);
            if ($endDate) $builder->where('rem_date <=', $endDate);

            $results = $builder->groupBy(['employee.emp_code', 'employee.emp_nom', 'employee.emp_prenom', 'employee.emp_code'])
                ->orderBy('nb_demandes', 'DESC')
                ->limit(5)
                ->get()->getResultArray();
            
            return $this->respond($results);
        } catch (\Throwable $e) {
            log_message('error', '[getTopReimbursements] Error: ' . $e->getMessage());
            return $this->respond([]);
        }
    }
}
