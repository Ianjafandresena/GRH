<?php

namespace App\Controllers\notification;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class NotificationController extends ResourceController
{
    use ResponseTrait;

    /**
     * Récupérer toutes les notifications actives
     */
    public function getAll()
    {
        $db = \Config\Database::connect();
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $notifications = [];
        
        // ===== NOTIFICATIONS CONGÉS =====
        
        // 1. Congés qui se terminent aujourd'hui
        $congesTerminant = $db->table('conge')
            ->select('conge.*, employee.emp_nom, employee.emp_prenom, type_conge.typ_appelation')
            ->join('employee', 'employee.emp_code = conge.emp_code', 'left')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
            ->where('DATE(conge.cng_fin)', $today)
            ->where('conge.cng_status', true)
            ->get()->getResultArray();
        
        foreach ($congesTerminant as $conge) {
            $notifications[] = [
                'id' => 'conge_fin_' . $conge['cng_code'],
                'type' => 'conge_fin',
                'titre' => 'Fin de congé aujourd\'hui',
                'message' => ($conge['emp_nom'] ?? '') . ' ' . ($conge['emp_prenom'] ?? '') . 
                            ' - ' . ($conge['typ_appelation'] ?? 'Congé') . ' se termine aujourd\'hui',
                'date' => $today,
                'icon' => 'event_busy',
                'color' => 'info',
                'link' => '/conge/detail/' . $conge['cng_code']
            ];
        }
        
        // 2. Demandes de congé non validées à 1 jour du départ
        $congesNonValides = $db->table('conge')
            ->select('conge.*, employee.emp_nom, employee.emp_prenom, type_conge.typ_appelation')
            ->join('employee', 'employee.emp_code = conge.emp_code', 'left')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
            ->where('DATE(conge.cng_debut)', $tomorrow)
            ->where('conge.cng_status IS NULL')
            ->get()->getResultArray();
        
        foreach ($congesNonValides as $conge) {
            $notifications[] = [
                'id' => 'conge_urgent_' . $conge['cng_code'],
                'type' => 'conge_urgent',
                'titre' => 'Congé non validé - URGENT',
                'message' => 'La demande de ' . ($conge['emp_nom'] ?? '') . ' ' . ($conge['emp_prenom'] ?? '') . 
                            ' débute demain et n\'est pas encore validée!',
                'date' => $today,
                'icon' => 'warning',
                'color' => 'warning',
                'link' => '/conge/detail/' . $conge['cng_code']
            ];
        }
        
        // 3. Congés en attente de validation
        $congesEnAttente = $db->table('conge')
            ->select('COUNT(*) as count')
            ->where('cng_status IS NULL')
            ->get()->getRowArray();
        
        if (($congesEnAttente['count'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'conge_attente',
                'type' => 'conge_attente',
                'titre' => 'Congés en attente',
                'message' => $congesEnAttente['count'] . ' demande(s) de congé en attente de validation',
                'date' => $today,
                'icon' => 'pending_actions',
                'color' => 'default',
                'link' => '/conge'
            ];
        }
        
        // ===== NOTIFICATIONS REMBOURSEMENTS =====
        
        // 4. Demandes de remboursement en attente
        $rembEnAttente = $db->table('demande_remb')
            ->select('COUNT(*) as count')
            ->where('dem_statut', 'En attente')
            ->get()->getRowArray();
        
        if (($rembEnAttente['count'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'remb_attente',
                'type' => 'remb_attente',
                'titre' => 'Remboursements en attente',
                'message' => $rembEnAttente['count'] . ' demande(s) de remboursement en attente',
                'date' => $today,
                'icon' => 'receipt_long',
                'color' => 'default',
                'link' => '/remboursement/demandes'
            ];
        }
        
        // 5. Demandes de remboursement validées (prêtes pour paiement)
        $rembValidees = $db->table('demande_remb')
            ->select('COUNT(*) as count')
            ->where('dem_statut', 'Validé')
            ->get()->getRowArray();
        
        if (($rembValidees['count'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'remb_valide',
                'type' => 'remb_valide',
                'titre' => 'Remboursements validés',
                'message' => $rembValidees['count'] . ' remboursement(s) validé(s) prêt(s) pour traitement',
                'date' => $today,
                'icon' => 'payments',
                'color' => 'info',
                'link' => '/remboursement/demandes'
            ];
        }
        
        // 6. États de remboursement en cours
        $etatsEnCours = $db->table('etat_remb')
            ->select('COUNT(*) as count')
            ->where('eta_statut', 'En cours')
            ->get()->getRowArray();
        
        if (($etatsEnCours['count'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'etat_encours',
                'type' => 'etat_encours',
                'titre' => 'États en cours',
                'message' => $etatsEnCours['count'] . ' état(s) de remboursement en cours de traitement',
                'date' => $today,
                'icon' => 'description',
                'color' => 'info',
                'link' => '/remboursement/etats'
            ];
        }
        
        // ===== NOTIFICATIONS PRISES EN CHARGE =====
        
        // 7. Prises en charge actives (non encore utilisées pour remboursement)
        $pecActives = $db->table('pris_en_charge')
            ->select('COUNT(*) as count')
            ->where('pec_statut', 'Actif')
            ->get()->getRowArray();
        
        if (($pecActives['count'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'pec_actives',
                'type' => 'pec_actives',
                'titre' => 'Prises en charge actives',
                'message' => $pecActives['count'] . ' prise(s) en charge active(s)',
                'date' => $today,
                'icon' => 'medical_services',
                'color' => 'default',
                'link' => '/pec'
            ];
        }
        
        // Trier par priorité (urgent en premier)
        usort($notifications, function($a, $b) {
            $priority = ['warning' => 1, 'info' => 2, 'default' => 3];
            return ($priority[$a['color']] ?? 9) - ($priority[$b['color']] ?? 9);
        });
        
        return $this->respond([
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
    }
    
    /**
     * Compter les notifications non lues
     */
    public function count()
    {
        $db = \Config\Database::connect();
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $count = 0;
        
        // ===== CONGÉS =====
        
        // Congés se terminant aujourd'hui
        $count += $db->table('conge')
            ->where('DATE(cng_fin)', $today)
            ->where('cng_status', true)
            ->countAllResults();
        
        // Congés urgents (non validés, départ demain)
        $count += $db->table('conge')
            ->where('DATE(cng_debut)', $tomorrow)
            ->where('cng_status IS NULL')
            ->countAllResults();
        
        // Congés en attente
        $attenteConge = $db->table('conge')
            ->where('cng_status IS NULL')
            ->countAllResults();
        if ($attenteConge > 0) $count++;
        
        // ===== REMBOURSEMENTS =====
        
        // Demandes en attente
        $attenteRemb = $db->table('demande_remb')
            ->where('dem_statut', 'En attente')
            ->countAllResults();
        if ($attenteRemb > 0) $count++;
        
        // Demandes validées
        $valideRemb = $db->table('demande_remb')
            ->where('dem_statut', 'Validé')
            ->countAllResults();
        if ($valideRemb > 0) $count++;
        
        // États en cours
        $etatsEncours = $db->table('etat_remb')
            ->where('eta_statut', 'En cours')
            ->countAllResults();
        if ($etatsEncours > 0) $count++;
        
        // ===== PRISES EN CHARGE =====
        
        // PEC actives
        $pecActives = $db->table('pris_en_charge')
            ->where('pec_statut', 'Actif')
            ->countAllResults();
        if ($pecActives > 0) $count++;
        
        return $this->respond(['count' => $count]);
    }
}
