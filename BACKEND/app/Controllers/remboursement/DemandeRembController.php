<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\DemandeRembModel;
use App\Models\remboursement\EtatRembModel;
use App\Models\remboursement\SignatureDemandeModel;
use App\Models\remboursement\ConjointeModel;
use App\Models\remboursement\EnfantModel;
use App\Models\remboursement\PieceModel;

class DemandeRembController extends ResourceController
{
    use ResponseTrait;

    /**
     * Générer le numéro de demande: N/ARMP/DG/DAAF/SRH-YY
     */
    private function generateNumDemande()
    {
        $db = \Config\Database::connect();
        $year = date('y'); // 25 pour 2025
        
        // Récupérer le dernier numéro de cette année
        $lastDemande = $db->table('demande_remb')
            ->like('num_demande', "/ARMP/DG/DAAF/SRH-$year", 'after')
            ->orderBy('rem_code', 'DESC')
            ->get()->getRow();
        
        $nextNum = 1;
        if ($lastDemande && $lastDemande->num_demande) {
            // Extraire le numéro du format "123/ARMP/DG/DAAF/SRH-25"
            $parts = explode('/', $lastDemande->num_demande);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $nextNum = intval($parts[0]) + 1;
            }
        }
        
        return "$nextNum/ARMP/DG/DAAF/SRH-$year";
    }

    /**
     * Génère un numéro pour l'état mensuel (agent)
     * Format : NNN/ARMP/DG/DAAF/SRH/FM-YY (selon l'image fournie)
     */
    private function generateEtatNum($year)
    {
        $db = \Config\Database::connect();
        $yy = substr((string)$year, -2);
        // On cherche dans num_engagement ou on pourrait créer une table dédiée
        $last = $db->table('demande_remb')
            ->like('num_engagement', "/ARMP/DG/DAAF/SRH/FM-$yy", 'after')
            ->orderBy('rem_code', 'DESC')
            ->get()
            ->getRow();

        $next = 1;
        if ($last && $last->num_engagement) {
            $parts = explode('/', $last->num_engagement);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $next = intval($parts[0]) + 1;
            }
        }
        return sprintf('%03d/ARMP/DG/DAAF/SRH/FM-%s', $next, $yy);
    }

    /**
     * Convertit un nombre en lettres (français)
     */
    private function numberToWords($num)
    {
        if ($num == 0) return 'zéro';

        $ones = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
            'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
        $scales = ['', 'mille', 'million', 'milliard'];

        $self = $this;
        $convertHundreds = function($n) use ($ones, $tens, $self) {
            if ($n == 0) return '';
            if ($n < 20) return $ones[$n];
            if ($n < 100) {
                $ten = intval($n / 10);
                $one = $n % 10;
                if ($ten == 7 || $ten == 9) {
                    $base = $ten == 7 ? 60 : 80;
                    $remainder = $n - $base;
                    if ($remainder == 0) return $tens[$ten];
                    if ($remainder < 20) return $tens[$ten] . '-' . $ones[$remainder];
                    return $tens[$ten] . '-' . $self->numberToWords($remainder);
                }
                if ($one == 0) return $tens[$ten];
                if ($one == 1) return $tens[$ten] . '-et-un';
                return $tens[$ten] . '-' . $ones[$one];
            }
            $hundred = intval($n / 100);
            $remainder = $n % 100;
            $result = '';
            if ($hundred == 1) {
                $result = 'cent';
            } else {
                $result = $ones[$hundred] . '-cent';
            }
            if ($remainder > 0) {
                $result .= '-' . $self->numberToWords($remainder);
            }
            return $result;
        };

        $convert = function($n, $scaleIndex) use ($scales, $convertHundreds, $self) {
            if ($n == 0) return '';
            $scale = $scales[$scaleIndex];
            $remainder = $n % 1000;
            $quotient = intval($n / 1000);

            $result = '';
            if ($remainder > 0) {
                $result = $convertHundreds($remainder);
                if ($scale && $scaleIndex > 0) {
                    $result .= ' ' . $scale;
                    if ($remainder > 1 && $scaleIndex == 1) $result .= 's';
                }
            }

            if ($quotient > 0) {
                $prefix = $self->numberToWords($quotient);
                if ($prefix) {
                    $result = $prefix . ($result ? ' ' . $result : '');
                }
            }

            return $result;
        };

        $words = $convert($num, 0);
        return ucfirst($words) . ' ariary';
    }

    /**
     * Résout le bénéficiaire (agent/conjoint/enfant) et applique les règles métier
     * - Vérifie l'appartenance (emp_conj / emp_enfant)
     * - Vérifie l'âge < 21 ans pour les enfants
     */
    private function resolveBeneficiaire(int $empCode, array $payload): array
    {
        $type = strtolower($payload['beneficiaire_type'] ?? 'agent');
        $db = \Config\Database::connect();

        if ($type === 'agent') {
            $emp = $db->table('employee')->where('emp_code', $empCode)->get()->getRowArray();
            if (!$emp) {
                throw new \Exception('Employé introuvable');
            }
            return [
                'lien' => 'AGENT',
                'nom' => $emp['nom'] ?? '',
                'prenom' => $emp['prenom'] ?? '',
                'enf_code' => null,
                'conj_code' => null,
            ];
        }

        if ($type === 'conjoint' || $type === 'conjointe') {
            $conjCode = $payload['conj_code'] ?? null;
            if (!$conjCode) {
                throw new \Exception('conj_code obligatoire pour un conjoint');
            }
            $exists = $db->table('emp_conj')
                ->where('emp_code', $empCode)
                ->where('conj_code', $conjCode)
                ->countAllResults();
            if ($exists === 0) {
                throw new \Exception('Ce conjoint n’appartient pas à cet employé');
            }
            $conj = (new ConjointeModel())->find($conjCode);
            return [
                'lien' => 'CONJOINT(E)',
                'nom' => $conj['conj_nom'] ?? '',
                'prenom' => '',
                'enf_code' => null,
                'conj_code' => $conjCode,
            ];
        }

        if ($type === 'enfant') {
            $enfCode = $payload['enf_code'] ?? null;
            if (!$enfCode) {
                throw new \Exception('enf_code obligatoire pour un enfant');
            }
            
            // Nouvelle logique : verification directe sur la table enfant
            $enf = (new EnfantModel())->find($enfCode);
            if (!$enf) {
                throw new \Exception('Enfant introuvable');
            }
            if ($enf['emp_code'] != $empCode) {
                throw new \Exception('Cet enfant n’appartient pas à cet employé');
            }

            // Vérifier l’âge < 21 ans
            if (!empty($enf['date_naissance'])) {
                $age = (new \DateTime($enf['date_naissance']))->diff(new \DateTime())->y;
                if ($age >= 21) {
                    throw new \Exception('Âge de l’enfant >= 21 ans');
                }
            }
            return [
                'lien' => 'ENFANT',
                'nom' => $enf['enf_nom'] ?? '',
                'prenom' => '',
                'enf_code' => $enfCode,
                'conj_code' => null,
            ];
        }

        throw new \Exception('Type de bénéficiaire invalide');
    }

    /**
     * Récupérer les membres de la famille (Conjoints et Enfants)
     */
    public function getFamilyMembers($empCode = null)
    {
        if (!$empCode) return $this->fail('emp_code requis');

        $db = \Config\Database::connect();

        // Récupérer les conjoints
        $conjoints = $db->table('emp_conj')
            ->select('conjointe.*')
            ->join('conjointe', 'conjointe.conj_code = emp_conj.conj_code')
            ->where('emp_conj.emp_code', $empCode)
            ->get()->getResultArray();

        // Récupérer les enfants (filtrer < 21 ans)
        $enfantsRaw = $db->table('enfant')
            ->where('emp_code', $empCode)
            ->get()->getResultArray();
            
        // Filtrage PHP pour l'âge (plus sûr que SQL pur par rapport aux formats de date)
        $enfants = [];
        $now = new \DateTime();
        foreach ($enfantsRaw as $enf) {
            $age = 0;
            if (!empty($enf['date_naissance'])) {
                $dob = new \DateTime($enf['date_naissance']);
                $age = $dob->diff($now)->y;
            }
            // On ne renvoie que ceux < 21 ans comme demandé
            if ($age < 21) {
                $enfants[] = $enf;
            }
        }

        return $this->respond([
            'conjoints' => $conjoints,
            'enfants' => $enfants
        ]);
    }

    /**
     * Liste toutes les demandes de remboursement avec filtres
     */
    /**
     * Liste toutes les demandes de remboursement avec filtres
     */
    public function getAllDemandes()
    {
        $model = new DemandeRembModel();
        $builder = $model->select('demande_remb.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, etat_remb.eta_libelle, poste.pst_fonction AS fonction, direction.dir_nom AS direction')
            ->join('employee', 'employee.emp_code = demande_remb.emp_code', 'left')
            ->join('etat_remb', 'etat_remb.eta_code = demande_remb.eta_code', 'left')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('fonction_direc', 'fonction_direc.pst_code = poste.pst_code', 'left')
            ->join('direction', 'direction.dir_code = fonction_direc.dir_code', 'left')
            ->groupBy('demande_remb.rem_code') // Pour éviter les doublons si multiples affectations (bien que limitées par logique métier)
            ->orderBy('demande_remb.rem_code', 'DESC');

        // Filtres optionnels
        $emp = $this->request->getGet('emp_code');
        $etat = $this->request->getGet('eta_code');
        $start = $this->request->getGet('start');
        $end = $this->request->getGet('end');

        if ($emp) $builder->where('demande_remb.emp_code', $emp);
        if ($etat) $builder->where('demande_remb.eta_code', $etat);
        if ($start) $builder->where('demande_remb.rem_date >=', $start);
        if ($end) $builder->where('demande_remb.rem_date <=', $end);

        $demandes = $builder->findAll();
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($demandes);
    }

    /**
     * Détail d'une demande de remboursement
     */
    public function getDemande($id = null)
    {
        $model = new DemandeRembModel();
        $demande = $model->select('demande_remb.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, etat_remb.eta_libelle, poste.pst_fonction AS fonction, direction.dir_nom AS direction')
            ->join('employee', 'employee.emp_code = demande_remb.emp_code', 'left')
            ->join('etat_remb', 'etat_remb.eta_code = demande_remb.eta_code', 'left')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('fonction_direc', 'fonction_direc.pst_code = poste.pst_code', 'left')
            ->join('direction', 'direction.dir_code = fonction_direc.dir_code', 'left')
            ->where('demande_remb.rem_code', $id)
            ->first();

        if (!$demande) {
            return $this->failNotFound('Demande de remboursement non trouvée');
        }

        // Récupérer l'historique des validations
        $signatureModel = new SignatureDemandeModel();
        $signatures = $signatureModel->select('signature_demande.*, signature.sign_libele')
            ->join('signature', 'signature.sign_code = signature_demande.sign_code', 'left')
            ->where('signature_demande.rem_code', $id)
            ->orderBy('signature_demande.date_', 'ASC')
            ->findAll();

        return $this->respond([
            'demande' => $demande,
            'historique' => $signatures
        ]);
    }

    /**
     * Créer une demande de remboursement - Mode Agent (Indirect)
     */
    public function createIndirect()
    {
        $data = $this->request->getJSON(true);

        try {
            // Validation minimale
            $required = ['rem_montant', 'emp_code', 'beneficiaire_type'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    return $this->fail("Champ obligatoire manquant: $field");
                }
            }
            if ($data['rem_montant'] <= 0) {
                return $this->failValidationErrors('Le montant doit être positif');
            }

            $empCode = (int)$data['emp_code'];

            // Résoudre le bénéficiaire (et vérifier enfant < 21 ans)
            $benef = $this->resolveBeneficiaire($empCode, $data);

            // Récupérer l'état SOUMIS
            $etatModel = new EtatRembModel();
            $etatSoumis = $etatModel->where('eta_libelle', 'SOUMIS')->first();
            if (!$etatSoumis) {
                $etatModel->insert(['eta_libelle' => 'SOUMIS']);
                $etatSoumis = $etatModel->where('eta_libelle', 'SOUMIS')->first();
            }

            // Générer le numéro de demande
            $numDemande = $this->generateNumDemande();

            $demandeData = [
                'num_demande' => $numDemande,
                'rem_objet' => $data['rem_objet'] ?? 'Demande de remboursement de frais médicaux',
                'rem_date' => date('Y-m-d'),
                'rem_montant' => $data['rem_montant'],
                'rem_montant_lettre' => $data['rem_montant_lettre'] ?? null,
                'nom_malade' => trim($benef['nom'] . ' ' . ($benef['prenom'] ?? '')),
                'lien_malade' => $benef['lien'],
                'has_ordonnance' => $data['has_ordonnance'] ?? false,
                'has_facture' => $data['has_facture'] ?? false,
                'has_prise_en_charge' => $data['has_prise_en_charge'] ?? false,
                'pec_reference' => $data['pec_reference'] ?? null,
                'date_consultation' => $data['date_consultation'] ?? null,
                'emp_code' => $empCode,
                'eta_code' => $etatSoumis['eta_code'],
            ];

            // Lier un pec_code si fourni
            if (!empty($data['pec_code'])) {
                $demandeData['pec_code'] = (int)$data['pec_code'];
            }

            $model = new DemandeRembModel();
            $db = \Config\Database::connect();
            $db->transStart();

            $id = $model->insert($demandeData);
            if ($id === false) {
                throw new \Exception('Impossible de créer la demande');
            }

            // Enregistrer les pièces cochées (optionnel)
            if (!empty($data['pieces']) && is_array($data['pieces'])) {
                $pieceModel = new PieceModel();
                foreach ($data['pieces'] as $pc) {
                    $pieceModel->insert([
                        'pc_piece' => (string)$pc,
                        'rem_code' => $id,
                    ]);
                }
            }

            // Lier le centre de santé si fourni (table Asso_30)
            if (!empty($data['cen_code'])) {
                $db->table('Asso_30')->insert([
                    'rem_code' => $id,
                    'cen_code' => (int)$data['cen_code']
                ]);
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \Exception('Erreur lors de la transaction');
            }

            $created = $model->find($id);
            return $this->respondCreated([
                'demande' => $created,
                'message' => 'Demande créée avec succès',
                'num_demande' => $numDemande
            ]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * Validation RRH (niveau 1)
     */
    public function validerRRH($id = null)
    {
        return $this->traiterValidation($id, 'SOUMIS', 'VALIDE_RRH', 'RRH');
    }

    /**
     * Validation DAAF (niveau 2)
     */
    public function validerDAAF($id = null)
    {
        return $this->traiterValidation($id, 'VALIDE_RRH', 'VALIDE_DAAF', 'DAAF');
    }

    /**
     * Engagement Finance
     */
    public function engager($id = null)
    {
        $model = new DemandeRembModel();
        $demande = $model->find($id);
        if (!$demande) return $this->failNotFound('Demande non trouvée');

        $etatModel = new EtatRembModel();
        $etatActuel = $etatModel->find($demande['eta_code']);
        
        if ($etatActuel['eta_libelle'] !== 'VALIDE_DAAF') {
            return $this->failValidationErrors('La demande doit être validée par DAAF avant engagement');
        }

        $data = $this->request->getJSON(true);
        $numEngagement = $data['num_engagement'] ?? 'ENG-' . date('Ymd') . '-' . $id;

        // Passer à l'état ENGAGE
        $etatEngage = $etatModel->where('eta_libelle', 'ENGAGE')->first();
        if (!$etatEngage) {
            $etatModel->insert(['eta_libelle' => 'ENGAGE']);
            $etatEngage = $etatModel->where('eta_libelle', 'ENGAGE')->first();
        }

        $model->update($id, [
            'eta_code' => $etatEngage['eta_code'],
            'num_engagement' => $numEngagement,
            'date_engagement' => date('Y-m-d'),
            'montant_valide' => $data['montant_valide'] ?? $demande['rem_montant']
        ]);

        $this->enregistrerAudit($id, 'ENGAGEMENT', 'FINANCE');

        return $this->respond([
            'demande' => $model->find($id),
            'message' => 'Engagement créé',
            'num_engagement' => $numEngagement
        ]);
    }

    /**
     * Paiement Finance
     */
    public function payer($id = null)
    {
        $model = new DemandeRembModel();
        $demande = $model->find($id);
        if (!$demande) return $this->failNotFound('Demande non trouvée');

        $etatModel = new EtatRembModel();
        $etatActuel = $etatModel->find($demande['eta_code']);
        
        if ($etatActuel['eta_libelle'] !== 'ENGAGE') {
            return $this->failValidationErrors('La demande doit être engagée avant paiement');
        }

        $etatPaye = $etatModel->where('eta_libelle', 'PAYE')->first();
        if (!$etatPaye) {
            $etatModel->insert(['eta_libelle' => 'PAYE']);
            $etatPaye = $etatModel->where('eta_libelle', 'PAYE')->first();
        }

        $model->update($id, [
            'eta_code' => $etatPaye['eta_code'],
            'date_paiement' => date('Y-m-d')
        ]);

        $this->enregistrerAudit($id, 'PAIEMENT', 'FINANCE');

        return $this->respond([
            'demande' => $model->find($id),
            'message' => 'Paiement enregistré'
        ]);
    }

    /**
     * Rejeter une demande
     */
    public function rejeter($id = null)
    {
        $model = new DemandeRembModel();
        $demande = $model->find($id);
        if (!$demande) return $this->failNotFound('Demande non trouvée');

        $data = $this->request->getJSON(true);
        $motif = $data['motif'] ?? 'Motif non spécifié';

        $etatModel = new EtatRembModel();
        $etatRejete = $etatModel->where('eta_libelle', 'REJETE')->first();
        if (!$etatRejete) {
            $etatModel->insert(['eta_libelle' => 'REJETE']);
            $etatRejete = $etatModel->where('eta_libelle', 'REJETE')->first();
        }

        $model->update($id, [
            'eta_code' => $etatRejete['eta_code'],
            'motif_rejet' => $motif
        ]);

        $this->enregistrerAudit($id, 'REJET', '', $motif);

        return $this->respond([
            'demande' => $model->find($id),
            'message' => 'Demande rejetée'
        ]);
    }

    /**
     * Traiter une validation (méthode générique)
     */
    private function traiterValidation($id, $etatAttendu, $prochainEtat, $role)
    {
        $model = new DemandeRembModel();
        $demande = $model->find($id);
        if (!$demande) return $this->failNotFound('Demande non trouvée');

        $etatModel = new EtatRembModel();
        $etatActuel = $etatModel->find($demande['eta_code']);
        
        if ($etatActuel['eta_libelle'] !== $etatAttendu) {
            return $this->failValidationErrors("État invalide. Attendu: $etatAttendu, Actuel: " . $etatActuel['eta_libelle']);
        }

        $data = $this->request->getJSON(true);
        $decision = $data['decision'] ?? 'APPROUVE';

        if ($decision === 'REFUSE') {
            return $this->rejeter($id);
        }

        // Passer au prochain état
        $nouvelEtat = $etatModel->where('eta_libelle', $prochainEtat)->first();
        if (!$nouvelEtat) {
            $etatModel->insert(['eta_libelle' => $prochainEtat]);
            $nouvelEtat = $etatModel->where('eta_libelle', $prochainEtat)->first();
        }

        $updateData = ['eta_code' => $nouvelEtat['eta_code']];
        
        // Si montant ajusté
        if (isset($data['montant_valide'])) {
            $updateData['montant_valide'] = $data['montant_valide'];
        }

        $model->update($id, $updateData);
        $this->enregistrerAudit($id, $prochainEtat, $role);

        return $this->respond([
            'demande' => $model->find($id),
            'message' => "Validation $role effectuée",
            'nouvel_etat' => $prochainEtat
        ]);
    }

    /**
     * Enregistrer une trace d'audit
     */
    private function enregistrerAudit($remCode, $action, $role, $commentaire = '')
    {
        $signatureModel = new SignatureDemandeModel();
        $signatureModel->insert([
            'rem_code' => $remCode,
            'sign_code' => 1,
            'sin_dem_code' => $action . '_' . $role . '_' . time(),
            'date_' => date('Y-m-d')
        ]);
    }

    /**
     * Exporter en PDF - Format officiel ARMP
     */
    public function exportPdf($id = null)
    {
        $model = new DemandeRembModel();
        $demande = $model->select('demande_remb.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, poste.pst_fonction AS fonction, direction.dir_nom AS direction')
            ->join('employee', 'employee.emp_code = demande_remb.emp_code', 'left')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('fonction_direc', 'fonction_direc.pst_code = poste.pst_code', 'left')
            ->join('direction', 'direction.dir_code = fonction_direc.dir_code', 'left')
            ->where('demande_remb.rem_code', $id)
            ->first();

        if (!$demande) return $this->failNotFound('Demande non trouvée');

        $today = date('d/m/Y');

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(20, 15, 20);
        $pdf->SetAutoPageBreak(false);

        // Logo/En-tête gauche
        $pdf->SetFont('Arial', '', 7);
        $pdf->MultiCell(80, 3, utf8_decode("AUTORITÉ DE RÉGULATION\nDES MARCHÉS PUBLICS\n---\nDIRECTION GÉNÉRALE\n---\nDIRECTION DES AFFAIRES\nADMINISTRATIVES ET FINANCIÈRES"), 0, 'L');
        
        $pdf->Ln(8);

        // Titre principal
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, utf8_decode('DEMANDE DE REMBOURSEMENT DE FRAIS'), 0, 1, 'C');
        $pdf->Cell(0, 6, utf8_decode('MÉDICAUX'), 0, 1, 'C');
        $pdf->Ln(3);

        // Numéro de demande
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, utf8_decode('N°: ' . ($demande['num_demande'] ?? '___/ARMP/DG/DAAF/SRH-' . date('y'))), 0, 1, 'L');
        $pdf->Ln(5);

        // Infos agent
        $lh = 6;
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, $lh, utf8_decode('Mr./Mme/Melle :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lh, utf8_decode(strtoupper(($demande['nom_emp'] ?? '') . ' ' . ($demande['prenom_emp'] ?? ''))), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, $lh, utf8_decode('Matricule n° :'), 0, 0);
        $pdf->Cell(0, $lh, utf8_decode($demande['matricule'] ?? ''), 0, 1);

        $pdf->Cell(50, $lh, utf8_decode('Direction/Service :'), 0, 0);
        $pdf->Cell(0, $lh, utf8_decode($demande['direction'] ?? ''), 0, 1);

        $pdf->Cell(50, $lh, utf8_decode('Fonction ou grade :'), 0, 0);
        $pdf->Cell(0, $lh, utf8_decode($demande['fonction'] ?? ''), 0, 1);

        $pdf->Ln(3);
        $pdf->Cell(0, $lh, utf8_decode('Sollicite le remboursement des frais médicaux de :'), 0, 1);

        // Infos malade
        $pdf->Cell(50, $lh, utf8_decode('Nom du malade :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lh, utf8_decode($demande['nom_malade'] ?? ''), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, $lh, utf8_decode('Lien :'), 0, 0);
        $pdf->Cell(0, $lh, utf8_decode($demande['lien_malade'] ?? ''), 0, 1);

        // Montant
        $pdf->Ln(3);
        $pdf->Cell(50, $lh, utf8_decode('Montant :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $montant = number_format($demande['rem_montant'] ?? 0, 2, ',', ' ');
        $pdf->Cell(0, $lh, utf8_decode($montant . ' Ariary (Ar ' . $montant . ')'), 0, 1);

        // Pièces fournies
        $pdf->Ln(3);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, $lh, utf8_decode('Pièces fournies :'), 0, 1);
        
        $checkOrd = ($demande['has_ordonnance'] ?? false) ? '☑' : '☐';
        $checkFac = ($demande['has_facture'] ?? false) ? '☑' : '☐';
        $checkPec = ($demande['has_prise_en_charge'] ?? false) ? '☑' : '☐';
        
        $pdf->Cell(50, $lh, utf8_decode("$checkOrd Ordonnance"), 0, 0);
        $pdf->Cell(50, $lh, utf8_decode("$checkFac Facture(s)"), 0, 0);
        $pdf->Cell(0, $lh, utf8_decode("$checkPec Prise en charge n°: " . ($demande['pec_reference'] ?? '')), 0, 1);

        // Date consultation
        $pdf->Cell(50, $lh, utf8_decode('Date de consultation :'), 0, 0);
        $pdf->Cell(0, $lh, utf8_decode($demande['date_consultation'] ?? ''), 0, 1);

        // Zone AVIS
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lh, utf8_decode('AVIS :'), 0, 1);
        $pdf->Ln(15);

        // Signatures
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, utf8_decode('Antananarivo, le ' . $today), 0, 1, 'R');
        $pdf->Ln(10);
        
        $pdf->Cell(60, 5, utf8_decode('Le Demandeur'), 0, 0, 'C');
        $pdf->Cell(60, 5, utf8_decode('Le Responsable des'), 0, 0, 'C');
        $pdf->Cell(60, 5, utf8_decode('Le Directeur des Affaires'), 0, 1, 'C');
        
        $pdf->Cell(60, 5, '', 0, 0, 'C');
        $pdf->Cell(60, 5, utf8_decode('Ressources Humaines'), 0, 0, 'C');
        $pdf->Cell(60, 5, utf8_decode('Administratives et Financières'), 0, 1, 'C');

        $content = $pdf->Output('S');
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="demande_remboursement_' . $id . '.pdf"')
            ->setBody($content);
    }

    /**
     * Export état de remboursement mensuel (Agent)
     * GET /api/remboursement/etat/agent/pdf?emp_code=&annee=&mois=
     * Génère l'état de remboursement pour un agent sur un mois donné
     * Ne liste que les demandes validées (VALIDE_DAAF ou ENGAGE)
     */
    public function exportEtatAgentPdf()
    {
        $empCode = (int)($this->request->getGet('emp_code') ?? 0);
        $annee = (int)($this->request->getGet('annee') ?? date('Y'));
        $mois = (int)($this->request->getGet('mois') ?? date('m'));

        if ($empCode <= 0) {
            return $this->failValidationErrors('emp_code requis');
        }
        if ($mois < 1 || $mois > 12) {
            return $this->failValidationErrors('mois invalide');
        }

        $db = \Config\Database::connect();
        
        // Récupérer l'employé avec fonction et direction
        $emp = $db->table('employee e')
            ->select('e.*, p.pst_fonction, d.dir_nom')
            ->join('affectation a', 'a.emp_code = e.emp_code', 'left')
            ->join('poste p', 'p.pst_code = a.pst_code', 'left')
            ->join('fonction_direc fd', 'fd.pst_code = p.pst_code', 'left')
            ->join('direction d', 'd.dir_code = fd.dir_code', 'left')
            ->where('e.emp_code', $empCode)
            ->orderBy('a.affec_date_debut', 'DESC')
            ->limit(1)
            ->get()->getRowArray();
            
        if (!$emp) return $this->failNotFound('Employé introuvable');

        // Récupérer les états VALIDE_DAAF et ENGAGE
        $etatModel = new EtatRembModel();
        $etatValideDAAF = $etatModel->where('eta_libelle', 'VALIDE_DAAF')->first();
        $etatEngage = $etatModel->where('eta_libelle', 'ENGAGE')->first();
        $etatCodes = [];
        if ($etatValideDAAF) $etatCodes[] = $etatValideDAAF['eta_code'];
        if ($etatEngage) $etatCodes[] = $etatEngage['eta_code'];

        if (empty($etatCodes)) {
            return $this->failNotFound('Aucun état de validation trouvé');
        }

        // Récupérer les demandes validées pour cette période
        $model = new DemandeRembModel();
        $builder = $model->select('demande_remb.*, employee.emp_imarmp AS matricule')
            ->join('employee', 'employee.emp_code = demande_remb.emp_code', 'left')
            ->where('demande_remb.emp_code', $empCode)
            ->where('EXTRACT(YEAR FROM rem_date) =', $annee, false)
            ->where('EXTRACT(MONTH FROM rem_date) =', $mois, false)
            ->whereIn('demande_remb.eta_code', $etatCodes)
            ->orderBy('rem_date', 'ASC');

        $rows = $builder->findAll();

        if (empty($rows)) {
            return $this->failNotFound('Aucune demande validée pour cette période');
        }

        $numEtat = $this->generateEtatNum($annee);
        $moisNoms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                     'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        $moisLib = $moisNoms[$mois] ?? '';

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // En-tête
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 4, utf8_decode("AUTORITE DE REGULATION DES MARCHES PUBLICS\nDIRECTION GENERALE\nDIRECTION DES AFFAIRES ADMINISTRATIVES ET FINANCIERES"), 0, 'L');
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, utf8_decode('ETAT DE REMBOURSEMENT DES FRAIS MEDICAUX'), 0, 1, 'C');
        $pdf->Ln(2);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, utf8_decode("N° : $numEtat"), 0, 1, 'L');
        $pdf->Cell(0, 6, utf8_decode("Mois : $moisLib $annee"), 0, 1, 'L');
        $pdf->Ln(2);

        // Informations agent
        $nomComplet = trim(($emp['nom'] ?? '') . ' ' . ($emp['prenom'] ?? ''));
        $pdf->Cell(0, 6, utf8_decode("Nom de l'Agent : $nomComplet"), 0, 1, 'L');
        $pdf->Cell(0, 6, utf8_decode("Fonction : " . ($emp['pst_fonction'] ?? '-')), 0, 1, 'L');
        $pdf->Cell(0, 6, utf8_decode("Direction : " . ($emp['dir_nom'] ?? '-')), 0, 1, 'L');
        $pdf->Cell(0, 6, utf8_decode("IM. : " . ($emp['matricule'] ?? '')), 0, 1, 'L');
        $pdf->Ln(4);

        // Tableau avec colonne N° Facture
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(8, 8, utf8_decode('N°'), 1, 0, 'C');
        $pdf->Cell(45, 8, utf8_decode('Nom du malade'), 1, 0, 'L');
        $pdf->Cell(18, 8, utf8_decode('Lien'), 1, 0, 'C');
        $pdf->Cell(35, 8, utf8_decode('N° Prise en charge'), 1, 0, 'C');
        $pdf->Cell(35, 8, utf8_decode('Objet du remboursement'), 1, 0, 'L');
        $pdf->Cell(30, 8, utf8_decode('N° Facture'), 1, 0, 'C');
        $pdf->Cell(25, 8, utf8_decode('Montant Total'), 1, 1, 'R');

        $pdf->SetFont('Arial', '', 8);
        $total = 0;
        $i = 1;
        foreach ($rows as $row) {
            // Format facture : "354914 du 03/07/2025" (si disponible, sinon date consultation)
            $factureInfo = '';
            if (!empty($row['date_consultation'])) {
                $dateFact = date('d/m/Y', strtotime($row['date_consultation']));
                $factureInfo = $dateFact; // On pourrait ajouter un numéro de facture si stocké
            }
            
            $pdf->Cell(8, 7, $i, 1, 0, 'C');
            $pdf->Cell(45, 7, utf8_decode($row['nom_malade'] ?? ''), 1, 0, 'L');
            $pdf->Cell(18, 7, utf8_decode($row['lien_malade'] ?? ''), 1, 0, 'C');
            $pdf->Cell(35, 7, utf8_decode($row['pec_reference'] ?? ''), 1, 0, 'C');
            
            // Objet (peut être long, on tronque si nécessaire)
            $objet = mb_substr($row['rem_objet'] ?? '', 0, 40);
            $pdf->Cell(35, 7, utf8_decode($objet), 1, 0, 'L');
            $pdf->Cell(30, 7, utf8_decode($factureInfo), 1, 0, 'C');
            $pdf->Cell(25, 7, number_format((float)$row['rem_montant'], 0, ',', ' '), 1, 1, 'R');
            
            $total += (float)$row['rem_montant'];
            $i++;
        }

        // Total
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(171, 8, utf8_decode('TOTAL'), 1, 0, 'R');
        $pdf->Cell(25, 8, number_format($total, 0, ',', ' '), 1, 1, 'R');

        $pdf->Ln(6);
        
        // Total en lettres
        $totalLettres = $this->numberToWords((int)$total);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, utf8_decode("Arrêté le présent état à la somme de : $totalLettres"), 0, 'L');
        
        $pdf->Ln(10);

        // Sections signatures
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(95, 5, utf8_decode('Présentateur:'), 0, 0, 'L');
        $pdf->Cell(95, 5, utf8_decode('Antananarivo, le ' . date('d/m/Y')), 0, 1, 'R');
        $pdf->Ln(2);
        $pdf->Cell(95, 5, utf8_decode('Le Responsable des Ressources Humaines p. i'), 0, 0, 'L');
        $pdf->Cell(95, 5, utf8_decode("L'Ordonnateur Secondaire"), 0, 1, 'R');
        $pdf->Ln(8);
        $pdf->Cell(95, 5, utf8_decode('Signature:'), 0, 0, 'L');
        $pdf->Cell(95, 5, utf8_decode(''), 0, 1, 'R');

        $content = $pdf->Output('S');
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="etat_remboursement_agent_' . $empCode . '_' . $annee . '_' . $mois . '.pdf"')
            ->setBody($content);
    }
}
