<?php
namespace App\Controllers\stage;

use CodeIgniter\RESTful\ResourceController;
use App\Models\stage\StageModel;
use App\Models\stage\StageCarriereModel;
use App\Models\affectation\AffectationModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class StageController extends ResourceController
{
    protected $modelName = StageModel::class;
    protected $format = 'json';

    protected $stageCarriereModel;

    public function __construct()
    {
        $this->stageCarriereModel = new StageCarriereModel();
    }

    /**
     * Statistiques globales pour les stages
     */
    public function stats()
    {
        $db = \Config\Database::connect();
        $today = date('Y-m-d');

        $total = $this->model->countAllResults();

        $enCours = $this->model
            ->where('stg_date_debut <=', $today)
            ->where('stg_date_fin >=', $today)
            ->countAllResults();

        $termines = $this->model
            ->where('stg_date_fin <', $today)
            ->countAllResults();

        $aVenir = $this->model
            ->where('stg_date_debut >', $today)
            ->countAllResults();

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'en_cours' => $enCours,
                'termines' => $termines,
                'a_venir' => $aVenir
            ]
        ]);
    }

    /**
     * Statistiques globales pour les demandes de documents de stage
     */
    public function statsDemandes()
    {
        $db = \Config\Database::connect();
        $total = $db->table('doc_stage')->countAllResults();

        $enAttente = $db->table('doc_stage')
            ->where('doc_stage_statut', 'en attente')
            ->countAllResults();

        $traitees = $db->table('doc_stage')
            ->where('doc_stage_statut', 'valider')
            ->countAllResults();

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'attente' => $enAttente,
                'traite' => $traitees
            ]
        ]);
    }

    public function index()
    {
        $filters = $this->request->getGet();

        $builder = $this->model
            ->select('stage.*')
            ->select('stagiaire.stgr_nom, stagiaire.stgr_prenom, stagiaire.stgr_nom_prenom')
            ->select('etablissement.etab_nom')
            ->select('stage_carriere.emp_code as encadreur_emp_code')
            ->select('employe.emp_nom as encadreur_nom, employe.emp_prenom as encadreur_prenom')
            ->join('stagiaire', 'stagiaire.stgr_code = stage.stgr_code')
            ->join('etablissement', 'etablissement.etab_code = stage.etab_code', 'left')
            ->join('stage_carriere', 'stage_carriere.stg_code = stage.stg_code', 'left')
            ->join('employe', 'employe.emp_code = stage_carriere.emp_code', 'left');

        if (!empty($filters['stagiaire'])) {
            $q = trim((string) $filters['stagiaire']);
            if (is_numeric($q)) {
                $builder->where('stage.stgr_code', $q);
            } else {
                $builder->groupStart()
                    ->like('stagiaire.stgr_nom', $q)
                    ->orLike('stagiaire.stgr_prenom', $q)
                    ->orLike('stagiaire.stgr_nom_prenom', $q)
                    ->groupEnd();
            }
        }

        if (!empty($filters['encadreur'])) {
            $q = trim((string) $filters['encadreur']);
            if (is_numeric($q)) {
                $builder->where('stage_carriere.emp_code', $q);
            } else {
                $builder->groupStart()
                    ->like('employe.emp_nom', $q)
                    ->orLike('employe.emp_prenom', $q)
                    ->groupEnd();
            }
        }

        if (!empty($filters['theme'])) {
            $builder->like('stage.stg_theme', trim((string) $filters['theme']));
        }

        if (!empty($filters['date_debut_from'])) {
            $builder->where('stage.stg_date_debut >=', $filters['date_debut_from']);
        }

        if (!empty($filters['date_debut_to'])) {
            $builder->where('stage.stg_date_debut <=', $filters['date_debut_to']);
        }

        $stages = $builder
            ->orderBy('stage.stg_date_debut', 'DESC')
            ->orderBy('stage.stg_code', 'DESC')
            ->findAll();

        return $this->respond($stages);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $id = $this->model->getInsertID();

        // Gestion de l'encadreur
        if (!empty($data['encadreur_emp_code'])) {
            $this->saveEncadreur($id, $data['encadreur_emp_code']);
        }

        $stage = $this->model->find($id);

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Stage créé avec succès',
            'data' => $stage,
        ]);
    }

    public function show($id = null)
    {
        $stage = $this->model
            ->select('stage.*')
            ->select('stagiaire.stgr_nom, stagiaire.stgr_prenom, stagiaire.stgr_nom_prenom')
            ->select('etablissement.etab_nom')
            ->select('stage_carriere.emp_code as encadreur_emp_code')
            ->select('employe.emp_nom as encadreur_nom, employe.emp_prenom as encadreur_prenom')
            ->join('stagiaire', 'stagiaire.stgr_code = stage.stgr_code', 'left')
            ->join('etablissement', 'etablissement.etab_code = stage.etab_code', 'left')
            ->join('stage_carriere', 'stage_carriere.stg_code = stage.stg_code', 'left')
            ->join('employe', 'employe.emp_code = stage_carriere.emp_code', 'left')
            ->where('stage.stg_code', $id)
            ->first();

        if (!$stage) {
            return $this->failNotFound('Stage introuvable');
        }

        return $this->respond($stage);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        // Gestion de l'encadreur
        if (array_key_exists('encadreur_emp_code', $data)) {
            $this->saveEncadreur($id, $data['encadreur_emp_code']);
        }

        $stage = $this->model->find($id);

        return $this->respond([
            'status' => 'success',
            'message' => 'Stage mis à jour avec succès',
            'data' => $stage,
        ]);
    }

    public function delete($id = null)
    {
        $stage = $this->model->find($id);
        if (!$stage) {
            return $this->failNotFound('Stage introuvable');
        }

        $this->model->delete($id);

        return $this->respondDeleted([
            'status' => 'success',
            'message' => 'Stage supprimé avec succès',
        ]);
    }

    public function assignCarriere($stgCode = null)
    {
        try {
            $stage = $this->model->find($stgCode);
            if (!$stage) {
                return $this->failNotFound('Stage introuvable');
            }

            $payload = $this->request->getJSON(true);
            $empCode = isset($payload['emp_code']) ? (int) $payload['emp_code'] : null;

            if (!$empCode) {
                return $this->failValidationErrors(['emp_code' => "Le champ 'emp_code' est obligatoire"]);
            }

            $affectationModel = new AffectationModel();
            $latestAffectation = $affectationModel
                ->where('emp_code', $empCode)
                ->orderBy('affec_date_debut', 'DESC')
                ->orderBy('affec_code', 'DESC')
                ->first();

            if (!$latestAffectation || empty($latestAffectation['pst_code'])) {
                return $this->failValidationErrors([
                    'pst_code' => "Aucune affectation trouvée pour cet employé (impossible de déterminer le poste)",
                ]);
            }

            $data = [
                'stg_code' => (int) $stgCode,
                'emp_code' => $empCode,
                'pst_code' => (int) $latestAffectation['pst_code'],
            ];

            if (!$this->stageCarriereModel->validate($data)) {
                return $this->failValidationErrors($this->stageCarriereModel->errors());
            }

            $existing = $this->stageCarriereModel->where('stg_code', (int) $stgCode)->first();
            if ($existing) {
                $this->stageCarriereModel->where('stg_code', (int) $stgCode)->delete();
            }

            if (!$this->stageCarriereModel->insert($data)) {
                return $this->failValidationErrors($this->stageCarriereModel->errors());
            }

            $row = $this->stageCarriereModel->where('stg_code', (int) $stgCode)->first();

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Carrière assignée au stage avec succès',
                'data' => $row,
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
    private function saveEncadreur($stgCode, $empCode)
    {
        if (!$stgCode)
            return;

        // Si empCode est vide, on supprime l'encadrement existant
        if (empty($empCode)) {
            $this->stageCarriereModel->where('stg_code', (int) $stgCode)->delete();
            return;
        }

        // Récupérer le poste actuel de l'encadreur
        $affectationModel = new AffectationModel();
        $latest = $affectationModel->where('emp_code', $empCode)
            ->orderBy('affec_date_debut', 'DESC')
            ->orderBy('affec_code', 'DESC')
            ->first();

        // On ne bloque pas si pas d'affectation, mais on ne peut pas créer le lien stage_carriere (contrainte FK pst_code)
        if (!$latest || empty($latest['pst_code'])) {
            return;
        }

        // Supprimer existant pour remplacer (on suppose un seul encadreur actif par stage ici)
        $this->stageCarriereModel->where('stg_code', (int) $stgCode)->delete();

        $this->stageCarriereModel->insert([
            'stg_code' => (int) $stgCode,
            'emp_code' => (int) $empCode,
            'pst_code' => (int) $latest['pst_code']
        ]);
    }

    public function telechargerConvention($id = null)
    {
        $stage = $this->model
            ->select('stage.*, stagiaire.stgr_nom, stagiaire.stgr_prenom, stagiaire.stgr_nom_prenom, stagiaire.stgr_contact, stagiaire.stgr_adresse, stagiaire.stgr_sexe')
            ->select('etablissement.etab_nom, etablissement.etab_adresse')
            ->join('stagiaire', 'stagiaire.stgr_code = stage.stgr_code')
            ->join('etablissement', 'etablissement.etab_code = stage.etab_code', 'left')
            ->where('stage.stg_code', $id)
            ->first();

        if (!$stage) {
            return $this->failNotFound('Stage introuvable');
        }

        if (!extension_loaded('gd')) {
            return $this->failServerError("Extension PHP GD requise pour les logos.");
        }

        $minLogo = $this->assetToDataUriJpegFromPng('assets/min.png', 300);
        $armpLogo = $this->assetToDataUriJpegFromPng('assets/logo_armp1.png', 220);

        if ($minLogo === '')
            $minLogo = $this->assetToDataUri('assets/min.png');
        if ($armpLogo === '')
            $armpLogo = $this->assetToDataUri('assets/logo_armp1.png');

        $dompdf = new Dompdf();
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf->setOptions($options);

        // Données pour le template
        $civilite = $stage['stgr_sexe'] ? 'Monsieur' : 'Mademoiselle';
        $nomComplet = strtoupper(($stage['stgr_nom'] ?? '') . ' ' . ($stage['stgr_prenom'] ?? ''));
        $adresse = $stage['stgr_adresse'] ?: 'Non renseignée';
        $contact = $stage['stgr_contact'] ?: 'Non renseigné';

        $dateDebut = date('d/m/Y', strtotime($stage['stg_date_debut']));
        $dateFin = date('d/m/Y', strtotime($stage['stg_date_fin']));

        $diff = strtotime($stage['stg_date_fin']) - strtotime($stage['stg_date_debut']);
        $jours = round($diff / (60 * 60 * 24));
        $mois = round($jours / 30);
        $dureeMots = $this->numberToWords((int) $mois);

        $matricule = $this->enregistrerDocStage($id, 'Convention de stage', 'valider');
        $yearCode = date('y', strtotime($stage['stg_date_debut']));
        $numRef = $matricule . "/ARMP/DG/DAAF/SRH- " . $yearCode;

        $html = $this->getConventionHtml([
            'numRef' => $numRef,
            'civilite' => $civilite,
            'nomComplet' => $nomComplet,
            'adresse' => $adresse,
            'contact' => $contact,
            'dureeMots' => $dureeMots,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'minLogo' => $minLogo,
            'armpLogo' => $armpLogo
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->respond([
            'status' => 'success',
            'pdf_base64' => base64_encode($dompdf->output()),
            'filename' => 'Convention_Stage_' . str_replace(' ', '_', $nomComplet) . '.pdf'
        ]);
    }

    public function telechargerDemandeAttestation($id = null)
    {
        $stage = $this->model
            ->select('stage.*, stagiaire.stgr_nom, stagiaire.stgr_prenom, stagiaire.stgr_nom_prenom, stagiaire.stgr_contact, stagiaire.stgr_adresse, stagiaire.stgr_sexe, stagiaire.stgr_filiere, stagiaire.stgr_niveau')
            ->join('stagiaire', 'stagiaire.stgr_code = stage.stgr_code')
            ->where('stage.stg_code', $id)
            ->first();

        if (!$stage) {
            return $this->failNotFound('Stage introuvable');
        }

        $dompdf = new Dompdf();
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf->setOptions($options);

        // Données pour le template
        $civilite = $stage['stgr_sexe'] ? 'Monsieur' : 'Mademoiselle';
        $nomComplet = strtoupper(($stage['stgr_nom'] ?? '') . ' ' . ($stage['stgr_prenom'] ?? ''));
        $adresse = $stage['stgr_adresse'] ?: 'Non renseignée';
        $contact = $stage['stgr_contact'] ?: 'Non renseigné';

        $dateDebut = date('d/m/Y', strtotime($stage['stg_date_debut']));
        $dateFin = date('d/m/Y', strtotime($stage['stg_date_fin']));

        $diff = strtotime($stage['stg_date_fin']) - strtotime($stage['stg_date_debut']);
        $jours = round($diff / (60 * 60 * 24));
        $moisNum = round($jours / 30);
        $dureeNumStr = str_pad((int) $moisNum, 2, '0', STR_PAD_LEFT);
        $dureeMots = $this->numberToWords((int) $moisNum);

        $db = \Config\Database::connect();
        $typeDoc = $db->table('type_document')->where('tdoc_nom', 'Attestation de stage')->get()->getRowArray();
        $docStage = null;
        if ($typeDoc) {
            $docStage = $db->table('doc_stage')
                ->where('tdoc_code', $typeDoc['tdoc_code'])
                ->where('stg_code', $id)
                ->get()->getRowArray();
        }

        if ($docStage && $docStage['doc_stage_statut'] === 'valider') {
            // Générer l'Attestation FINALE
            $matricule = $docStage['tdoc_matricule'] ?: '____';
            $yearCode = date('y', strtotime($stage['stg_date_debut']));
            $numRef = $matricule . "/ARMP/DG -" . $yearCode;

            $html = $this->getAttestationFinaleHtml([
                'armpLogo' => $this->assetToDataUriJpegFromPng('assets/logo_armp1.png', 80),
                'minLogo' => $this->assetToDataUriJpegFromPng('assets/min.png', 100),
                'matricule' => $matricule,
                'yearCode' => $yearCode,
                'civilite' => $civilite,
                'nomComplet' => $nomComplet,
                'dureeMots' => $dureeMots,
                'dureeChiffre' => $dureeNumStr,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'dateAujourdhui' => $this->formatDateLongFr(date('Y-m-d'))
            ]);
            $filename = 'Attestation_Stage_' . str_replace(' ', '_', $nomComplet) . '.pdf';
        } else {
            // Générer la DEMANDE (Request)
            $html = $this->getDemandeAttestationHtml([
                'civilite' => $civilite,
                'nomComplet' => $nomComplet,
                'adresse' => $adresse,
                'contact' => $contact,
                'theme' => $stage['stg_theme'] ?: 'Non spécifié',
                'filiere' => $stage['stgr_filiere'] ?: '',
                'niveau' => $stage['stgr_niveau'] ?: '',
                'dureeMois' => $dureeNumStr,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'dateAujourdhui' => $this->formatDateLongFr(date('Y-m-d'))
            ]);
            $this->enregistrerDocStage($id, 'Attestation de stage', 'en attente');
            $filename = 'Demande_Attestation_' . str_replace(' ', '_', $nomComplet) . '.pdf';
        }

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->respond([
            'status' => 'success',
            'pdf_base64' => base64_encode($dompdf->output()),
            'filename' => $filename
        ]);
    }

    private function getDemandeAttestationHtml($data)
    {
        $objectif = "Stage de fin d'étude";
        if (!empty($data['filiere'])) {
            $objectif .= " pour obtention de mon diplôme en " . $data['filiere'];
            if (!empty($data['niveau'])) {
                $objectif .= " ({$data['niveau']})";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1cm 2cm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10.5pt; line-height: 1.4; color: #000; }
        .top-date { text-align: right; margin-bottom: 15px; }
        .stagiaire-info { margin-bottom: 30px; }
        .stagiaire-info div { margin-bottom: 3px; }
        .label { font-weight: bold; text-decoration: underline; }
        
        .recipient-block { float: right; width: 60%; text-align: center; margin-top: 0.5cm; margin-bottom: 1.5cm; }
        .recipient-dg { font-weight: bold; margin-bottom: 3px; }
        .recipient-org { font-weight: bold; text-transform: uppercase; }
        
        .clear { clear: both; }
        
        .objet-section { margin-top: 0.5cm; margin-bottom: 0.8cm; }
        .objet-label { font-weight: bold; text-decoration: underline; }
        
        .salutation { margin-top: 0.5cm; margin-left: 1.5cm; margin-bottom: 0.8cm; }
        
        .body-text { margin-bottom: 20px; text-indent: 1cm; text-align: justify; }
        
        .details-list { margin-left: 0.8cm; margin-bottom: 20px; }
        .details-list div { margin-bottom: 10px; }
        
        .bold { font-weight: bold; }
        
        .closing { margin-bottom: 30px; text-align: justify; }
        
        .signature-block { float: right; width: 40%; text-align: center; margin-top: 0.5cm; }
        
        .footer-note { position: fixed; bottom: 1.5cm; left: 0; font-size: 9pt; text-decoration: underline; font-weight: bold; }
    </style>
</head>
<body>
    <div class="top-date">Antananarivo, le {$data['dateAujourdhui']}</div>

    <div class="stagiaire-info">
        <div><span class="label">Nom et Prénoms</span> : {$data['nomComplet']}</div>
        <div><span class="label">Adresse</span>: {$data['adresse']}</div>
        <div><span class="label">Tél.</span>: {$data['contact']}</div>
    </div>

    <div class="recipient-block">
        <div style="margin-bottom: 20px;">A</div>
        <div class="recipient-dg">Madame Le Directeur Général</div>
        <div class="recipient-org">AUTORITE DE REGULATION DES</div>
        <div class="recipient-org">MARCHES PUBLICS</div>
        <div style="text-decoration: underline;">101 - ANTANANARIVO</div>
    </div>

    <div class="clear"></div>

    <div class="objet-section">
        <span class="objet-label">Objet</span> : Demande d'une Attestation de Stage
    </div>

    <div class="salutation">
        Madame Le Directeur Général,
    </div>

    <div class="body-text">
        J'ai l'honneur de solliciter votre haute bienveillance de bien vouloir me délivrer une attestation de stage.
    </div>

    <div style="margin-bottom: 20px;">Ci-joint les renseignements me concernant :</div>
    <div class="details-list">
        <div>- <span class="label">Objectif du stage</span> : {$objectif}</div>
        <div>- <span class="label">Thème/Intitulé du stage</span> : {$data['theme']}</div>
        <div>- <span class="label">Période, durée du stage</span> : <span class="bold">{$data['dureeMois']}</span> mois pour compter du <span class="bold">{$data['dateDebut']} au {$data['dateFin']}</span></div>
    </div>

    <div class="closing">
        Dans l'attente d'une suite de votre part,<br>
        Veuillez agréer, Madame Le Directeur Général, les expressions de ma haute considération.
    </div>

    <div class="signature-block">
        Le Demandeur
    </div>

    <div class="footer-note">
        Avis du Directeur des Affaires Administratives et Financières
    </div>
</body>
</html>
HTML;
    }

    public function listerDemandesStage()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('doc_stage ds');
        $builder->select('ds.*, stg.stg_theme, stgr.stgr_nom, stgr.stgr_prenom, stgr.stgr_nom_prenom, td.tdoc_nom');
        $builder->join('stage stg', 'stg.stg_code = ds.stg_code');
        $builder->join('stagiaire stgr', 'stgr.stgr_code = stg.stgr_code');
        $builder->join('type_document td', 'td.tdoc_code = ds.tdoc_code');
        $builder->orderBy('ds.doc_stg_date', 'DESC');
        $builder->orderBy('ds.doc_stg_code', 'DESC');

        $demandes = $builder->get()->getResultArray();

        return $this->respond([
            'status' => 'success',
            'data' => $demandes
        ]);
    }

    public function validerDemandeStage($id = null)
    {
        $db = \Config\Database::connect();
        $doc = $db->table('doc_stage')->where('doc_stg_code', $id)->get()->getRowArray();

        if (!$doc) {
            return $this->failNotFound('Document de stage introuvable');
        }

        $db->table('doc_stage')
            ->where('doc_stg_code', $id)
            ->update(['doc_stage_statut' => 'valider']);

        return $this->respond([
            'status' => 'success',
            'message' => 'Document validé avec succès'
        ]);
    }

    private function getAttestationFinaleHtml($data)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1cm 1.5cm 1.5cm 1.5cm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11pt; line-height: 1.6; color: #000; }
        
        .header { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .logo-left { width: 70px; height: auto; }
        .logo-center { width: 90px; height: auto; }
        
        .sidebar { float: left; width: 35%; font-size: 8pt; font-weight: bold; margin-top: 10px; }
        .sidebar-line { height: 1px; border-top: 1px dashed #000; width: 100px; margin: 5px 0; }
        
        .clear { clear: both; }
        
        .title-box { text-align: center; margin: 1.5cm 0; }
        .title { font-size: 18pt; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        
        .ref-line { font-weight: bold; margin-bottom: 1cm; margin-left: 2cm; }
        
        .body-content { text-align: justify; margin: 0 1cm; text-indent: 1.5cm; }
        .body-line { margin-bottom: 20px; }
        
        .bold { font-weight: bold; }
        
        .footer-date { text-align: right; margin-top: 2cm; margin-right: 2cm; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8.5pt; line-height: 1.3; }
        .footer-line { border-top: 1px solid #000; margin: 5px 1cm; }
        .footer-slogan { font-style: italic; font-weight: bold; letter-spacing: 1px; margin-bottom: 5px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="33%"><img src="{$data['armpLogo']}" class="logo-left"></td>
            <td width="33%" align="center"><img src="{$data['minLogo']}" class="logo-center"></td>
            <td width="33%"></td>
        </tr>
    </table>

    <div class="sidebar">
        <div>DIRECTION GENERALE</div>
        <div class="sidebar-line"></div>
        <div>DIRECTION DES AFFAIRES</div>
        <div>ADMINISTRATIVES</div>
        <div>ET FINANCIERES</div>
        <div class="sidebar-line"></div>
    </div>

    <div class="clear"></div>

    <div class="title-box">
        <span class="title">ATTESTATION DE STAGE</span>
    </div>

    <div class="ref-line">
        N° <span class="bold">{$data['matricule']}</span> /ARMP/DG -<span class="bold">{$data['yearCode']}</span>
    </div>

    <div class="body-content">
        <div class="body-line">
            Le Directeur Général de l'Autorité de Régulation des Marchés Publics, atteste que,
        </div>
        <div class="body-line" align="center">
            <span class="bold">{$data['civilite']} {$data['nomComplet']}</span>
        </div>
        <div class="body-line">
            a effectué(e) un stage d'une durée de <span class="bold">{$data['dureeMots']} ({$data['dureeChiffre']})</span> mois au sein de notre Etablissement 
            pendant la période du <span class="bold">{$data['dateDebut']} au {$data['dateFin']}</span>.
        </div>
        <div class="body-line">
            En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.
        </div>
    </div>

    <div class="footer-date">
        Antananarivo, le {$data['dateAujourdhui']}
    </div>

    <div class="footer">
        <div class="footer-line"></div>
        <div class="footer-slogan">Disponibilité - Efficacité - Intégrité</div>
        <div>Immeuble Plan Anosy - 101 Antananarivo</div>
        <div>infos@armp.mg</div>
        <div>Tél: 020 22 285 93 - Fax: 020 22 677 31</div>
    </div>
</body>
</html>
HTML;
    }

    private function enregistrerDocStage($stgId, $tdocNom = 'Convention de stage', $statut = 'valider')
    {
        $db = \Config\Database::connect();
        $typeDoc = $db->table('type_document')->where('tdoc_nom', $tdocNom)->get()->getRowArray();
        $tdocCode = $typeDoc ? $typeDoc['tdoc_code'] : null;

        if ($tdocCode) {
            $exists = $db->table('doc_stage')
                ->where('tdoc_code', $tdocCode)
                ->where('stg_code', $stgId)
                ->get()->getRowArray();

            if ($exists && !empty($exists['tdoc_matricule'])) {
                // Si le matricule existe déjà, on vérifie juste s'il faut mettre à jour le statut
                if ($exists['doc_stage_statut'] !== $statut) {
                    $db->table('doc_stage')
                        ->where('doc_stg_code', $exists['doc_stg_code'])
                        ->update(['doc_stage_statut' => $statut]);
                }
                return $exists['tdoc_matricule'];
            }

            // Générer un numéro séquentiel GLOBAL pour l'année en cours (tous types de documents de stage confondus)
            $currentYear = date('Y');
            $count = $db->table('doc_stage')
                ->where("EXTRACT(YEAR FROM doc_stg_date) =", $currentYear)
                ->countAllResults();

            $num = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            $matricule = $num;

            if ($exists) {
                // Si l'enregistrement existait mais sans matricule, on le met à jour
                $db->table('doc_stage')
                    ->where('doc_stg_code', $exists['doc_stg_code'])
                    ->update([
                        'tdoc_matricule' => $matricule,
                        'doc_stage_statut' => $statut
                    ]);
            } else {
                $db->table('doc_stage')->insert([
                    'tdoc_code' => $tdocCode,
                    'stg_code' => $stgId,
                    'doc_stg_date' => date('Y-m-d'),
                    'tdoc_matricule' => $matricule,
                    'doc_stage_statut' => $statut
                ]);
            }
            return $matricule;
        }
        return '_______';
    }

    private function numberToWords($number)
    {
        $words = [
            1 => 'un',
            2 => 'deux',
            3 => 'trois',
            4 => 'quatre',
            5 => 'cinq',
            6 => 'six',
            7 => 'sept',
            8 => 'huit',
            9 => 'neuf',
            10 => 'dix',
            11 => 'onze',
            12 => 'douze'
        ];
        return $words[$number] ?? (string) $number;
    }

    private function getConventionHtml($data)
    {
        $armpLogo = $data['armpLogo'];
        $minLogo = $data['minLogo'];

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1cm 1.5cm 1.5cm 1.5cm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5pt; line-height: 1.4; color: #000; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header td { vertical-align: top; }
        .logo-left { width: 65px; height: auto; }
        .logo-center { width: 85px; height: auto; }
        .header-text-left { font-size: 7pt; text-transform: uppercase; line-height: 1.1; margin-top: 3px; }
        .dg-line { width: 100px; border-top: 1px solid #000; margin: 2px 0; }
        
        .recipient-block { float: right; width: 45%; text-align: center; margin-top: 0.8cm; }
        .recipient-a { font-weight: normal; margin-bottom: 3px; }
        .recipient-name { font-weight: bold; font-size: 10.5pt; margin-bottom: 2px; }
        .recipient-detail { font-size: 9.5pt; line-height: 1.2; }
        
        .clear { clear: both; }
        
        .ref-section { margin-top: 0.4cm; font-weight: bold; font-size: 10.5pt; }
        .objet-section { margin-top: 0.4cm; font-size: 10.5pt; }
        .objet-label { font-weight: bold; text-decoration: underline; }
        
        .salutation { margin-top: 0.4cm; margin-left: 1cm; font-size: 10.5pt; }
        
        .content { margin-top: 0.4cm; text-align: justify; line-height: 1.4; }
        .content p { margin-bottom: 8px; }
        .content ol { padding-left: 0.8cm; list-style-type: none; }
        .content li { margin-bottom: 5px; position: relative; }
        .content li::before { content: attr(data-num) "- "; position: absolute; left: -0.7cm; }
        
        .bold { font-weight: bold; }
        
        .closing-salutation { margin-top: 0.6cm; text-align: left; }
        
        .footer { position: fixed; bottom: 0.4cm; left: 0; right: 0; text-align: center; font-size: 8pt; border-top: 1px solid #000; padding-top: 5px; margin: 0 1cm; }
        .footer-line { font-style: italic; letter-spacing: 0.4px; margin-bottom: 1px; }
        .footer-addr { font-size: 7.5pt; line-height: 1.2; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 40%;">
                <img class="logo-left" src="{$armpLogo}" alt="ARMP">
                <div class="header-text-left">
                    <div>AUTORITE DE REGULATION DES</div>
                    <div>MARCHES PUBLICS</div>
                    <div style="font-weight: bold; margin-top: 3px;">DIRECTION GENERALE</div>
                    <div class="dg-line"></div>
                    <div style="margin-top: 3px;">DIRECTION DES AFFAIRES</div>
                    <div>ADMINISTRATIVES ET</div>
                    <div>FINANCIERES</div>
                </div>
            </td>
            <td style="width: 20%; text-align: center;">
                <img class="logo-center" src="{$minLogo}" alt="MIN">
                <div style="font-size: 6pt; text-transform: uppercase; margin-top: 5px; font-weight: bold;">
                    REPOBLIKAN'I MADAGASIKARA<br>
                    <span style="font-weight: normal; font-size: 5pt;">Fitiavana - Tanindrazana - Fandrosoana</span>
                </div>
            </td>
            <td style="width: 40%;">
                <div class="recipient-block">
                    <div class="recipient-a">A</div>
                    <div class="recipient-name">{$data['nomComplet']}</div>
                    <div class="recipient-detail">{$data['adresse']}</div>
                    <div class="recipient-detail">{$data['contact']}</div>
                    <div class="recipient-detail" style="text-decoration: underline; margin-top: 5px;">101 - ANTANANARIVO</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="clear"></div>

    <div class="ref-section">
        N° {$data['numRef']}
    </div>

    <div class="objet-section">
        <span class="objet-label">Objet</span> : Convention de stage
    </div>

    <div class="salutation">
        {$data['civilite']}
    </div>

    <div class="content">
        <p>Nous vous informons que nous sommes disposés à vous accueillir dans notre Etablissement pour un stage dans les conditions suivantes :</p>
        <div style="margin-left: 0.5cm;">
            <p>1- Le lieu de stage est à Antananarivo au sein de la Direction Générale de l'Autorité de Régulation des Marchés Publics ;</p>
            <p>2- Ce stage est non rémunéré ;</p>
            <p>3- Votre stage dure <span class="bold">{$data['dureeMots']}</span> mois à compter du <span class="bold">{$data['dateDebut']} au {$data['dateFin']}</span> ;</p>
            <p>4- Vous êtes soumis aux horaires et aux dispositions du règlement interne applicables au personnel de notre Etablissement et devez-vous conformer, scrupuleusement, aux instructions qui vous sont données par Le Directeur des Affaires Administratives et Financières ;</p>
            <p>5- En cas de manquement à la discipline durant cette période, ou si des appréciations défavorables quant à votre comportement et à votre travail étaient portées par la Direction au sein desquels s'effectue votre stage, nous serons dans l'obligation de mettre fin à notre engagement ;</p>
            <p>6- Le stagiaire est tenu de contracter une assurance en son nom personnel, couvrant la responsabilité civile et les accidents pouvant survenir durant le stage ;</p>
            <p>7- A la fin de votre stage, il vous est demandé de rédiger un rapport de stage ou mémoire en deux exemplaires à remettre à la Direction Générale.</p>
        </div>

        <p style="margin-top: 10px;">Nous vous remercions de bien vouloir nous donner votre accord exprès sur les conditions énumérées ci-dessus en nous retournant la copie ci-jointe sur laquelle vous aurez porté la mention « lu et approuvé » suivie de votre signature.</p>

        <div style="margin-top: 15px;">
            Nous vous prions d'agréer, <span class="bold">{$data['civilite']}</span>, nos sincères salutations.
        </div>
    </div>

    <div class="footer">
        <div class="footer-line">Disponibilité - Efficacité - Intégrité</div>
        <div class="footer-addr">Immeuble Plan Anosy - 101 Antananarivo</div>
        <div class="footer-addr">infos@armp.mg</div>
        <div class="footer-addr">Tel : 020 22 285 93 ; Fax : 020 22 677 31</div>
    </div>
</body>
</html>
HTML;
    }

    private function assetToDataUri(string $relativePath): string
    {
        $path = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($relativePath, '\\/');
        if (!is_file($path))
            return '';
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    private function assetToDataUriJpegFromPng(string $relativePath, ?int $targetWidth = null): string
    {
        if (!extension_loaded('gd'))
            return '';
        $path = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($relativePath, '\\/');
        if (!is_file($path))
            return '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'png')
            return $this->assetToDataUri($relativePath);
        try {
            $src = @imagecreatefrompng($path);
            if (!$src)
                return '';
            $w = imagesx($src);
            $h = imagesy($src);
            $dstW = $w;
            $dstH = $h;
            if ($targetWidth && $w > $targetWidth) {
                $dstW = $targetWidth;
                $dstH = (int) ($h * ($targetWidth / $w));
            }
            $dst = imagecreatetruecolor($dstW, $dstH);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);
            ob_start();
            imagejpeg($dst, null, 90);
            $data = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            return 'data:image/jpeg;base64,' . base64_encode($data);
        } catch (\Exception $e) {
            return '';
        }
    }
    private function formatDateLongFr($date)
    {
        if (!$date)
            return '';
        $time = strtotime($date);
        $mois = [
            '01' => 'janvier',
            '02' => 'février',
            '03' => 'mars',
            '04' => 'avril',
            '05' => 'mai',
            '06' => 'juin',
            '07' => 'juillet',
            '08' => 'août',
            '09' => 'septembre',
            '10' => 'octobre',
            '11' => 'novembre',
            '12' => 'décembre'
        ];
        $m = date('m', $time);
        return date('d', $time) . ' ' . $mois[$m] . ' ' . date('Y', $time);
    }
}

