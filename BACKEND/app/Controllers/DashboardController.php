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
            
            $conges = $db->table('conge')
                ->select("employe.emp_code, employe.emp_nom, employe.emp_prenom, 
                          conge.cng_debut as debut, conge.cng_fin as fin,
                          type_conge.typ_appelation as motif, 'Congé' as type")
                ->join('employe', 'employe.emp_code = conge.emp_code')
                ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
                ->where('conge.cng_debut <=', $today)
                ->where('conge.cng_fin >=', $today)
                ->where('conge.cng_status', true)
                ->get()->getResultArray();

            $permissions = $db->table('permission')
                ->select("employe.emp_code, employe.emp_nom, employe.emp_prenom, 
                          permission.prm_debut as debut, permission.prm_fin as fin,
                          'Permission exceptionnelle' as motif, 'Permission' as type")
                ->join('employe', 'employe.emp_code = permission.emp_code')
                ->where('permission.prm_debut <=', $today . ' 23:59:59')
                ->where('permission.prm_fin >=', $today . ' 00:00:00')
                ->where('permission.prm_status', true)
                ->get()->getResultArray();
            
            $employees = array_merge($conges, $permissions);
            return $this->respond(array_slice($employees, 0, 10));
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
            
            $last24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
            
            // Recent leaves
            $congesBuilder = $db->table('conge')
                ->select("'conge' as type, employe.emp_nom, employe.emp_prenom, 
                          conge.cng_demande as date, 
                          CASE WHEN conge.cng_status = true THEN 'VALIDE' ELSE 'EN_ATTENTE' END as status,
                          type_conge.typ_appelation as label")
                ->join('employe', 'employe.emp_code = conge.emp_code')
                ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
                ->where('conge.cng_demande >=', $last24h);

            if ($startDate) $congesBuilder->where('conge.cng_demande >=', $startDate);
            if ($endDate) $congesBuilder->where('conge.cng_demande <=', $endDate);
            
            $conges = $congesBuilder->orderBy('conge.cng_demande', 'DESC')
                ->limit(20)
                ->get()->getResultArray();
            
            // Recent reimbursements
            $rembBuilder = $db->table('demande_remb')
                ->select("'remboursement' as type, employe.emp_nom, employe.emp_prenom,
                          demande_remb.rem_date as date, 
                          CASE WHEN rem_status = true THEN 'TRAITE' ELSE 'EN_ATTENTE' END as status,
                          objet_remboursement.obj_article as label")
                ->join('employe', 'employe.emp_code = demande_remb.emp_code')
                ->join('objet_remboursement', 'objet_remboursement.obj_code = demande_remb.obj_code', 'left')
                ->where('demande_remb.rem_date >=', $last24h);

            if ($startDate) $rembBuilder->where('demande_remb.rem_date >=', $startDate);
            if ($endDate) $rembBuilder->where('demande_remb.rem_date <=', $endDate);
            
            $remb = $rembBuilder->orderBy('demande_remb.rem_date', 'DESC')
                ->limit(20)
                ->get()->getResultArray();
            
            $activities = array_merge($conges, $remb);
            usort($activities, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
            
            return $this->respond($activities);
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
        $totalEmployees = $db->table('employe')->countAllResults();
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
                ->select('employe.emp_nom, employe.emp_prenom, SUM(conge.cng_nb_jour) as total_jours')
                ->join('employe', 'employe.emp_code = conge.emp_code')
                ->where('conge.cng_status', true);

            if ($startDate) $builder->where('conge.cng_debut >=', $startDate);
            if ($endDate) $builder->where('conge.cng_fin <=', $endDate);

            $results = $builder->groupBy(['employe.emp_code', 'employe.emp_nom', 'employe.emp_prenom', 'employe.emp_code'])
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
                ->select('employe.emp_nom, employe.emp_prenom, COUNT(*) as nb_demandes, SUM(rem_montant) as total_montant')
                ->join('employe', 'employe.emp_code = demande_remb.emp_code');

            if ($startDate) $builder->where('rem_date >=', $startDate);
            if ($endDate) $builder->where('rem_date <=', $endDate);

            $results = $builder->groupBy(['employe.emp_code', 'employe.emp_nom', 'employe.emp_prenom', 'employe.emp_code'])
                ->orderBy('nb_demandes', 'DESC')
                ->limit(5)
                ->get()->getResultArray();
            
            return $this->respond($results);
        } catch (\Throwable $e) {
            log_message('error', '[getTopReimbursements] Error: ' . $e->getMessage());
            return $this->respond([]);
        }
    }

    /**
     * Get Absence and Leave KPIs for the summary bar
     */
    public function getAbsenceKPIs()
    {
        try {
            $db = \Config\Database::connect();
            $start = $this->request->getGet('start');
            $end = $this->request->getGet('end');
            $lieu = $this->request->getGet('lieu');
            
            // 1. Total Employees (filtered by lieu if provided)
            $empBuilder = $db->table('employe')->where('emp_disponibilite', true);
            if ($lieu) {
                $empBuilder->join('affectation', 'affectation.emp_code = employe.emp_code')
                           ->join('region', 'region.reg_code = affectation.reg_code')
                           ->like('region.reg_nom', $lieu);
            }
            $totalEmployees = $empBuilder->countAllResults();
            if ($totalEmployees == 0) $totalEmployees = 1;

            // 2. Total Days Taken (Conges)
            $congeBuilder = $db->table('conge')
                ->selectSum('cng_nb_jour')
                ->where('cng_status', true);
            
            if ($start) $congeBuilder->where('cng_debut >=', $start);
            if ($end) $congeBuilder->where('cng_fin <=', $end);
            if ($lieu) {
                $congeBuilder->join('region', 'region.reg_code = conge.reg_code')
                             ->like('region.reg_nom', $lieu);
            }
            $totalDaysTaken = $congeBuilder->get()->getRow()->cng_nb_jour ?? 0;

            // 3. Total Days Allocated (sld_initial)
            $allocBuilder = $db->table('solde_conge')->selectSum('sld_initial');
            if ($start) {
                $year = date('Y', strtotime($start));
                $allocBuilder->where('sld_anne', $year);
            } else {
                $allocBuilder->where('sld_anne', date('Y'));
            }
            $totalDaysAllocated = $allocBuilder->get()->getRow()->sld_initial ?? 0;
            if ($totalDaysAllocated == 0) $totalDaysAllocated = 1;

            // 4. Permissions
            $permBuilder = $db->table('permission')
                ->selectSum('prm_duree')
                ->where('prm_status', true);
            if ($start) $permBuilder->where('prm_debut >=', $start);
            if ($end) $permBuilder->where('prm_fin <=', $end);
            if ($lieu) {
                $permBuilder->join('employe', 'employe.emp_code = permission.emp_code')
                            ->join('affectation', 'affectation.emp_code = employe.emp_code')
                            ->join('region', 'region.reg_code = affectation.reg_code')
                            ->like('region.reg_nom', $lieu);
            }
            $totalPermissionsHours = $permBuilder->get()->getRow()->prm_duree ?? 0;
            $totalPermissionsDays = (float)$totalPermissionsHours / 8;

            // KPI calculation
            $totalAbsenceDays = (float)$totalDaysTaken + $totalPermissionsDays;
            $avgDays = round($totalAbsenceDays / $totalEmployees, 1);
            $utilizationRate = round(($totalDaysTaken / $totalDaysAllocated) * 100, 1);
            
            // Absenteeism Rate calculation
            $theoreticalWorkDays = 260; 
            if ($start && $end) {
                $diff = strtotime($end) - strtotime($start);
                $theoreticalWorkDays = max(1, floor($diff / (60 * 60 * 24)) * 0.7); // Rough working days
            }
            $absenteeismRate = round(($totalAbsenceDays / ($totalEmployees * $theoreticalWorkDays)) * 100, 2);
            
            // Total recorded count (filtered)
            $cngCountBuilder = $db->table('conge');
            if ($start) $cngCountBuilder->where('cng_debut >=', $start);
            if ($end) $cngCountBuilder->where('cng_fin <=', $end);
            if ($lieu) {
                $cngCountBuilder->join('region', 'region.reg_code = conge.reg_code')
                                ->like('region.reg_nom', $lieu);
            }
            $countConges = $cngCountBuilder->countAllResults();

            $prmCountBuilder = $db->table('permission');
            if ($start) $prmCountBuilder->where('prm_debut >=', $start);
            if ($end) $prmCountBuilder->where('prm_fin <=', $end);
            if ($lieu) {
                $prmCountBuilder->join('employe', 'employe.emp_code = permission.emp_code')
                                ->join('affectation', 'affectation.emp_code = employe.emp_code')
                                ->join('region', 'region.reg_code = affectation.reg_code')
                                ->like('region.reg_nom', $lieu);
            }
            $countPerms = $prmCountBuilder->countAllResults();

            return $this->respond([
                'avg_days' => $avgDays,
                'utilization_rate' => $utilizationRate,
                'absenteeism_rate' => min(100, $absenteeismRate),
                'total_records' => $countConges + $countPerms
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[getAbsenceKPIs] Error: ' . $e->getMessage());
            return $this->respond([
                'avg_days' => 0,
                'utilization_rate' => 0,
                'absenteeism_rate' => 0,
                'total_records' => 0
            ]);
        }
    }
}
