<?php
namespace App\Controllers\document;

use CodeIgniter\RESTful\ResourceController;
use App\Models\employe\EmployeModel;
use App\Models\referentiel\TypeDocumentModel;
use Dompdf\Dompdf;

class DocumentController extends ResourceController
{
    protected $format = 'json';
    protected $db;
    protected $typeDocumentModel;

    private array $docEmpCols = [];

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->typeDocumentModel = model('App\Models\referentiel\TypeDocumentModel');
    }

    /**
     * Statistiques globales pour les demandes de documents
     */
    public function stats()
    {
        $cols = $this->resolveDocEmpColumns();
        $statutCol = $cols['statut'] ?? null;

        $total = $this->db->table('doc_emp')->countAllResults();

        $enAttente = 0;
        $traitees = 0;

        if ($statutCol) {
            $enAttente = $this->db->table('doc_emp')
                ->where("($statutCol IS NULL OR $statutCol = '' OR $statutCol = 'en attente' OR $statutCol = 'attente')")
                ->countAllResults();

            $traitees = $this->db->table('doc_emp')
                ->where($statutCol, 'traite')
                ->countAllResults();
        }

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'attente' => $enAttente,
                'traite' => $traitees
            ]
        ]);
    }

    /**
     * Créer une demande de document et générer le PDF
     */
    public function creerDemande()
    {
        try {
            /** @var \CodeIgniter\HTTP\IncomingRequest $request */
            $request = $this->request;
            $json = $request->getJSON(true);

            if (!$json) {
                return $this->fail('Données JSON manquantes', 400);
            }

            $data = $json;

            if (!isset($data['emp_code']) || !isset($data['tdoc_code'])) {
                return $this->fail('emp_code et tdoc_code sont requis', 400);
            }

            $usageRaw = $data['usage'] ?? null;
            if ($usageRaw === null || trim((string) $usageRaw) === '') {
                return $this->failValidationErrors([
                    'usage' => 'Le champ usage est requis (Bancaire ou Administrative).'
                ]);
            }

            $usageNorm = ucfirst(strtolower(trim((string) $usageRaw)));
            if (!in_array($usageNorm, ['Bancaire', 'Administrative'], true)) {
                return $this->failValidationErrors([
                    'usage' => 'Usage invalide. Valeurs autorisées: Bancaire, Administrative.'
                ]);
            }

            $commentaire = $data['commentaire'] ?? null;
            if ($usageNorm === 'Administrative') {
                if ($commentaire === null || trim((string) $commentaire) === '') {
                    return $this->failValidationErrors([
                        'commentaire' => 'Le commentaire est requis lorsque usage = Administrative.'
                    ]);
                }
                $commentaire = trim((string) $commentaire);
            } else {
                $commentaire = null;
            }

            $empCode = $data['emp_code'];
            $tdocCode = $data['tdoc_code'];

            // Récupérer les informations de l'employé avec son affectation active
            $employe = $this->getEmployeWithAffectation($empCode);
            if (!$employe) {
                return $this->fail('Employé non trouvé', 404);
            }

            // Récupérer le type de document
            $typeDocument = $this->typeDocumentModel->find($tdocCode);
            if (!$typeDocument) {
                return $this->fail('Type de document non trouvé', 404);
            }

            // Générer le PDF
            $pdfContent = $this->genererPDF($employe, $typeDocument, $usageNorm, $commentaire);

            // Sauvegarder la demande dans la base (optionnel)
            // TODO: Implémenter la sauvegarde dans la table document_employe

            $affecCode = $employe['affectation_active']['affec_code'] ?? null;
            if (!$affecCode) {
                return $this->fail('Affectation active introuvable pour cet employé', 400);
            }

            $docEmpDate = date('Y-m-d');
            $docEmpStatut = 'en_attente';
            $docEmpMatricule = 'DOC-' . strtoupper(bin2hex(random_bytes(8)));

            $cols = $this->resolveDocEmpColumns();

            $this->db->transBegin();

            $insertCols = ['tdoc_code', 'affec_code'];
            $insertParams = [$tdocCode, $affecCode];

            if (!empty($cols['statut'])) {
                $insertCols[] = $cols['statut'];
                $insertParams[] = $docEmpStatut;
            }
            if (!empty($cols['date'])) {
                $insertCols[] = $cols['date'];
                $insertParams[] = $docEmpDate;
            }
            if (!empty($cols['matricule'])) {
                $insertCols[] = $cols['matricule'];
                $insertParams[] = $docEmpMatricule;
            }

            if (!empty($cols['usage'])) {
                $insertCols[] = $cols['usage'];
                $insertParams[] = $usageNorm;
            }
            if (!empty($cols['commentaire'])) {
                $insertCols[] = $cols['commentaire'];
                $insertParams[] = $commentaire;
            }

            $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
            $insertSql = 'INSERT INTO doc_emp (' . implode(', ', $insertCols) . ') VALUES (' . $placeholders . ')';

            $ok = $this->db->query($insertSql, $insertParams);

            if ($ok === false) {
                $err = $this->db->error();
                $code = $err['code'] ?? null;

                // 23505 = unique_violation (PostgreSQL)
                if ((string) $code === '23505') {
                    $setParts = [];
                    $updateParams = [];

                    if (!empty($cols['statut'])) {
                        $setParts[] = $cols['statut'] . ' = ?';
                        $updateParams[] = $docEmpStatut;
                    }
                    if (!empty($cols['date'])) {
                        $setParts[] = $cols['date'] . ' = ?';
                        $updateParams[] = $docEmpDate;
                    }
                    if (!empty($cols['matricule'])) {
                        $setParts[] = $cols['matricule'] . ' = ?';
                        $updateParams[] = $docEmpMatricule;
                    }

                    if (!empty($cols['usage'])) {
                        $setParts[] = $cols['usage'] . ' = ?';
                        $updateParams[] = $usageNorm;
                    }
                    if (!empty($cols['commentaire'])) {
                        $setParts[] = $cols['commentaire'] . ' = ?';
                        $updateParams[] = $commentaire;
                    }

                    if (empty($setParts)) {
                        $this->db->transRollback();
                        return $this->failServerError('Erreur doc_emp (update): aucune colonne updatable détectée');
                    }

                    $updateSql = 'UPDATE doc_emp SET ' . implode(', ', $setParts) . ' WHERE tdoc_code = ? AND affec_code = ?';
                    $updateParams[] = $tdocCode;
                    $updateParams[] = $affecCode;

                    $ok2 = $this->db->query($updateSql, $updateParams);
                    if ($ok2 === false) {
                        $err2 = $this->db->error();
                        $this->db->transRollback();
                        return $this->failServerError('Erreur doc_emp (update): ' . ($err2['message'] ?? ''));
                    }
                } else {
                    $this->db->transRollback();
                    return $this->failServerError('Erreur doc_emp (insert): ' . ($err['message'] ?? ''));
                }
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                $err3 = $this->db->error();
                return $this->failServerError('Erreur doc_emp (transaction): ' . ($err3['message'] ?? ''));
            }

            $this->db->transCommit();

            // Retourner le PDF en base64 pour le téléchargement
            return $this->respond([
                'status' => 'success',
                'message' => 'Document généré avec succès',
                'pdf_base64' => base64_encode($pdfContent),
                'filename' => $this->genererNomFichier($employe, $typeDocument)
            ], 200);

        } catch (\Exception $e) {
            log_message('error', 'Erreur génération document: ' . $e->getMessage());
            return $this->failServerError('Erreur lors de la génération du document: ' . $e->getMessage());
        }
    }

    public function validerDemande($id)
    {
        try {
            $cols = $this->resolveDocEmpColumns();
            if (empty($cols['code'])) {
                return $this->failServerError('doc_emp_code introuvable (schéma)');
            }
            if (empty($cols['statut'])) {
                return $this->failServerError('colonne statut introuvable (schéma doc_emp)');
            }

            $this->db->transBegin();
            $ok = $this->db->table('doc_emp')
                ->where($cols['code'], (int) $id)
                ->update([$cols['statut'] => 'traite']);

            if ($ok === false) {
                $err = $this->db->error();
                $this->db->transRollback();
                return $this->failServerError('Erreur validation: ' . ($err['message'] ?? ''));
            }
            if ($this->db->transStatus() === false) {
                $err2 = $this->db->error();
                $this->db->transRollback();
                return $this->failServerError('Erreur validation (transaction): ' . ($err2['message'] ?? ''));
            }

            $this->db->transCommit();

            // Envoyer l'email de notification
            $this->envoyerEmailValidation($id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Demande validée'
            ], 200);
        } catch (\Exception $e) {
            log_message('error', 'Erreur validation demande: ' . $e->getMessage());
            return $this->failServerError('Erreur lors de la validation');
        }
    }

    public function telechargerPdfDemande($id)
    {
        try {
            $row = $this->getDemandeRowByCode((int) $id);
            if (!$row) {
                return $this->failNotFound('Demande introuvable');
            }

            $statut = $row['statut'] ?? null;
            if ($statut !== 'traite') {
                return $this->fail('La demande doit être validée avant téléchargement', 400);
            }

            $empCode = $row['emp_code'] ?? null;
            $tdocCode = $row['tdoc_code'] ?? null;
            if (!$empCode || !$tdocCode) {
                return $this->failServerError('Données demande incomplètes (emp_code/tdoc_code)');
            }

            $employe = $this->getEmployeWithAffectation((int) $empCode);
            if (!$employe) {
                return $this->fail('Employé non trouvé', 404);
            }

            $typeDocument = $this->typeDocumentModel->find((int) $tdocCode);
            if (!$typeDocument) {
                return $this->fail('Type de document non trouvé', 404);
            }

            $numero = $row['tdoc_matricule'] ?? null;

            $minLogoDebug = '';
            $armpLogoDebug = '';
            $minLogoFileExists = false;
            $armpLogoFileExists = false;
            if (extension_loaded('gd')) {
                $minLogoDebug = $this->assetToDataUriJpegFromPng('assets/min.png', 300);
                if ($minLogoDebug === '') {
                    $minLogoDebug = $this->assetToDataUri('assets/min.png');
                }
                $armpLogoDebug = $this->assetToDataUriJpegFromPng('assets/logo_armp1.png', 220);
                if ($armpLogoDebug === '') {
                    $armpLogoDebug = $this->assetToDataUri('assets/logo_armp1.png');
                }
                $minLogoFileExists = $this->publicFileExists('assets/min.png');
                $armpLogoFileExists = $this->publicFileExists('assets/logo_armp1.png');
            }

            $pdfContent = $this->genererPDFFinal($employe, $typeDocument, $numero);

            return $this->respond([
                'status' => 'success',
                'logos_enabled' => extension_loaded('gd'),
                'min_logo_src' => (string) $minLogoDebug,
                'armp_logo_src' => (string) $armpLogoDebug,
                'min_logo_exists' => $minLogoFileExists,
                'armp_logo_exists' => $armpLogoFileExists,
                'min_logo_len' => strlen((string) $minLogoDebug),
                'armp_logo_len' => strlen((string) $armpLogoDebug),
                'pdf_base64' => base64_encode($pdfContent),
                'filename' => $this->genererNomFichier($employe, $typeDocument)
            ], 200);
        } catch (\Exception $e) {
            log_message('error', 'Erreur téléchargement PDF demande: ' . $e->getMessage());
            return $this->failServerError('Erreur lors du téléchargement du PDF: ' . $e->getMessage());
        }
    }

    public function listerDemandes()
    {
        try {
            $cols = $this->resolveDocEmpColumns();

            $builder = $this->db->table('doc_emp de');
            $builder
                ->select(
                    (empty($cols['code']) ? 'NULL' : ('de.' . $cols['code'])) . ' as doc_emp_code, '
                    . 'de.tdoc_code, de.affec_code, '
                    . (empty($cols['matricule']) ? 'NULL' : ('de.' . $cols['matricule'])) . ' as tdoc_matricule, '
                    . (empty($cols['statut']) ? 'NULL' : ('de.' . $cols['statut'])) . ' as statut, '
                    . (empty($cols['date']) ? 'NULL' : ('de.' . $cols['date'])) . ' as date_demande, '
                    . (empty($cols['usage']) ? 'NULL' : ('de.' . $cols['usage'])) . ' as usage, '
                    . (empty($cols['commentaire']) ? 'NULL' : ('de.' . $cols['commentaire'])) . ' as commentaire, '
                    . 'e.emp_code, e.emp_matricule, e.emp_nom, e.emp_prenom, td.tdoc_nom'
                )
                ->join('affectation a', 'a.affec_code = de.affec_code', 'left')
                ->join('employe e', 'e.emp_code = a.emp_code', 'left')
                ->join('type_document td', 'td.tdoc_code = de.tdoc_code', 'left')

                ->orderBy(empty($cols['date']) ? 'de.doc_emp_code' : ('de.' . $cols['date']), 'DESC')
                ->orderBy('de.doc_emp_code', 'DESC');

            $rows = $builder->get()->getResultArray();

            return $this->respond([
                'status' => 'success',
                'data' => $rows
            ], 200);
        } catch (\Exception $e) {
            log_message('error', 'Erreur listing demandes: ' . $e->getMessage());
            return $this->failServerError('Erreur lors du chargement des demandes');
        }
    }

    /**
     * Générer le PDF du document
     */
    private function genererPDF($employe, $typeDocument, ?string $usageNorm = null, ?string $commentaire = null)
    {
        // Créer l'instance DomPDF
        $dompdf = new Dompdf();

        // Configurer les options
        $options = $dompdf->getOptions();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf->setOptions($options);

        // Préparer les données pour le template
        $data = [
            'employe' => $employe,
            'typeDocument' => $typeDocument,
            'date' => date('d/m/Y'),
            'dateDemande' => date('d/m/Y'),
            'usage' => $usageNorm,
            'commentaire' => $commentaire,
        ];

        // Générer le HTML
        $html = $this->genererHTML($data);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function genererPDFFinal($employe, $typeDocument, ?string $numero)
    {
        $dompdf = new Dompdf();

        $options = $dompdf->getOptions();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', rtrim((string) FCPATH, '\\/'));
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf->setOptions($options);

        if (!extension_loaded('gd')) {
            throw new \RuntimeException("Extension PHP GD requise pour afficher les logos. Activez 'extension=gd' dans php.ini puis redémarrez le serveur.");
        }

        $minLogo = $this->assetToDataUriJpegFromPng('assets/min.png', 300);
        if ($minLogo === '') {
            $minLogo = $this->assetToDataUri('assets/min.png');
        }
        $armpLogo = $this->assetToDataUriJpegFromPng('assets/logo_armp1.png', 220);
        if ($armpLogo === '') {
            $armpLogo = $this->assetToDataUri('assets/logo_armp1.png');
        }
        if ($minLogo === '' || $armpLogo === '') {
            throw new \RuntimeException('Logos introuvables ou illisibles dans BACKEND/public/assets (min.png, logo_armp1.png)');
        }

        $data = [
            'employe' => $employe,
            'typeDocument' => $typeDocument,
            'date' => date('d/m/Y'),
            'numero' => $numero,
            'minLogo' => $minLogo,
            'armpLogo' => $armpLogo,
        ];

        $html = $this->genererHTMLFinal($data);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function genererHTMLFinal(array $data): string
    {
        $typeDocument = $data['typeDocument'];
        $tdocNom = strtolower((string) ($typeDocument['tdoc_nom'] ?? ''));

        // Priorité à la non interruption (robuste aux variantes: interuption/interruption)
        if ($this->strContains($tdocNom, 'attestation') && ($this->strContains($tdocNom, 'service') || $this->strContains($tdocNom, 'serv'))) {
            return $this->genererHTMLAttestationNonInterruption($data);
        }

        if ($this->strContains($tdocNom, 'certificat') && $this->strContains($tdocNom, 'administratif')) {
            return $this->genererHTMLCertificatAdministratif($data);
        }
        if ($this->strContains($tdocNom, 'certificat') && $this->strContains($tdocNom, 'travail')) {
            return $this->genererHTMLCertificatTravail($data);
        }
        if ($this->strContains($tdocNom, 'attestation') && $this->strContains($tdocNom, 'emploi')) {
            return $this->genererHTMLAttestationEmploi($data);
        }

        if ($this->strContains($tdocNom, 'convention') && $this->strContains($tdocNom, 'stage')) {
            return $this->genererHTMLConventionStage($data);
        }

        return $this->genererHTMLAttestationEmploi($data);
    }

    private function genererHTMLConventionStage(array $data): string
    {
        $stage = $data['stage'] ?? [];
        $minLogo = $data['minLogo'] ?? '';
        $armpLogo = $data['armpLogo'] ?? '';

        $civilite = ($stage['stgr_sexe'] ?? true) ? 'Monsieur' : 'Mademoiselle';
        $nomComplet = strtoupper(($stage['stgr_nom'] ?? '') . ' ' . ($stage['stgr_prenom'] ?? ''));
        $adresse = $stage['stgr_adresse'] ?? 'Non renseignée';
        $contact = $stage['stgr_contact'] ?? 'Non renseigné';

        $dateDebut = date('d/m/Y', strtotime($stage['stg_date_debut'] ?? 'now'));
        $dateFin = date('d/m/Y', strtotime($stage['stg_date_fin'] ?? 'now'));

        // Calcul durée en mois
        $diff = strtotime($stage['stg_date_fin'] ?? 'now') - strtotime($stage['stg_date_debut'] ?? 'now');
        $jours = round($diff / (60 * 60 * 24));
        $mois = round($jours / 30);
        $dureeMots = $this->numberToWords((int) $mois);

        $year = date('y', strtotime($stage['stg_date_debut'] ?? 'now'));
        $numRef = "_______/ARMP/DG/DAAF/SRH- " . $year;

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1.5cm 2cm 2cm 2cm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #000; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header td { vertical-align: top; }
        .logo-left { width: 75px; height: auto; }
        .logo-center { width: 100px; height: auto; }
        .header-text-left { font-size: 8pt; text-transform: uppercase; line-height: 1.2; margin-top: 5px; }
        .dg-line { width: 120px; border-top: 1px solid #000; margin: 3px 0; }
        
        .recipient-block { float: right; width: 45%; text-align: center; margin-top: 1cm; margin-right: -0.5cm; }
        .recipient-a { font-weight: normal; margin-bottom: 5px; }
        .recipient-name { font-weight: bold; font-size: 11pt; margin-bottom: 3px; }
        .recipient-detail { font-size: 10pt; line-height: 1.3; }
        
        .clear { clear: both; }
        
        .ref-section { margin-top: 0.5cm; font-weight: bold; font-size: 11pt; }
        .objet-section { margin-top: 0.8cm; font-size: 11pt; }
        .objet-label { font-weight: bold; text-decoration: underline; }
        
        .salutation { margin-top: 0.8cm; margin-left: 2cm; font-size: 11pt; }
        
        .content { margin-top: 0.8cm; text-align: justify; line-height: 1.6; }
        .content p { margin-bottom: 12px; }
        .content ol { padding-left: 1.2cm; list-style-type: none; }
        .content li { margin-bottom: 8px; position: relative; }
        .content li::before { content: attr(data-num) "- "; position: absolute; left: -0.8cm; }
        
        .bold { font-weight: bold; }
        
        .closing-salutation { margin-top: 1cm; text-align: left; }
        
        .footer { position: fixed; bottom: 0.5cm; left: 0; right: 0; text-align: center; font-size: 9pt; border-top: 1px solid #000; padding-top: 8px; margin: 0 2cm; }
        .footer-line { font-style: italic; letter-spacing: 0.5px; margin-bottom: 2px; }
        .footer-addr { font-size: 8.5pt; line-height: 1.3; }
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
            </td>
            <td style="width: 40%;">
                <div class="recipient-block">
                    <div class="recipient-a">A</div>
                    <div class="recipient-name">{$nomComplet}</div>
                    <div class="recipient-detail">{$adresse}</div>
                    <div class="recipient-detail">{$contact}</div>
                    <div class="recipient-detail" style="text-decoration: underline; margin-top: 5px;">101 - ANTANANARIVO</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="clear"></div>

    <div class="ref-section">
        N° {$numRef}
    </div>

    <div class="objet-section">
        <span class="objet-label">Objet</span> : Convention de stage
    </div>

    <div class="salutation">
        {$civilite}
    </div>

    <div class="content">
        <p>Nous vous informons que nous sommes disposés à vous accueillir dans notre Etablissement pour un stage dans les conditions suivantes :</p>
        <div style="margin-left: 0.5cm;">
            <p>1- Le lieu de stage est à Antananarivo au sein de la Direction Générale de l'Autorité de Régulation des Marchés Publics ;</p>
            <p>2- Ce stage est non rémunéré ;</p>
            <p>3- Votre stage dure <span class="bold">{$dureeMots}</span> mois à compter du <span class="bold">{$dateDebut} au {$dateFin}</span> ;</p>
            <p>4- Vous êtes soumis aux horaires et aux dispositions du règlement interne applicables au personnel de notre Etablissement et devez-vous conformer, scrupuleusement, aux instructions qui vous sont données par Le Directeur des Affaires Administratives et Financières ;</p>
            <p>5- En cas de manquement à la discipline durant cette période, ou si des appréciations défavorables quant à votre comportement et à votre travail étaient portées par la Direction au sein desquels s'effectue votre stage, nous serons dans l'obligation de mettre fin à notre engagement ;</p>
            <p>6- Le stagiaire est tenu de contracter une assurance en son nom personnel, couvrant la responsabilité civile et les accidents pouvant survenir durant le stage ;</p>
            <p>7- A la fin de votre stage, il vous est demandé de rédiger un rapport de stage ou mémoire en deux exemplaires à remettre à la Direction Générale. </p>
        </div>

        <p style="margin-top: 15px;">Nous vous remercions de bien vouloir nous donner votre accord exprès sur les conditions énumérées ci-dessus en nous retournant la copie ci-jointe sur laquelle vous aurez porté la mention « lu et approuvé » suivie de votre signature.</p>

        <p class="closing-salutation">
            Nous vous prions d'agréer, <span class="bold">{$civilite}</span>, nos sincères salutations.
        </p>
    </div>
</body>
</html>
HTML;
    }

    private function numberToWords(int $number): string
    {
        $words = [
            0 => 'zéro',
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

    private function genererHTMLCertificatAdministratif(array $data): string
    {
        $doc = $this->buildDocVars($data);

        $title = 'CERTIFICAT ADMINISTRATIF';
        $usage = 'Pour usage administratif';
        $body = "Le Directeur Général de l'Autorité de Régulation des Marchés Publics, certifie que {$doc['civilite']} {$doc['nom']}, IM {$doc['im']}, est affecté au {$doc['direction']} en qualité de {$doc['fonction']}, depuis le {$doc['date_debut']} jusqu'à ce jour.";
        $closing = "En foi de quoi, le présent certificat lui est délivré pour servir et valoir ce que de droit.";

        return $this->renderDocumentHtml($data, $title, $doc['num_line'], $usage, $body, $closing, true);
    }

    private function genererHTMLCertificatTravail(array $data): string
    {
        $doc = $this->buildDocVars($data);

        $title = 'CERTIFICAT DE TRAVAIL';
        $usage = 'Pour complément de dossier';
        $body = "Le Directeur Général de l'Autorité de Régulation des Marchés Publics, atteste que {$doc['civilite']} {$doc['nom']}, IM {$doc['im']}, est affecté à la {$doc['direction']} depuis le {$doc['date_debut']} jusqu'à ce jour.";
        $closing = "En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.";

        return $this->renderDocumentHtml($data, $title, $doc['num_line'], $usage, $body, $closing, true);
    }

    private function genererHTMLAttestationEmploi(array $data): string
    {
        $doc = $this->buildDocVars($data);

        $title = "ATTESTATION D'EMPLOI";
        $usage = 'Pour Usage bancaire';
        $body = "Le Directeur Général de l'Autorité de Régulation des Marchés Publics, atteste que {$doc['civilite']} {$doc['nom']}, IM {$doc['im']}, est affecté(e) à la {$doc['direction']}, en qualité de {$doc['fonction']} depuis le {$doc['date_debut']} jusqu'à ce jour.";
        $closing = "En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.";

        return $this->renderDocumentHtml($data, $title, $doc['num_line'], $usage, $body, $closing, false);
    }

    private function genererHTMLAttestationNonInterruption(array $data): string
    {
        $doc = $this->buildDocVars($data);

        $title = "ATTESTATION DE NON INTERRUPTION\nDE SERVICE";
        $usage = '';

        $dateFin = $doc['date_fin'] ?: 'ce jour';
        $body = "Le Directeur Général de l'Autorité de Régulation des Marchés Publics,\natteste par la présente que :\n\n{$doc['civilite']} {$doc['nom']}, IM {$doc['im']} -\n{$doc['fonction']}, au sein de la {$doc['direction']}, a travaillé sans interruption, depuis le {$doc['date_debut']}\njusqu'au {$dateFin}.";
        $closing = "En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.";

        return $this->renderDocumentHtml($data, $title, $doc['num_line'], $usage, $body, $closing, true);
    }

    private function buildDocVars(array $data): array
    {
        $employe = $data['employe'];
        $numero = $data['numero'] ?? '';

        $nom = trim(($employe['emp_nom'] ?? '') . ' ' . ($employe['emp_prenom'] ?? ''));
        $im = $employe['emp_im_armp'] ?? '';
        $matricule = $employe['emp_matricule'] ?? '';
        $civilite = null;
        if (array_key_exists('emp_sexe', $employe)) {
            $civilite = ($employe['emp_sexe'] ? 'Monsieur' : 'Madame');
        }
        $civilite = $civilite ?: 'Monsieur/Madame';

        $aff = $employe['affectation_active'] ?? [];
        $fonction = $aff['pst_fonction'] ?? '';
        $direction = $this->normalizeDirection($aff['directions'] ?? null);

        $dateDebut = $this->formatDateLongFr($aff['affec_date_debut'] ?? ($employe['date_entree'] ?? null));
        $dateFin = $this->formatDateLongFr($aff['affec_date_fin'] ?? null);

        $year2 = date('y');
        $numeroFmt = trim((string) $numero);
        $numLine = 'N° : ____ /ARMP/DG-' . $year2;
        if ($numeroFmt !== '') {
            $numLine = 'N° : ' . $numeroFmt . ' /ARMP/DG-' . $year2;
        }

        return [
            'nom' => $nom,
            'im' => $im,
            'matricule' => $matricule,
            'civilite' => $civilite,
            'fonction' => $fonction,
            'direction' => $direction,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'num_line' => $numLine,
        ];
    }

    private function normalizeDirection($directions): string
    {
        if (empty($directions))
            return '';

        if (is_array($directions)) {
            $out = [];
            foreach ($directions as $v) {
                if (!is_string($v)) {
                    continue;
                }
                $s = trim($v);
                if ($s === '') {
                    continue;
                }

                if (strlen($s) >= 2 && $s[0] === '{' && $s[strlen($s) - 1] === '}') {
                    $inner = substr($s, 1, -1);
                    $items = str_getcsv($inner, ',', '"');
                    foreach ($items as $it) {
                        $it = trim((string) $it);
                        if ($it !== '') {
                            $out[] = $it;
                        }
                    }
                    continue;
                }

                $out[] = trim($s, "\"'");
            }

            $out = array_values(array_filter(array_map('trim', $out), fn($x) => $x !== ''));
            return implode(', ', $out);
        }

        if (is_string($directions)) {
            $s = trim($directions);
            if ($s === '')
                return '';

            // PostgreSQL array text format: {"A","B"}
            if (strlen($s) >= 2 && $s[0] === '{' && $s[strlen($s) - 1] === '}') {
                $inner = substr($s, 1, -1);
                $items = str_getcsv($inner, ',', '"');
                $items = array_values(array_filter(array_map('trim', $items), fn($v) => $v !== ''));
                return implode(', ', $items);
            }

            // Otherwise treat as plain text
            $s = trim($s, "\"'");
            return $s;
        }

        return '';
    }

    private function renderDocumentHtml(array $data, string $title, string $numLine, string $usage, string $body, string $closing, bool $withTelFax): string
    {
        $minLogo = $data['minLogo'] ?? '';
        $armpLogo = $data['armpLogo'] ?? '';

        $titleHtml = nl2br(htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $numHtml = htmlspecialchars($numLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $usageHtml = $usage ? ('<div class="usage">' . htmlspecialchars($usage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>') : '';
        $bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $closingHtml = htmlspecialchars($closing, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $leftLogoHtml = $armpLogo ? '<img class="logo-left" src="' . $armpLogo . '" alt="armp" />' : '';
        $centerLogoHtml = $minLogo ? '<img class="logo-center" src="' . $minLogo . '" alt="min" />' : '';

        $telFaxHtml = '';
        if ($withTelFax) {
            $telFaxHtml = '<div class="footaddr">Tel : 020 22 285 93 ; Fax : 020 22 677 35</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 2.2cm 2.6cm 2.4cm 2.6cm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11pt; color: #000; }
        .header { width: 100%; border-collapse: collapse; margin-top: -0.6cm; }
        .header td { vertical-align: top; }
        .logo-left { width: 70px; height: auto; }
        .logo-center { width: 95px; height: auto; }
        .header-left { width: 33%; }
        .header-center { width: 34%; text-align: center; }
        .header-right { width: 33%; }
        .header-text { margin-top: 6px; font-size: 8.5pt; line-height: 1.2; text-transform: uppercase; }
        .dg { margin-top: 4px; font-weight: bold; }
        .dg-line { width: 130px; border-top: 1px solid #000; margin-top: 2px; }
        .title { text-align: center; font-weight: bold; text-decoration: underline; margin-top: 2.6cm; letter-spacing: 0.5px; }
        .num { margin-top: 1.2cm; font-size: 11pt; }
        .usage { margin-top: 0.8cm; font-size: 11pt; }
        .content { margin-top: 0.9cm; text-align: justify; line-height: 1.7; }
        .closing { margin-top: 0.9cm; text-align: justify; line-height: 1.7; }
        .date-bottom { margin-top: 2.2cm; text-align: right; }
        .footer { position: fixed; left: 2.6cm; right: 2.6cm; bottom: 1.6cm; text-align: center; font-size: 9.5pt; color: #111; }
        .footline { font-style: italic; letter-spacing: 0.6px; }
        .footaddr { margin-top: 2px; font-size: 8.5pt; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="header-left">
                {$leftLogoHtml}
                <div class="header-text">
                    <div>AUTORITE DE REGULATION</div>
                    <div>DES MARCHES PUBLICS</div>
                    <div class="dg">DIRECTION GENERALE</div>
                    <div class="dg-line"></div>
                </div>
            </td>
            <td class="header-center">{$centerLogoHtml}</td>
            <td class="header-right"></td>
        </tr>
    </table>

    <div class="title">{$titleHtml}</div>
    <div class="num">{$numHtml}</div>
    {$usageHtml}
    <div class="content">{$bodyHtml}</div>
    <div class="closing">{$closingHtml}</div>
    <div class="date-bottom">Antananarivo, le {$data['date']}</div>
    <div class="footer">
        <div class="footline">Disponibilité - Efficacité - Intégrité</div>
        <div class="footaddr">Immeuble Plan Anosy - 101 Antananarivo</div>
        <div class="footaddr">infos@armp.mg</div>
        {$telFaxHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Générer le HTML du document
     */
    private function genererHTML($data)
    {
        $employe = $data['employe'];
        $typeDocument = $data['typeDocument'];
        $date = $data['date'];

        // Récupérer les informations de l'employé
        $nom = $employe['emp_nom'] ?? '';
        $prenom = $employe['emp_prenom'] ?? '';
        $nomComplet = trim($nom . ' ' . $prenom);
        $im = $employe['emp_im_armp'] ?? '';
        $matricule = $employe['emp_matricule'] ?? '';

        // Informations de l'affectation active
        $affectation = $employe['affectation_active'] ?? null;
        $fonction = $affectation['pst_fonction'] ?? '';
        $service = $affectation['srvc_nom'] ?? '';
        $direction = $this->normalizeDirection($affectation['directions'] ?? null);

        // Corps et grade (à adapter selon votre structure)
        $corps = '';
        $grade = '';

        // Texte selon le type de document
        $objet = $this->getObjetDocument($typeDocument['tdoc_nom']);
        $usageNorm = $data['usage'] ?? null;
        $commentaire = $data['commentaire'] ?? null;
        $corpsTexte = $this->getCorpsTexte($typeDocument['tdoc_nom'], $usageNorm, $commentaire);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 2.5cm;
            size: A4 portrait;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
        }
        .top-block {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .top-left {
            width: 60%;
            vertical-align: top;
        }
        .top-right {
            width: 40%;
            vertical-align: top;
            text-align: right;
        }
        .sender-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .sender-info td {
            padding: 3px 0;
            font-size: 11pt;
            vertical-align: top;
        }
        .sender-info td:first-child {
            width: 40%;
            font-weight: bold;
        }
        .header {
            font-size: 11pt;
            margin-bottom: 20px;
        }
        .recipient {
            width: 50%;
            margin-left: 50%;
            text-align: left;
            margin-bottom: 40px;
        }
        .recipient p {
            margin: 4px 0;
            line-height: 1.4;
        }
        .recipient .addr {
            text-decoration: underline;
        }
        .objet {
            margin: 20px 0;
            font-size: 11pt;
        }
        .objet .label {
            font-weight: bold;
            text-decoration: underline;
        }
        .body-text {
            margin: 30px 0;
            text-align: justify;
            line-height: 1.6;
            text-indent: 1.5cm;
        }
        .closing {
            margin-top: 30px;
            text-align: justify;
            line-height: 1.6;
        }
        .signature {
            text-align: right;
            margin-top: 50px;
            padding-right: 50px;
        }
        .footer {
            position: fixed;
            left: 0;
            bottom: 60px;
            font-size: 10pt;
            font-weight: bold;
        }
        .footer p {
            margin: 0;
        }
    </style>
</head>
<body>
    <table class="top-block">
        <tr>
            <td class="top-left">
                <div class="sender-info">
                    <table>
                        <tr>
                            <td>Nom et Prénoms :</td>
                            <td>{$nomComplet}</td>
                        </tr>
                        <tr>
                            <td>Fonction :</td>
                            <td>{$fonction}</td>
                        </tr>
                        <tr>
                            <td>IM :</td>
                            <td>{$im}</td>
                        </tr>
                        <tr>
                            <td>Direction :</td>
                            <td>{$direction}</td>
                        </tr>
                        <tr>
                            <td>Corps :</td>
                            <td>{$corps}</td>
                        </tr>
                        <tr>
                            <td>Grade :</td>
                            <td>{$grade}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td class="top-right">
                <div class="header">Antananarivo, le {$date}</div>
            </td>
        </tr>
    </table>

    <div class="recipient">
        <p><strong>A</strong></p>
        <p><strong>Madame Le Directeur Général</strong></p>
        <p><strong>Autorité de Régulation des Marchés</strong></p>
        <p><strong>Publics</strong></p>
        <p class="addr"><strong>101 - ANTANANARIVO</strong></p>
    </div>

    <div class="objet">
        <span class="label">Objet :</span> {$objet}
    </div>

    <div class="body-text">
        <p>Madame Le Directeur Général,</p>
        <p>{$corpsTexte}</p>
    </div>

    <div class="closing">
        <p>Veuillez agréer, Madame Le Directeur Général, l'assurance de ma considération distinguée.</p>
    </div>

    <div class="signature">
        <p>Le Demandeur</p>
    </div>

    <div class="footer">
        <p><u>Avis de la Direction des Affaires</u></p>
        <p><u>Administratives et Financières</u></p>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Obtenir l'objet du document selon le type
     */
    private function getObjetDocument($tdocNom)
    {
        $objet = 'Demande d\'un ';

        if (stripos($tdocNom, 'attestation') !== false) {
            if (stripos($tdocNom, 'non interruption') !== false) {
                return 'Demande d\'une attestation de non interruption de service';
            } elseif (stripos($tdocNom, 'emploi') !== false) {
                return 'Demande d\'une attestation d\'emploi';
            }
        } elseif (stripos($tdocNom, 'certificat') !== false) {
            if (stripos($tdocNom, 'travail') !== false) {
                return 'Demande d\'un certificat de travail';
            } elseif (stripos($tdocNom, 'administratif') !== false) {
                return 'Demande d\'un certificat administratif';
            }
        }

        return $objet . strtolower($tdocNom);
    }

    /**
     * Obtenir le corps du texte selon le type de document
     */
    private function getCorpsTexte($tdocNom, ?string $usageNorm = null, ?string $commentaire = null)
    {
        return $this->getCorpsTexteAvecUsage($tdocNom, $usageNorm, $commentaire);
    }

    private function getCorpsTexteAvecUsage($tdocNom, ?string $usageNorm, ?string $commentaire): string
    {
        $usageText = 'administratif ou bancaire';
        if ($usageNorm === 'Bancaire') {
            $usageText = 'bancaire';
        } elseif ($usageNorm === 'Administrative') {
            $usageText = 'administratif';
        }

        $extra = '';
        if ($usageNorm === 'Administrative' && $commentaire !== null && trim((string) $commentaire) !== '') {
            $safeComment = htmlspecialchars(trim((string) $commentaire), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $extra = ' (' . $safeComment . ')';
        }

        if (stripos($tdocNom, 'attestation') !== false) {
            if (stripos($tdocNom, 'non interruption') !== false) {
                return "J'ai l'honneur de solliciter votre bienveillance de bien vouloir me délivrer une attestation de non interruption de service pour usage {$usageText}{$extra}.";
            }
            if (stripos($tdocNom, 'emploi') !== false) {
                return "J'ai l'honneur de solliciter votre bienveillance de bien vouloir me délivrer une attestation d'emploi pour usage {$usageText}{$extra}.";
            }
        }

        if (stripos($tdocNom, 'certificat') !== false) {
            if (stripos($tdocNom, 'travail') !== false) {
                return "J'ai l'honneur de solliciter votre bienveillance de bien vouloir me délivrer un certificat de travail pour usage {$usageText}{$extra}.";
            }
            if (stripos($tdocNom, 'administratif') !== false) {
                return "J'ai l'honneur de solliciter votre bienveillance de bien vouloir me délivrer un certificat administratif pour usage {$usageText}{$extra}.";
            }
        }

        return "J'ai l'honneur de solliciter votre bienveillance de bien vouloir me délivrer le document demandé pour usage {$usageText}{$extra}.";
    }

    /**
     * Générer le nom du fichier PDF
     */
    private function genererNomFichier($employe, $typeDocument)
    {
        $nom = str_replace(' ', '_', $employe['emp_nom'] ?? '');
        $prenom = str_replace(' ', '_', $employe['emp_prenom'] ?? '');
        $type = str_replace(' ', '_', strtolower($typeDocument['tdoc_nom'] ?? 'document'));
        $date = date('Y-m-d');

        return "Demande_{$type}_{$nom}_{$prenom}_{$date}.pdf";
    }

    /**
     * Récupérer un employé avec son affectation active
     */
    private function getEmployeWithAffectation($empCode)
    {
        $builder = $this->db->table('employe e');
        $builder->select('e.*, 
            a.affec_code, a.affec_date_debut, a.affec_date_fin, a.affec_commentaire,
            tc.tcontrat_nom,
            p.pst_code, p.pst_fonction, p.pst_mission,
            s.srvc_nom,
            ma.m_aff_code, ma.m_aff_motif,
            COALESCE(d_direct.dir_nom, d_service.dir_nom) as dir_nom')
            ->join('affectation a', 'a.emp_code = e.emp_code', 'left')
            ->join('type_contrat tc', 'tc.tcontrat_code = a.tcontrat_code', 'left')
            ->join('poste p', 'p.pst_code = a.pst_code', 'left')
            ->join('service s', 's.srvc_code = p.srvc_code', 'left')
            ->join('motif_affectation ma', 'ma.m_aff_code = a.m_aff_code', 'left')
            ->join('direction d_direct', 'd_direct.dir_code = p.dir_code', 'left')
            ->join('direction d_service', 'd_service.dir_code = s.dir_code', 'left')
            ->where('e.emp_code', $empCode)
            ->groupStart()
            ->where('a.affec_date_fin >=', date('Y-m-d'))
            ->orWhere('a.affec_date_fin', null)
            ->groupEnd()
            ->limit(1);

        $queryResult = $builder->get();
        $result = null;
        if ($queryResult && $queryResult !== false) {
            $result = $queryResult->getRowArray();
        }

        if ($result) {
            // Formater les directions (compatibilité)
            $directions = [];
            if (!empty($result['dir_nom'])) {
                $directions[] = $result['dir_nom'];
            }
            $result['directions'] = $directions;

            // Structurer l'affectation active
            $result['affectation_active'] = [
                'affec_code' => $result['affec_code'],
                'affec_date_debut' => $result['affec_date_debut'],
                'affec_date_fin' => $result['affec_date_fin'],
                'affec_type_contrat' => $result['tcontrat_nom'],
                'affec_commentaire' => $result['affec_commentaire'],
                'pst_code' => $result['pst_code'],
                'pst_fonction' => $result['pst_fonction'],
                'pst_mission' => $result['pst_mission'],
                'srvc_nom' => $result['srvc_nom'],
                'm_aff_code' => $result['m_aff_code'],
                'm_aff_motif' => $result['m_aff_motif'],
                'directions' => $directions
            ];
        }

        return $result;
    }

    private function getDemandeRowByCode(int $id): ?array
    {
        $cols = $this->resolveDocEmpColumns();
        if (empty($cols['code'])) {
            return null;
        }

        $builder = $this->db->table('doc_emp de');
        $builder
            ->select(
                'de.tdoc_code, de.affec_code, '
                . (empty($cols['matricule']) ? 'NULL' : ('de.' . $cols['matricule'])) . ' as tdoc_matricule, '
                . (empty($cols['statut']) ? 'NULL' : ('de.' . $cols['statut'])) . ' as statut, '
                . (empty($cols['date']) ? 'NULL' : ('de.' . $cols['date'])) . ' as date_demande, '
                . (empty($cols['usage']) ? 'NULL' : ('de.' . $cols['usage'])) . ' as usage, '
                . (empty($cols['commentaire']) ? 'NULL' : ('de.' . $cols['commentaire'])) . ' as commentaire, '
                . 'e.emp_code, e.emp_mail, td.tdoc_nom'
            )
            ->join('affectation a', 'a.affec_code = de.affec_code', 'left')
            ->join('employe e', 'e.emp_code = a.emp_code', 'left')
            ->join('type_document td', 'td.tdoc_code = de.tdoc_code', 'left')
            ->where('de.' . $cols['code'], $id)
            ->limit(1);

        $queryResult = $builder->get();
        $row = null;
        if ($queryResult && $queryResult !== false) {
            $row = $queryResult->getRowArray();
        }
        return $row ?: null;
    }

    private function assetToDataUri(string $relativePath): string
    {
        $path = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($relativePath, '\\/');
        if (!is_file($path)) {
            return '';
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'image/png';
        if ($ext === 'jpg' || $ext === 'jpeg')
            $mime = 'image/jpeg';
        if ($ext === 'gif')
            $mime = 'image/gif';
        $data = file_get_contents($path);
        if ($data === false) {
            return '';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private function assetToFileUri(string $relativePath): string
    {
        $path = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($relativePath, '\\/');
        $real = realpath($path);
        if (!$real || !is_file($real)) {
            return '';
        }
        $real = str_replace('\\', '/', $real);
        if (preg_match('/^[a-zA-Z]:\//', $real)) {
            return 'file:///' . $real;
        }
        return 'file://' . $real;
    }

    private function prepareLogoForDompdf(string $relativePath, string $name, int $targetWidth): string
    {
        if (!extension_loaded('gd')) {
            return '';
        }

        $srcPath = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($relativePath, '\\/');
        $real = realpath($srcPath);
        if (!$real || !is_file($real)) {
            return '';
        }

        $mtime = (int) @filemtime($real);
        $outDir = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . '__dompdf';
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }
        if (!is_dir($outDir)) {
            return '';
        }

        $outFile = $name . '_' . $targetWidth . '_' . $mtime . '.jpg';
        $outPath = $outDir . DIRECTORY_SEPARATOR . $outFile;
        if (!is_file($outPath)) {
            $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));

            $src = null;
            if ($ext === 'png') {
                $src = @imagecreatefrompng($real);
            } elseif ($ext === 'jpg' || $ext === 'jpeg') {
                $src = @imagecreatefromjpeg($real);
            } elseif ($ext === 'gif') {
                $src = @imagecreatefromgif($real);
            }

            if (!$src) {
                return '';
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $dstW = $w;
            $dstH = $h;
            if ($targetWidth > 0 && $w > $targetWidth) {
                $ratio = $h / max(1, $w);
                $dstW = (int) $targetWidth;
                $dstH = (int) round($dstW * $ratio);
                if ($dstH < 1)
                    $dstH = 1;
            }

            $dst = imagecreatetruecolor($dstW, $dstH);
            if (!$dst) {
                imagedestroy($src);
                return '';
            }
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
            if ($dstW !== $w || $dstH !== $h) {
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);
            } else {
                imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
            }

            @imagejpeg($dst, $outPath, 90);
            imagedestroy($dst);
            imagedestroy($src);

            if (!is_file($outPath)) {
                return '';
            }
        }

        return 'assets/__dompdf/' . $outFile;
    }

    private function publicFileExists(string $relativePath): bool
    {
        $p = trim($relativePath);
        if ($p === '')
            return false;
        if (preg_match('/^https?:\/\//i', $p) || preg_match('/^file:\/\//i', $p))
            return false;
        $path = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($p, '\\/');
        $real = realpath($path);
        return $real && is_file($real);
    }

    private function toPublicUrl(string $relativeOrUrl): string
    {
        $s = trim($relativeOrUrl);
        if ($s === '')
            return '';

        if (preg_match('/^https?:\/\//i', $s)) {
            return $s;
        }

        // base_url() (CI4) si disponible
        if (function_exists('base_url')) {
            return (string) base_url($s);
        }

        // Fallback si base_url non configuré
        try {
            $https = $this->request->getServer('HTTPS');
            $isHttps = !empty($https) && strtolower((string) $https) !== 'off';
            $scheme = $this->request->getServer('REQUEST_SCHEME') ?: ($isHttps ? 'https' : 'http');
            $host = $this->request->getServer('HTTP_HOST') ?: $this->request->getServer('SERVER_NAME');
            if (!$host)
                return $s;
            return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($s, '/');
        } catch (\Throwable $e) {
            return $s;
        }
    }

    private function assetToDataUriJpegFromPng(string $relativePath, ?int $targetWidth = null): string
    {
        if (!extension_loaded('gd')) {
            return '';
        }

        $path = rtrim((string) FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($relativePath, '\\/');
        if (!is_file($path)) {
            return '';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            return $this->assetToDataUri($relativePath);
        }

        try {
            $src = @imagecreatefrompng($path);
            if (!$src) {
                return '';
            }

            $w = imagesx($src);
            $h = imagesy($src);

            $dstW = $w;
            $dstH = $h;
            if ($targetWidth !== null && $targetWidth > 0 && $w > $targetWidth) {
                $ratio = $h / max(1, $w);
                $dstW = (int) $targetWidth;
                $dstH = (int) round($dstW * $ratio);
                if ($dstH < 1)
                    $dstH = 1;
            }

            $dst = imagecreatetruecolor($dstW, $dstH);
            if (!$dst) {
                imagedestroy($src);
                return '';
            }

            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
            if ($dstW !== $w || $dstH !== $h) {
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);
            } else {
                imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
            }

            ob_start();
            imagejpeg($dst, null, 92);
            $jpegData = ob_get_clean();

            imagedestroy($dst);
            imagedestroy($src);

            if ($jpegData === false || $jpegData === '') {
                return '';
            }

            return 'data:image/jpeg;base64,' . base64_encode($jpegData);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function formatDateFr(?string $date): ?string
    {
        if (!$date)
            return null;
        $ts = strtotime($date);
        if ($ts === false)
            return null;
        return date('d/m/Y', $ts);
    }

    private function formatDateLongFr(?string $date): ?string
    {
        if (!$date)
            return null;
        $ts = strtotime($date);
        if ($ts === false)
            return null;

        $day = (int) date('d', $ts);
        $month = (int) date('m', $ts);
        $year = (int) date('Y', $ts);

        $months = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];

        $m = $months[$month] ?? date('m', $ts);
        return sprintf('%02d %s %04d', $day, $m, $year);
    }

    private function strContains(?string $haystack, string $needle): bool
    {
        if ($haystack === null)
            return false;
        return strpos($haystack, $needle) !== false;
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        try {
            $col = $this->db->query(
                'SELECT 1 FROM information_schema.columns WHERE LOWER(table_name) = LOWER(?) AND LOWER(column_name) = LOWER(?) LIMIT 1',
                [$tableName, $columnName]
            )->getRowArray();
            return !empty($col);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function resolveFirstExistingColumn(string $tableName, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if ($this->hasColumn($tableName, $col)) {
                return $col;
            }
        }
        return null;
    }

    private function resolveDocEmpColumns(): array
    {
        if (!empty($this->docEmpCols)) {
            return $this->docEmpCols;
        }

        $this->docEmpCols = [
            'statut' => $this->resolveFirstExistingColumn('doc_emp', ['doc_emp_statut', 'statut', 'doc_statut']),
            'date' => $this->resolveFirstExistingColumn('doc_emp', ['doc_emp_date', 'date_demande', 'doc_date', 'date']),
            'matricule' => $this->resolveFirstExistingColumn('doc_emp', ['tdoc_matricule', 'doc_emp_matricule', 'matricule']),
            'code' => $this->resolveFirstExistingColumn('doc_emp', ['doc_emp_code', 'id']),
            'usage' => $this->resolveFirstExistingColumn('doc_emp', ['usage', 'doc_emp_usage']),
            'commentaire' => $this->resolveFirstExistingColumn('doc_emp', ['commentaire', 'doc_emp_commentaire']),
        ];

        return $this->docEmpCols;
    }
    private function envoyerEmailValidation($id)
    {
        $demande = $this->getDemandeRowByCode((int) $id);
        if (!$demande)
            return;

        $emailDestinataire = $demande['emp_mail'] ?? null;
        if (empty($emailDestinataire)) {
            log_message('error', 'Validation demande: email employé introuvable.');
            return;
        }

        $email = \Config\Services::email();
        $email->setTo($emailDestinataire);
        // Expéditeur demandé
        $email->setFrom('arorandria1@gmail.com', 'ARMP - Gestion Documents');
        $email->setSubject('Validation de votre demande de document');

        $nomDoc = $demande['tdoc_nom'] ?? 'Document administratif';

        $message = "Bonjour,\n\n" .
            "Votre demande pour le document \"{$nomDoc}\" a été validée.\n" .
            "Vous pouvez dès à présent le récupérer auprès de la Direction des Ressources Humaines.\n\n" .
            "Cordialement,\n" .
            "L'équipe RH - ARMP";

        $email->setMessage($message);

        // Envoyer l'email et logger si erreur (sans bloquer le processus)
        if (!$email->send()) {
            log_message('error', 'Erreur envoi email validation doc ' . $id . ': ' . $email->printDebugger(['headers']));
        }
    }
}

