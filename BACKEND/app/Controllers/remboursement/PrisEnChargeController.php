<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\PrisEnChargeModel;
use App\Models\remboursement\CentreSanteModel;
use App\Models\remboursement\ConventionModel;
use App\Models\remboursement\ConjointeModel;
use App\Models\remboursement\EnfantModel;

class PrisEnChargeController extends ResourceController
{
    use ResponseTrait;

    private function generatePecNum(): string
    {
        $db = \Config\Database::connect();
        $yy = date('y');
        // 'before' puts % at the start: LIKE '%/PC-25'
        $last = $db->table('pris_en_charge')
            ->like('pec_num', '/PC-' . $yy, 'before') 
            ->orderBy('pec_code', 'DESC')
            ->get()
            ->getRow();
        $next = 1;
        if ($last && $last->pec_num) {
            $parts = explode('/', $last->pec_num);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $next = intval($parts[0]) + 1;
            }
        }
        return sprintf('%03d/ARMP/DG/DAAF/SRH/PC-%s', $next, $yy);
    }

    // ... (keep getFonctionDirection) ...

    /**
     * Retourne la fonction et la direction (latest affectation)
     */
    private function getFonctionDirection(int $empCode): array
    {
        $db = \Config\Database::connect();
        $row = $db->table('affectation a')
            ->select('pst.pst_fonction, dir.dir_nom, dir.dir_abbreviation')
            ->join('poste pst', 'pst.pst_code = a.pst_code', 'left')
            ->join('direction dir', 'dir.dir_code = pst.dir_code', 'left')
            ->where('a.emp_code', $empCode)
            ->where('a.affec_etat', 'active')
            ->orderBy('a.affec_date_debut', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        return [
            'fonction' => $row['pst_fonction'] ?? '',
            'direction' => $row['dir_nom'] ?? '',
        ];
    }
    


    /**
     * Résout le bénéficiaire (agent/conjoint/enfant) et applique les règles métier
     */
    private function resolveBeneficiaire(int $empCode, array $payload): array
    {
        $type = strtolower($payload['beneficiaire_type'] ?? 'agent');
        $db = \Config\Database::connect();

        if ($type === 'agent') {
            $emp = $db->table('employe')->where('emp_code', $empCode)->get()->getRowArray();
            if (!$emp) throw new \Exception('Employé introuvable');
            return [
                'lien' => 'AGENT',
                'nom' => $emp['nom'] ?? '',
                'prenom' => $emp['prenom'] ?? '',
                'conj_code' => null,
                'enf_code' => null,
            ];
        }

        if ($type === 'conjoint' || $type === 'conjointe') {
            $conjCode = $payload['conj_code'] ?? null;
            if (!$conjCode) throw new \Exception('conj_code obligatoire');
            $exists = $db->table('emp_conj')
                ->where('emp_code', $empCode)
                ->where('conj_code', $conjCode)
                ->countAllResults();
            if ($exists === 0) throw new \Exception('Conjoint non lié à cet employé');
            $conj = (new ConjointeModel())->find($conjCode);
            return [
                'lien' => 'CONJOINT(E)',
                'nom' => $conj['conj_nom'] ?? '',
                'prenom' => '',
                'conj_code' => $conjCode,
                'enf_code' => null,
            ];
        }

        if ($type === 'enfant') {
            $enfCode = $payload['enf_code'] ?? null;
            if (!$enfCode) throw new \Exception('enf_code obligatoire');

            
            // Check direct ownership via enfant table
            $enf = (new EnfantModel())->find($enfCode);
            if (!$enf) throw new \Exception('Enfant introuvable');
            
            if ($enf['emp_code'] != $empCode) {
                 throw new \Exception('Cet enfant n’appartient pas à cet employé');
            }

            if (!empty($enf['date_naissance'])) {
                $age = (new \DateTime($enf['date_naissance']))->diff(new \DateTime())->y;
                if ($age >= 21) throw new \Exception('Âge de l’enfant >= 21 ans');
            }
            return [
                'lien' => 'ENFANT',
                'nom' => $enf['enf_nom'] ?? '',
                'prenom' => '',
                'conj_code' => null,
                'enf_code' => $enfCode,
            ];
        }

        throw new \Exception('Type de bénéficiaire invalide');
    }

    /**
     * Liste toutes les prises en charge
     */
    public function getAll()
    {
        $model = new PrisEnChargeModel();
        $prises = $model->select('pris_en_charge.*, employe.emp_nom AS nom_emp, employe.emp_prenom AS prenom_emp, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom')
            ->join('employe', 'employe.emp_code = pris_en_charge.emp_code', 'left')
            ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
            ->join('conjointe', 'conjointe.conj_code = pris_en_charge.conj_code', 'left')
            ->join('enfant', 'enfant.enf_code = pris_en_charge.enf_code', 'left')
            ->findAll();

        // Ajouter les infos du bénéficiaire à chaque PEC
        foreach ($prises as &$prise) {
            if (!empty($prise['conj_code'])) {
                $prise['beneficiaire_type'] = 'CONJOINT(E)';
                $prise['beneficiaire_nom'] = $prise['conj_nom'] ?? '';
            } elseif (!empty($prise['enf_code'])) {
                $prise['beneficiaire_type'] = 'ENFANT';
                $prise['beneficiaire_nom'] = $prise['enf_nom'] ?? '';
            } else {
                $prise['beneficiaire_type'] = 'AGENT';
                $prise['beneficiaire_nom'] = trim(($prise['nom_emp'] ?? '') . ' ' . ($prise['prenom_emp'] ?? ''));
            }
        }

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($prises);
    }

    /**
     * Liste les PECs d'un employé avec infos bénéficiaire (pour demande remboursement)
     */
    public function getByEmployee($empCode = null)
    {
        if (!$empCode) return $this->fail('emp_code requis');
        
        $model = new PrisEnChargeModel();
        // On récupère TOUTES les PECs de l'employé avec infos bénéficiaire
        $prises = $model->select('pris_en_charge.*, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom, employe.emp_nom, employe.emp_prenom')
            ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
            ->join('conjointe', 'conjointe.conj_code = pris_en_charge.conj_code', 'left')
            ->join('enfant', 'enfant.enf_code = pris_en_charge.enf_code', 'left')
            ->join('employe', 'employe.emp_code = pris_en_charge.emp_code', 'left')
            ->where('pris_en_charge.emp_code', $empCode)
            ->orderBy('pris_en_charge.pec_code', 'DESC')
            ->findAll();

        // Ajouter les infos du bénéficiaire à chaque PEC
        foreach ($prises as &$prise) {
            if (!empty($prise['conj_code'])) {
                $prise['beneficiaire_type'] = 'CONJOINT(E)';
                $prise['beneficiaire_nom'] = $prise['conj_nom'] ?? '';
            } elseif (!empty($prise['enf_code'])) {
                $prise['beneficiaire_type'] = 'ENFANT';
                $prise['beneficiaire_nom'] = $prise['enf_nom'] ?? '';
            } else {
                $prise['beneficiaire_type'] = 'AGENT';
                $prise['beneficiaire_nom'] = trim(($prise['emp_nom'] ?? '') . ' ' . ($prise['emp_prenom'] ?? ''));
            }
        }

        return $this->respond($prises);
    }

    /**
     * Détail d'une prise en charge
     */
    public function get($id = null)
    {
        $model = new PrisEnChargeModel();
        $prise = $model->select('pris_en_charge.*, employe.emp_nom AS nom_emp, employe.emp_prenom AS prenom_emp, employe.emp_im_armp AS matricule, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom')
            ->join('employe', 'employe.emp_code = pris_en_charge.emp_code', 'left')
            ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
            ->join('conjointe', 'conjointe.conj_code = pris_en_charge.conj_code', 'left')
            ->join('enfant', 'enfant.enf_code = pris_en_charge.enf_code', 'left')
            ->where('pris_en_charge.pec_code', $id)
            ->first();

        if (!$prise) {
            return $this->failNotFound('Prise en charge non trouvée');
        }

        // Standardize output for frontend
        $benefNom = $prise['nom_emp'] ?? '';
        $benefPrenom = $prise['prenom_emp'] ?? '';
        $type = 'AGENT';
        
        if (!empty($prise['conj_code'])) {
            $benefNom = $prise['conj_nom'];
            $benefPrenom = '';
            $type = 'CONJOINT(E)';
        } elseif (!empty($prise['enf_code'])) {
            $benefNom = $prise['enf_nom'];
            $benefPrenom = '';
            $type = 'ENFANT';
        }

        $prise['beneficiaire_type'] = $type;
        $prise['beneficiaire_nom'] = trim($benefNom . ' ' . $benefPrenom);
        
        return $this->respond($prise);
    }

    /**
     * Créer une prise en charge
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        try {
            if (!isset($data['emp_code'])) {
                return $this->fail('Données obligatoires manquantes (emp_code)');
            }

            $empCode = (int)$data['emp_code'];

            // Vérifier le centre de santé si fourni
            if (isset($data['cen_code']) && $data['cen_code']) {
                $centreModel = new CentreSanteModel();
                if (!$centreModel->find($data['cen_code'])) {
                    return $this->failValidationErrors('Centre de santé non trouvé');
                }
            }

            // Résoudre le bénéficiaire
            $benef = $this->resolveBeneficiaire($empCode, $data);

            $insert = [
                'emp_code' => $empCode,
                'cen_code' => $data['cen_code'] ?? null,
                'conj_code' => $benef['conj_code'],
                'enf_code' => $benef['enf_code'],
                'pec_num' => $this->generatePecNum(),
                'pec_creation' => date('Y-m-d'),
                'pec_approuver' => false,
                'pec_date_arrive' => $data['pec_date_arrive'] ?? null,
                'pec_date_depart' => $data['pec_date_depart'] ?? null,
            ];

            $model = new PrisEnChargeModel();
            $id = $model->insert($insert);

            if ($id === false) {
                throw new \Exception('Impossible de créer la prise en charge');
            }

            $created = $model->find($id);
            return $this->respondCreated($created);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * Approuver une prise en charge (mettre à jour dates et centre)
     */
    public function approuver($id = null)
    {
        $model = new PrisEnChargeModel();
        $prise = $model->find($id);

        if (!$prise) {
            return $this->failNotFound('Prise en charge non trouvée');
        }

        $data = $this->request->getJSON(true);

        // Vérifier les champs requis pour l'approbation
        if (empty($data['pec_date_arrive']) || empty($data['cen_code'])) {
            return $this->failValidationErrors('Date d\'arrivée et centre de santé requis pour l\'approbation');
        }

        // Vérifier que le centre existe
        $centreModel = new CentreSanteModel();
        if (!$centreModel->find($data['cen_code'])) {
            return $this->failValidationErrors('Centre de santé non trouvé');
        }

        $update = [
            'pec_date_arrive' => $data['pec_date_arrive'],
            'pec_date_depart' => $data['pec_date_depart'] ?? null,
            'cen_code' => $data['cen_code'],
            'pec_approuver' => true
        ];

        // Enregistrer le validateur si fourni
        if (isset($data['validateur_emp_code'])) {
            $update['emp_code_1'] = $data['validateur_emp_code'];
        }

        $model->update($id, $update);

        $updated = $model->find($id);
        return $this->respond([
            'prise_en_charge' => $updated,
            'message' => 'Prise en charge approuvée'
        ]);
    }

    /**
     * Générer le bulletin PDF d'une prise en charge
     * Format EXACT conforme au document officiel - Utilise DomPDF
     */
    public function genererBulletin($id = null)
    {
        try {
            $model = new PrisEnChargeModel();
            
            $prise = $model->select('pris_en_charge.*, employe.emp_nom AS nom_emp, employe.emp_prenom AS prenom_emp, employe.emp_im_armp AS matricule, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom, enfant.enf_num')
                ->join('employe', 'employe.emp_code = pris_en_charge.emp_code', 'left')
                ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
                ->join('conjointe', 'conjointe.conj_code = pris_en_charge.conj_code', 'left')
                ->join('enfant', 'enfant.enf_code = pris_en_charge.enf_code', 'left')
                ->where('pris_en_charge.pec_code', $id)
                ->first();

            if (!$prise) {
                return $this->failNotFound('Prise en charge non trouvee');
            }

            // Determiner si le malade est l'agent lui-même
            $maladeEstAgent = empty($prise['conj_code']) && empty($prise['enf_code']);
            
            // Determiner le beneficiaire (malade)
            $benefNom = '';
            $lien = '';
            
            if ($maladeEstAgent) {
                $benefNom = ($prise['nom_emp'] ?? '') . ' ' . ($prise['prenom_emp'] ?? '');
                $lien = 'Agent';
            } elseif (!empty($prise['conj_code'])) {
                $benefNom = $prise['conj_nom'] ?? '';
                $lien = 'Conjoint(e)';
            } elseif (!empty($prise['enf_code'])) {
                $benefNom = $prise['enf_nom'] ?? '';
                $lien = 'Enfant' . (!empty($prise['enf_num']) ? ' ' . $prise['enf_num'] : '');
            }

            $fd = $this->getFonctionDirection((int)$prise['emp_code']);
            $fonction = $fd['fonction'] ?: '';
            $direction = $fd['direction'] ?: '';
            
            $nomComplet = ($prise['nom_emp'] ?? '') . ' ' . ($prise['prenom_emp'] ?? '');

            // Logo en base64
            $logoPath = FCPATH . 'logo.png';
            $logoBase64 = '';
            if (extension_loaded('gd') && file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            }

            // Construire le HTML - Format EXACT du document officiel
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 10px;
            line-height: 1.3;
            color: #000;
        }
        
        /* Header avec logo à gauche */
        .header-row {
            width: 100%;
            margin-bottom: 5px;
        }
        .logo-section {
            float: left;
            width: 180px;
        }
        .logo-section img {
            width: 45px;
            height: auto;
            float: left;
            margin-right: 8px;
        }
        .org-name {
            font-size: 8px;
            line-height: 1.2;
            padding-top: 5px;
        }
        .title-section {
            text-align: center;
            padding-top: 10px;
        }
        .main-title {
            font-size: 13px;
            font-weight: bold;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        
        /* Cadre principal */
        .content-box {
            border: 1px solid #000;
            padding: 8px 12px;
            margin-top: 8px;
        }
        
        .section-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            font-size: 10px;
            margin: 8px 0 6px 0;
        }
        
        .info-row {
            margin: 4px 0;
        }
        .info-row::after {
            content: "";
            clear: both;
            display: table;
        }
        .info-left {
            float: left;
            width: 55%;
        }
        .info-right {
            float: right;
            text-align: right;
            width: 45%;
        }
        
        /* Cases NON / OUI - Style exact du document officiel */
        .checkbox-container {
            display: inline;
        }
        .checkbox-box {
            display: inline-block;
            border: 1px solid #000;
            padding: 1px 4px;
            margin: 0 3px;
            font-size: 9px;
            min-width: 25px;
            text-align: center;
        }
        
        .footer-title {
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            margin-top: 12px;
            text-decoration: underline;
        }
        
        .signature-name {
            font-weight: bold;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- HEADER : Logo + Org à gauche, Titre centré -->
    <div class="header-row clearfix">
        <div class="logo-section">';
            
            if ($logoBase64) {
                $html .= '
            <img src="' . $logoBase64 . '" alt="Logo">
            <div class="org-name">AUTORITE DE REGULATION<br>DES MARCHES PUBLICS</div>';
            }
            
            $html .= '
        </div>
        <div class="title-section">
            <div class="main-title">BULLETIN DE PRISE EN CHARGE</div>
        </div>
    </div>
    
    <!-- CONTENU PRINCIPAL -->
    <div class="content-box">
        <!-- SECTION AGENT -->
        <div class="section-title">RENSEIGNEMENTS CONCERNANT L\'AGENT</div>
        
        <div class="info-row">
            <span class="info-left">Nom et Prénoms : ' . htmlspecialchars($nomComplet) . '</span>
            <span class="info-right">Matricule : ' . htmlspecialchars($prise['matricule'] ?? '') . '</span>
        </div>
        <div class="info-row">Fonction : ' . htmlspecialchars($fonction) . '</div>
        <div class="info-row">Direction / Service : ' . htmlspecialchars($direction) . '</div>
        
        <!-- SECTION MALADE -->
        <div class="section-title">RENSEIGNEMENTS CONCERNANT LE MALADE</div>
        
        <div class="info-row">
            Le malade est l\'agent : 
            <span class="checkbox-box">' . ($maladeEstAgent ? '' : 'X') . ' NON</span>
            <span class="checkbox-box">' . ($maladeEstAgent ? 'X' : '') . ' OUI</span>
        </div>
        <div class="info-row">Noms et prénoms du malade : ' . htmlspecialchars($benefNom) . '</div>
        <div class="info-row">Lien: ' . htmlspecialchars($lien) . '</div>
        <div class="info-row">
            <span class="info-left">Ref. n° : ' . htmlspecialchars($prise['pec_num'] ?? '') . '</span>
            <span class="info-right">Antananarivo, le</span>
        </div>
        <div class="info-row">Date et heure d\'arrivée :</div>
        <div class="info-row">
            <span class="info-left">Date et heure de départ :</span>
            <span class="info-right">Personnel d\'Appui Administratif et Financier</span>
        </div>
        <div class="info-row">
            <span class="info-left">Signature et tampon du Médecin</span>
            <span class="info-right"></span>
        </div>
        <div class="info-row" style="margin-top: 15px;">
            <span class="info-right signature-name">RAZAFIMANDIMBY Danielle Tolisoa</span>
        </div>
    </div>
    
    <div class="footer-title">BULLETIN DE PRISE EN CHARGE</div>
</body>
</html>';

            // Générer le PDF avec DomPDF
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $output = $dompdf->output();
            
            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="bulletin_pec_' . $id . '.pdf"')
                ->setBody($output);
                
        } catch (\Exception $e) {
            log_message('error', 'PDF generation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->fail('Erreur lors de la generation du bulletin: ' . $e->getMessage(), 500);
        }
    }
}


