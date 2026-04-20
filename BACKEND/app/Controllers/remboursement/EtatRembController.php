<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\EtatRembModel;
use App\Models\remboursement\DemandeRembModel;
use App\Models\remboursement\EngagementModel;
use App\Services\EmailService;

class EtatRembController extends ResourceController
{
    use ResponseTrait;

    /**
     * Liste tous les états de remboursement avec info agent
     */
    public function index()
    {
        $db = \Config\Database::connect();
        
        // Sélectionner les états avec les infos de base de l'agent
        $etats = $db->table('etat_remb')
            ->select('etat_remb.*, 
                      employe.emp_nom AS nom_emp, 
                      employe.emp_prenom AS prenom_emp, 
                      employe.emp_im_armp AS matricule')
            ->join('employe', 'employe.emp_code = etat_remb.emp_code', 'left')
            ->orderBy('etat_remb.eta_date', 'DESC')
            ->get()
            ->getResultArray();

        // Récupérer les métadonnées (nb demandes, centre) pour chaque état
        foreach ($etats as &$etat) {
            $etat['nb_demandes'] = $db->table('demande_remb')
                ->where('eta_code', $etat['eta_code'])
                ->countAllResults();
            
            // On récupère le centre associé (via la première demande liée)
            $demandeInfo = $db->table('demande_remb')
                ->select('centre_sante.cen_code, centre_sante.cen_nom')
                ->join('centre_sante', 'centre_sante.cen_code = demande_remb.cen_code', 'left')
                ->where('eta_code', $etat['eta_code'])
                ->limit(1)
                ->get()
                ->getRowArray();
            
            $etat['cen_code'] = $demandeInfo['cen_code'] ?? null;
            $etat['cen_nom'] = $demandeInfo['cen_nom'] ?? null;
            
            // Conversion explicite des montants pour éviter les strings JSON
            $etat['eta_total'] = (float)($etat['eta_total'] ?? 0);
        }

        return $this->respond($etats);
    }

    /**
     * Afficher le détail d'un état
     */
    public function show($id = null)
    {
        $db = \Config\Database::connect();
        $etat = $db->table('etat_remb')
            ->select('etat_remb.*, 
                      employe.emp_nom AS nom_emp, 
                      employe.emp_prenom AS prenom_emp, 
                      employe.emp_im_armp AS matricule')
            ->join('employe', 'employe.emp_code = etat_remb.emp_code', 'left')
            ->where('etat_remb.eta_code', $id)
            ->get()->getRowArray();

        if (!$etat) {
            return $this->failNotFound('État non trouvé');
        }
        
        // On récupère le centre associé (via la première demande liée)
        $demandeInfo = $db->table('demande_remb')
            ->select('centre_sante.cen_code, centre_sante.cen_nom')
            ->join('centre_sante', 'centre_sante.cen_code = demande_remb.cen_code', 'left')
            ->where('eta_code', $id)
            ->limit(1)
            ->get()
            ->getRowArray();
        
        $etat['cen_code'] = $demandeInfo['cen_code'] ?? null;
        $etat['cen_nom'] = $demandeInfo['cen_nom'] ?? null;
        
        // nb_demandes
        $etat['nb_demandes'] = $db->table('demande_remb')
            ->where('eta_code', $id)
            ->countAllResults();
        
        $etat['eta_total'] = (float)($etat['eta_total'] ?? 0);

        return $this->respond($etat);
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
        $employee = $db->table('employe')
            ->select('COALESCE(poste.dir_code, service.dir_code) AS dir_code')
            ->join('affectation', "affectation.emp_code = employe.emp_code AND affectation.affec_etat = 'active'", 'left')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('service', 'service.srvc_code = poste.srvc_code', 'left')
            ->where('employe.emp_code', $empCode)
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
        
        // 3. Obtenir de manière sécurisée le prochain numéro séquentiel
        // On récupère tous les numéros existants en PHP pour éviter les crashs SQL (si un ancien numéro utilise des lettres)
        $etats = $db->table('etat_remb')->select('etat_num')->get()->getResultArray();
        
        $maxId = 0;
        foreach ($etats as $etat) {
            if (!empty($etat['etat_num'])) {
                // On extrait la partie avant le premier '/'
                $parts = explode('/', $etat['etat_num']);
                if (is_numeric($parts[0])) {
                    $id = (int)$parts[0];
                    if ($id > $maxId) {
                        $maxId = $id;
                    }
                }
            }
        }
        
        $nextId = $maxId + 1;
        $sequential = str_pad($nextId, 3, '0', STR_PAD_LEFT);  // Format NNN
        
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
    /**
     * Marquer un état comme mandaté
     */
    public function mandater($id)
    {
        $model = new EtatRembModel();
        $etat = $model->find($id);
        
        if (!$etat) {
            return $this->failNotFound('État non trouvé');
        }

        $model->update($id, ['eta_libelle' => 'MANDATE']);

        // Insérer dans la table engagement
        $engModel = new EngagementModel();
        $engModel->insert([
            'eng_date' => date('Y-m-d H:i:s'),
            'eta_code' => $id
        ]);

        return $this->respond(['message' => 'État marqué comme mandaté', 'status' => 'MANDATE']);
    }

    /**
     * Marquer un état comme envoyé à l'agent comptable
     */
    public function agentComptable($id)
    {
        $model = new EtatRembModel();
        $etat = $model->find($id);
        
        if (!$etat) {
            return $this->failNotFound('État non trouvé');
        }

        if ($etat['eta_libelle'] !== 'MANDATE') {
            return $this->fail('L\'état doit être mandaté avant d\'être envoyé à l\'agent comptable');
        }

        $model->update($id, ['eta_libelle' => 'AGENT_COMPTABLE']);

        // 1. Insérer dans la table engagement
        $engModel = new EngagementModel();
        $engModel->insert([
            'eng_date' => date('Y-m-d H:i:s'),
            'eta_code' => $id
        ]);

        // 2. Notification Email (SAFE)
        try {
            $db = \Config\Database::connect();
            
            // Récupérer l'employé et son mail
            $employee = $db->table('employe')
                ->where('emp_code', $etat['emp_code'])
                ->get()->getRowArray();
            
            if ($employee && !empty($employee['emp_mail'])) {
                // Récupérer la liste des demandes détaillées
                $demandes = $db->table('demande_remb')
                    ->select('demande_remb.*, objet_remboursement.obj_article')
                    ->join('objet_remboursement', 'objet_remboursement.obj_code = demande_remb.obj_code', 'left')
                    ->where('eta_code', $id)
                    ->get()->getResultArray();
                
                $emailService = new EmailService();
                
                // 3. Générer le PDF pour l'attachement
                $pdfController = new EtatPdfController();
                $pdfData = $pdfController->getBinaryPdf($id);

                $emailService->sendEtatComptableNotice(
                    $employee['emp_mail'],
                    $employee['emp_nom'] . ' ' . $employee['emp_prenom'],
                    $etat,
                    $demandes,
                    $pdfData // Contient 'content' et 'filename'
                );
            }
        } catch (\Throwable $e) {
            log_message('error', '[EtatMail-SafeFault] ' . $e->getMessage());
        }

        return $this->respond(['message' => 'État envoyé à l\'agent comptable', 'status' => 'AGENT_COMPTABLE']);
    }
    /**
     * Exporter un état de remboursement spécifique vers Excel
     */
    public function exportExcel($id)
    {
        $db = \Config\Database::connect();
        
        // 1. Infos de l'état
        $etat = $db->table('etat_remb')
            ->select('etat_remb.*, e.emp_im_armp, e.emp_nom, e.emp_prenom, d.dir_nom')
            ->join('employe e', 'e.emp_code = etat_remb.emp_code', 'left')
            ->join('affectation aff', "aff.emp_code = e.emp_code AND aff.affec_etat = 'active'", 'left')
            ->join('poste p', 'p.pst_code = aff.pst_code', 'left')
            ->join('direction d', 'd.dir_code = p.dir_code', 'left')
            ->where('etat_remb.eta_code', $id)
            ->get()->getRowArray();

        if (!$etat) return $this->failNotFound('Etat non trouvé');

        // 2. Demandes liées
        $demandes = $db->table('demande_remb dr')
            ->select('dr.*, pec.pec_num, f.fac_num, obj.obj_article,
                      CASE 
                        WHEN pec.conj_code IS NOT NULL THEN \'C\'
                        WHEN pec.enf_code IS NOT NULL THEN \'E\'
                        ELSE \'A\'
                      END as lien_code')
            ->join('pris_en_charge pec', 'pec.pec_code = dr.pec_code', 'left')
            ->join('facture f', 'f.fac_code = dr.fac_code', 'left')
            ->join('objet_remboursement obj', 'obj.obj_code = dr.obj_code', 'left')
            ->where('dr.eta_code', $id)
            ->orderBy('dr.rem_date', 'ASC')
            ->get()->getResultArray();

        $html = "
        <meta charset='utf-8'>
        <h3>ÉTAT DE REMBOURSEMENT N° : " . $etat['etat_num'] . "</h3>
        <p><b>Agent :</b> " . $etat['emp_nom'] . " " . $etat['emp_prenom'] . " (" . $etat['emp_im_armp'] . ")</p>
        <p><b>Direction :</b> " . ($etat['dir_nom'] ?? '-') . "</p>
        <p><b>Date :</b> " . date('d/m/Y', strtotime($etat['eta_date'])) . "</p>
        
        <table border='1'>
            <tr style='background-color: #f2f2f2; font-weight: bold;'>
                <td>LIEN</td>
                <td>N° Bulletin PEC</td>
                <td>N° Ordonnance</td>
                <td>N° Facture</td>
                <td>Date Acte</td>
                <td>Objet du remboursement</td>
                <td>Montant (Ar)</td>
                <td>Montant en lettres</td>
            </tr>";

        foreach ($demandes as $d) {
            $bgColor = '#ffffff'; 
            if ($d['lien_code'] === 'C') $bgColor = '#A0522D';
            if ($d['lien_code'] === 'E') $bgColor = '#4682B4';
            
            $textColor = ($bgColor === '#ffffff') ? '#000000' : '#ffffff';
            $date = $d['rem_date'] ? date('d/m/Y', strtotime($d['rem_date'])) : '-';
            $valMontant = (float)($d['rem_montant'] ?? 0);

            $html .= "
            <tr style='background-color: $bgColor; color: $textColor;'>
                <td style='text-align:center;'>" . $d['lien_code'] . "</td>
                <td>" . htmlspecialchars($d['pec_num'] ?? '') . "</td>
                <td>" . htmlspecialchars($d['rem_num'] ?? '') . "</td>
                <td>" . htmlspecialchars($d['fac_num'] ?? '') . "</td>
                <td>$date</td>
                <td>" . htmlspecialchars($d['obj_article'] ?? '') . "</td>
                <td style='text-align:right;'>" . number_format($valMontant, 2, ',', ' ') . "</td>
                <td>" . htmlspecialchars($d['rem_montant_lettre'] ?? '') . "</td>
            </tr>";
        }
        
        $html .= "
            <tr style='font-weight:bold; background-color:#eee;'>
                <td colspan='6' style='text-align:right;'>TOTAL GÉNÉRAL</td>
                <td style='text-align:right;'>" . number_format((float)$etat['eta_total'], 2, ',', ' ') . " Ar</td>
                <td></td>
            </tr>
        </table>";

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel')
            ->setHeader('Content-Disposition', 'attachment; filename="etat_remboursement_' . $id . '.xls"')
            ->setBody("\xEF\xBB\xBF" . $html);
    }
}

