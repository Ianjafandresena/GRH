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
            ->select('conge.*, employe.emp_nom, employe.emp_prenom, type_conge.typ_appelation')
            ->join('employe', 'employe.emp_code = conge.emp_code', 'left')
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
            ->select('conge.*, employe.emp_nom, employe.emp_prenom, type_conge.typ_appelation')
            ->join('employe', 'employe.emp_code = conge.emp_code', 'left')
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
        
        // 4. Demandes de remboursement en attente (status is NULL or false)
        $rembEnAttente = $db->table('demande_remb')
            ->select('COUNT(*) as count')
            ->where('rem_status', null)
            ->orWhere('rem_status', false)
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
        
        // 5. Demandes de remboursement validées
        $rembValidees = $db->table('demande_remb')
            ->select('COUNT(*) as count')
            ->where('rem_status', true)
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
        
        // 7. Prises en charge non encore validées
        $pecNonValides = $db->table('pris_en_charge')
            ->select('COUNT(*) as count')
            ->where('pec_approuver', null)
            ->orWhere('pec_approuver', false)
            ->get()->getRowArray();
        
        if (($pecNonValides['count'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'pec_actives',
                'type' => 'pec_actives',
                'titre' => 'Prises en charge en attente',
                'message' => $pecNonValides['count'] . ' prise(s) en charge en attente d\'approbation',
                'date' => $today,
                'icon' => 'medical_services',
                'color' => 'default',
                'link' => '/remboursement/prises-en-charge'
            ];
        }
        
        // ===== NOTIFICATIONS PERMISSIONS =====
        
        // 6. Permissions en attente de validation
        $prmsEnAttente = $db->table('permission')
            ->select('COUNT(*) as count')
            ->where('prm_status', false)
            ->get()->getRowArray();
        
        if (($prmsEnAttente['count'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'prm_attente',
                'type' => 'prm_attente',
                'titre' => 'Permissions en attente',
                'message' => $prmsEnAttente['count'] . ' permission(s) exceptionnelle(s) en attente',
                'date' => $today,
                'icon' => 'rule',
                'color' => 'default',
                'link' => '/conge/index?type=permission'
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
            ->where('rem_status', null)
            ->orWhere('rem_status', false)
            ->countAllResults();
        if ($attenteRemb > 0) $count++;
        
        // Demandes validées
        $valideRemb = $db->table('demande_remb')
            ->where('rem_status', true)
            ->countAllResults();
        if ($valideRemb > 0) $count++;
        
        // ===== PRISES EN CHARGE =====
        
        // PEC non approuvées
        $pecAttente = $db->table('pris_en_charge')
            ->where('pec_approuver', null)
            ->orWhere('pec_approuver', false)
            ->countAllResults();
        if ($pecAttente > 0) $count++;
        
        // ===== PERMISSIONS =====
        $attentePrm = $db->table('permission')
            ->where('prm_status', false)
            ->countAllResults();
        if ($attentePrm > 0) $count++;
        
        return $this->respond(['count' => $count]);
    }
}
