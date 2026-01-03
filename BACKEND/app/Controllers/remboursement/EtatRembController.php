<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\EtatRembModel;
use App\Models\remboursement\DemandeRembModel;

class EtatRembController extends ResourceController
{
    use ResponseTrait;

    /**
     * Liste tous les états de remboursement avec info agent
     */
    public function index()
    {
        $db = \Config\Database::connect();
        
        $etats = $db->table('etat_remb')
            ->select('etat_remb.*, 
                      employee.emp_nom AS nom_emp, 
                      employee.emp_prenom AS prenom_emp, 
                      employee.emp_imarmp AS matricule,
                      COUNT(demande_remb.rem_code) AS nb_demandes')
            ->join('employee', 'employee.emp_code = etat_remb.emp_code', 'left')
            ->join('demande_remb', 'demande_remb.eta_code = etat_remb.eta_code', 'left')
            ->groupBy('etat_remb.eta_code, employee.emp_nom, employee.emp_prenom, employee.emp_imarmp')
            ->orderBy('etat_remb.eta_date', 'DESC')
            ->get()
            ->getResultArray();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($etats);
    }

    /**
     * Liste les états de remboursement pour un agent donné
     */
    public function getByAgent($empCode)
    {
        $model = new EtatRembModel();
        $etats = $model->where('emp_code', $empCode)->findAll();

        return $this->respond($etats);
    }

    /**
     * Génère le numéro d'état selon format officiel: NNN/ARMP/DG/DAAF/[SERVICE]/[MOIS]-YY
     * Exemple: 086/ARMP/DG/DAAF/SRH/FM-25
     */
    private function generateEtatNum(int $empCode): string
    {
        $db = \Config\Database::connect();
        
        // 1. Déterminer le service de l'agent via affectation -> direction
        $employee = $db->table('employee')
            ->select('direction.dir_code, direction.dir_nom')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('direction', 'direction.dir_code = affectation.dir_code', 'left')
            ->where('employee.emp_code', $empCode)
            ->get()->getRowArray();
        
        // Mapping direction -> code service (selon l'organisation ARMP)
        $serviceMap = [
            1 => 'DG',   // Direction Générale
            2 => 'DAAF', // Direction des Affaires Administratives et Financières
            3 => 'DSI',  // Direction des Systèmes d'Information
            4 => 'SRH',  // Service Ressources Humaines
            5 => 'COMPTA', // Service Comptabilité
            6 => 'LOG'   // Service Logistique
        ];
        
        $serviceCode = $serviceMap[$employee['dir_code'] ?? 4] ?? 'SRH';  // Défaut: SRH
        
        // 2. Obtenir mois et année actuels
        $moisMap = [
            '01' => 'JA', '02' => 'FE', '03' => 'MA', '04' => 'AV',
            '05' => 'MI', '06' => 'JU', '07' => 'JL', '08' => 'AO',
            '09' => 'SE', '10' => 'OC', '11' => 'NO', '12' => 'DE'
        ];
        
        $moisActuel = date('m');
        $anneeActuel = date('y');  // 2 chiffres
        $moisCode = $moisMap[$moisActuel];
        
        // 3. Compter TOUS les états existants pour obtenir le séquentiel global
        $count = $db->table('etat_remb')->countAllResults();
        
        $sequential = str_pad($count + 1, 3, '0', STR_PAD_LEFT);  // Format NNN
        
        // 4. Construire le numéro final
        return "{$sequential}/ARMP/DG/DAAF/{$serviceCode}/{$moisCode}-{$anneeActuel}";
    }

    /**
     * Créer un nouvel état de remboursement
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (empty($data['emp_code'])) {
            return $this->fail('emp_code est requis');
        }

        // Générer automatiquement le numéro d'état
        $etatNum = $this->generateEtatNum($data['emp_code']);

        $model = new EtatRembModel();

        $insertData = [
            'etat_num' => $etatNum,
            'emp_code' => $data['emp_code'],
            'eta_date' => date('Y-m-d'),
            'eta_total' => 0
        ];

        $id = $model->insert($insertData);

        if ($id === false) {
            return $this->fail('Erreur lors de la création');
        }

        return $this->respondCreated([
            'eta_code' => $id,
            'etat_num' => $etatNum,
            'message' => 'État de remboursement créé'
        ]);
    }

    /**
     * Recalculer le total d'un état de remboursement
     */
    public function recalculerTotal($etaCode)
    {
        $db = \Config\Database::connect();

        // Somme des demandes liées
        $result = $db->table('demande_remb')
            ->selectSum('rem_montant', 'total')
            ->where('eta_code', $etaCode)
            ->get()->getRowArray();

        $total = $result['total'] ?? 0;

        $model = new EtatRembModel();
        $model->update($etaCode, ['eta_total' => $total]);

        return $total;
    }
}
