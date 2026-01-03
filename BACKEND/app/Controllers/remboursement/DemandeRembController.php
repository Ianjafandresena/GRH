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
     * Générer le numéro de demande: NNN/ARMP/DG/DAAF/[SERVICE]/[MOIS]-YY
     * Exemple: 001/ARMP/DG/DAAF/SRH/DE-25
     */
    private function generateNumDemande(int $empCode)
    {
        $db = \Config\Database::connect();
        
        // 1. Déterminer le service de l'agent
        $employee = $db->table('employee')
            ->select('direction.dir_code')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('direction', 'direction.dir_code = affectation.dir_code', 'left')
            ->where('employee.emp_code', $empCode)
            ->get()->getRowArray();
        
        // Mapping direction -> code service
        $serviceMap = [
            1 => 'DG', 2 => 'DAAF', 3 => 'DSI',
            4 => 'SRH', 5 => 'COMPTA', 6 => 'LOG'
        ];
        $serviceCode = $serviceMap[$employee['dir_code'] ?? 4] ?? 'SRH';
        
        // 2. Obtenir mois et année actuels
        $moisMap = [
            '01' => 'JA', '02' => 'FE', '03' => 'MA', '04' => 'AV',
            '05' => 'MI', '06' => 'JU', '07' => 'JL', '08' => 'AO',
            '09' => 'SE', '10' => 'OC', '11' => 'NO', '12' => 'DE'
        ];
        $moisCode = $moisMap[date('m')];
        $annee = date('y');
        
        // 3. Compter TOUTES les demandes pour obtenir le séquentiel global
        $count = $db->table('demande_remb')->countAllResults();
        $sequential = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        
        // 4. Construire le numéro final
        return "{$sequential}/ARMP/DG/DAAF/{$serviceCode}/{$moisCode}-{$annee}";
    }

    /**
     * Génère un numéro pour l'état mensuel (agent)
     * Format : NNN/ARMP/DG/DAAF/SRH/FM-YY (selon l'image fournie)
     */
    private function generateEtatNum($year)
    {
        $db = \Config\Database::connect();
        $yy = substr((string)$year, -2);
        
        // Chercher le dernier numéro d'état pour cette année
        $last = $db->table('etat_remb')
            ->like('etat_num', "/ARMP/DG/DAAF/SRH/FM-$yy", 'after')
            ->orderBy('eta_code', 'DESC')
            ->get()
            ->getRow();

        $next = 1;
        if ($last && $last->etat_num) {
            $parts = explode('/', $last->etat_num);
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
        $builder = $model->select('DISTINCT ON (demande_remb.rem_code) demande_remb.*, demande_remb.rem_is_centre, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, poste.pst_fonction AS fonction, direction.dir_nom AS direction, etat_remb.etat_num')
            ->join('employee', 'employee.emp_code = demande_remb.emp_code', 'left')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('fonction_direc', 'fonction_direc.pst_code = poste.pst_code', 'left')
            ->join('etat_remb', 'etat_remb.eta_code = demande_remb.eta_code', 'left')
            ->join('direction', 'direction.dir_code = fonction_direc.dir_code', 'left')
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
        
        // Normaliser les booléens PostgreSQL pour JavaScript
        foreach ($demandes as &$demande) {
            $demande['rem_is_centre'] = ($demande['rem_is_centre'] === true || $demande['rem_is_centre'] === 't');
            $demande['rem_status'] = ($demande['rem_status'] === true || $demande['rem_status'] === 't');
        }
        
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($demandes);
    }

    /**
     * Détail d'une demande de remboursement
     */
    public function getDemande($id = null)
    {
        try {
        $model = new DemandeRembModel();
        $demande = $model->select('demande_remb.*, 
                              employee.emp_nom AS nom_emp, 
                              employee.emp_prenom AS prenom_emp, 
                              employee.emp_imarmp AS matricule, 
                              poste.pst_fonction AS fonction, 
                              direction.dir_nom AS direction, 
                              etat_remb.etat_num,
                              pris_en_charge.pec_num,
                              COALESCE(pec_employee.emp_nom || \' \' || pec_employee.emp_prenom, pec_conj.conj_nom, pec_enf.enf_nom) AS beneficiaire_nom,
                              CASE 
                                WHEN pris_en_charge.emp_code IS NOT NULL THEN \'Agent\'
                                WHEN pris_en_charge.conj_code IS NOT NULL THEN \'Conjoint\'
                                WHEN pris_en_charge.enf_code IS NOT NULL THEN \'Enfant\'
                                ELSE NULL
                              END AS beneficiaire_lien,
                              centre_sante.cen_nom')
            ->join('employee', 'employee.emp_code = demande_remb.emp_code', 'left')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('fonction_direc', 'fonction_direc.pst_code = poste.pst_code', 'left')
            ->join('direction', 'direction.dir_code = fonction_direc.dir_code', 'left')
            ->join('etat_remb', 'etat_remb.eta_code = demande_remb.eta_code', 'left')
            ->join('pris_en_charge', 'pris_en_charge.pec_code = demande_remb.pec_code', 'left')
            ->join('employee pec_employee', 'pec_employee.emp_code = pris_en_charge.emp_code', 'left')
            ->join('conjointe pec_conj', 'pec_conj.conj_code = pris_en_charge.conj_code', 'left')
            ->join('enfant pec_enf', 'pec_enf.enf_code = pris_en_charge.enf_code', 'left')
            ->join('centre_sante', 'centre_sante.cen_code = demande_remb.cen_code', 'left')
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
        } catch (\Throwable $e) {
            log_message('error', '[getDemande] Error: ' . $e->getMessage());
            log_message('error', '[getDemande] Trace: ' . $e->getTraceAsString());
            return $this->failServerError('Erreur: ' . $e->getMessage());
        }
    }

    /**
     * Créer une demande de remboursement - Mode Agent (Indirect)
     */
    public function createIndirect()
    {
        $data = $this->request->getJSON(true);

        try {
            // Validation minimale
            $required = ['rem_montant', 'emp_code', 'pec_code'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    return $this->fail("Champ obligatoire manquant: $field");
                }
            }
            if ($data['rem_montant'] <= 0) {
                return $this->failValidationErrors('Le montant doit être positif');
            }

            $empCode = (int)$data['emp_code'];
            $pecCode = (int)$data['pec_code'];

            // Récupérer les infos depuis la PEC (bénéficiaire + centre)
            $pecModel = new \App\Models\remboursement\PrisEnChargeModel();
            $pec = $pecModel->find($pecCode);
            if (!$pec) {
                return $this->failNotFound('Prise en charge introuvable');
            }
            if (empty($pec['pec_approuver']) || $pec['pec_approuver'] === 'f' || $pec['pec_approuver'] === false) {
                return $this->failValidationErrors('La prise en charge doit être validée avant de créer une demande');
            }

            // Déduire beneficiaire_type depuis la PEC
            if (!empty($pec['conj_code'])) {
                $data['beneficiaire_type'] = 'conjoint';
                $data['conj_code'] = $pec['conj_code'];
            } elseif (!empty($pec['enf_code'])) {
                $data['beneficiaire_type'] = 'enfant';
                $data['enf_code'] = $pec['enf_code'];
            } else {
                $data['beneficiaire_type'] = 'agent';
            }

            // Utiliser le centre de la PEC si non fourni
            if (empty($data['cen_code']) && !empty($pec['cen_code'])) {
                $data['cen_code'] = $pec['cen_code'];
            }

            // Résoudre le bénéficiaire (et vérifier enfant < 21 ans)
            $benef = $this->resolveBeneficiaire($empCode, $data);

            // Générer le numéro de demande
            $numDemande = $this->generateNumDemande();

            $demandeData = [
                'rem_num' => $numDemande,
                'rem_date' => date('Y-m-d'),
                'rem_montant' => $data['rem_montant'],
                'rem_montant_lettre' => $data['rem_montant_lettre'] ?? null,
                'rem_status' => false, // New demande starts as not validated
                'emp_code' => $empCode,
                'obj_code' => $data['obj_code'] ?? null,
                'cen_code' => $data['cen_code'] ?? null,
                'fac_code' => $data['fac_code'] ?? null,
                'pec_code' => $pecCode,
            ];

            $model = new DemandeRembModel();
            $db = \Config\Database::connect();
            $db->transStart();

            // Step 1: Insert demande
            try {
                $id = $model->insert($demandeData);
                if ($id === false) {
                    $err = $model->errors();
                    throw new \Exception('Insert demande failed: ' . json_encode($err));
                }
            } catch (\Throwable $e) {
                $db->transRollback();
                throw new \Exception('Step 1 (insert demande): ' . $e->getMessage());
            }

            // Step 2: Insert pieces (optional)
            if (!empty($data['pieces']) && is_array($data['pieces'])) {
                try {
                    $pieceModel = new PieceModel();
                    foreach ($data['pieces'] as $pc) {
                        $result = $pieceModel->insert([
                            'pc_nom' => (string)$pc,
                            'rem_code' => $id,
                        ]);
                        if ($result === false) {
                            throw new \Exception('Piece insert failed: ' . json_encode($pieceModel->errors()));
                        }
                    }
                } catch (\Throwable $e) {
                    $db->transRollback();
                    throw new \Exception('Step 2 (insert pieces): ' . $e->getMessage());
                }
            }

            // Step 3: Link centre (table Asso_30) - SKIP if not needed
            // Note: This might fail if Asso_30 table doesn't exist or has different structure
            // Commenting out for now since cen_code is already in demande_remb
            /*
            if (!empty($data['cen_code'])) {
                try {
                    $db->table('Asso_30')->insert([
                        'rem_code' => $id,
                        'cen_code' => (int)$data['cen_code']
                    ]);
                } catch (\Throwable $e) {
                    $db->transRollback();
                    throw new \Exception('Step 3 (Asso_30): ' . $e->getMessage());
                }
            }
            */

            $db->transComplete();
            if ($db->transStatus() === false) {
                $error = $db->error();
                throw new \Exception('Erreur lors de la transaction: ' . ($error['message'] ?? 'Unknown'));
            }

            $created = $model->find($id);
            return $this->respondCreated([
                'demande' => $created,
                'message' => 'Demande créée avec succès',
                'num_demande' => $numDemande,
                'rem_num' => $numDemande
            ]);
        } catch (\Exception $e) {
            if (isset($db)) {
                $error = $db->error();
                $sqlError = $error['message'] ?? '';
                return $this->fail($e->getMessage() . ' | SQL: ' . $sqlError, 500);
            }
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
     * Créer plusieurs demandes en batch (transactionnel)
     */
    public function createBatch()
    {
        $data = $this->request->getJSON(true);
        $demandes = $data['demandes'] ?? [];

        if (empty($demandes) || !is_array($demandes)) {
            return $this->fail('Aucune demande fournie');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $results = [];
        $factureMap = []; // Map fac_num => fac_code pour réutiliser les factures
        $factureModel = new \App\Models\remboursement\FactureModel();
        $demandeModel = new DemandeRembModel();
        $pieceModel = new PieceModel();
        $pecModel = new \App\Models\remboursement\PrisEnChargeModel();

        try {
            foreach ($demandes as $idx => $dem) {
                // Validation basique
                if (empty($dem['pec_code']) || empty($dem['rem_montant']) || empty($dem['obj_code'])) {
                    throw new \Exception("Demande #" . ($idx + 1) . ": champs obligatoires manquants");
                }

                // Récupérer PEC pour emp_code et cen_code
                $pec = $pecModel->find($dem['pec_code']);
                if (!$pec) {
                    throw new \Exception("Demande #" . ($idx + 1) . ": PEC introuvable");
                }
                if (empty($pec['pec_approuver']) || $pec['pec_approuver'] === 'f') {
                    throw new \Exception("Demande #" . ($idx + 1) . ": PEC non validée");
                }

                $empCode = $pec['emp_code'];
                $cenCode = $pec['cen_code'] ?? null;

                // 1. Créer la facture si fournie (ou réutiliser si déjà créée)
                $facCode = null;
                if (!empty($dem['fac_num'])) {
                    // Vérifier si cette facture a déjà été créée dans ce batch
                    if (isset($factureMap[$dem['fac_num']])) {
                        $facCode = $factureMap[$dem['fac_num']];
                    } else {
                        // Créer nouvelle facture
                        $facId = $factureModel->insert([
                            'fac_num' => $dem['fac_num'],
                            'fac_date' => $dem['fac_date'] ?? date('Y-m-d')
                        ]);
                        if ($facId === false) {
                            throw new \Exception("Demande #" . ($idx + 1) . ": erreur création facture");
                        }
                        $facCode = $facId;
                        $factureMap[$dem['fac_num']] = $facId;
                    }
                }

                // 2. Générer numéro demande
                $numDemande = $this->generateNumDemande($empCode);

                // 3. Créer la demande
                $demandeData = [
                    'rem_num' => $numDemande,
                    'rem_date' => date('Y-m-d'),
                    'rem_montant' => $dem['rem_montant'],
                    'rem_montant_lettre' => $dem['rem_montant_lettre'] ?? null,
                    'rem_status' => false,
                    'rem_is_centre' => $dem['rem_is_centre'] ?? false,  // Définir le type
                    'emp_code' => $empCode,
                    'pec_code' => $dem['pec_code'],
                    'obj_code' => $dem['obj_code'],
                    'cen_code' => $cenCode,
                    'fac_code' => $facCode
                ];

                $remId = $demandeModel->insert($demandeData);
                if ($remId === false) {
                    throw new \Exception("Demande #" . ($idx + 1) . ": erreur création demande");
                }

                // 4. Créer les pièces
                if (!empty($dem['pieces']) && is_array($dem['pieces'])) {
                    foreach ($dem['pieces'] as $pc) {
                        $pieceModel->insert([
                            'pc_nom' => (string)$pc,
                            'rem_code' => $remId
                        ]);
                    }
                }

                $results[] = [
                    'rem_code' => $remId,
                    'rem_num' => $numDemande
                ];
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \Exception('Erreur lors de la transaction');
            }

            return $this->respondCreated([
                'message' => count($results) . ' demande(s) créée(s) avec succès',
                'demandes' => $results
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->fail($e->getMessage(), 500);
        }
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
     * Traiter une demande - l'associer à un État de Remboursement
     * POST /remboursement/:id/traiter
     * Body: { eta_code: number } (existing) OR { new_etat_num: string } (new)
     */
    public function traiter($id = null)
    {
        $model = new DemandeRembModel();
        $demande = $model->find($id);
        if (!$demande) {
            return $this->failNotFound('Demande non trouvée');
        }

        // Vérifier si déjà traité
        if ($demande['rem_status'] === true || $demande['rem_status'] === 't') {
            return $this->fail('Cette demande a déjà été traitée');
        }

        $data = $this->request->getJSON(true);
    $etaCode = $data['eta_code'] ?? null;

    // Si pas d'eta_code fourni, créer un nouvel état
    if (!$etaCode && (!empty($data['new_etat_num']) || !empty($data['create_new']))) {
        $empCode = $demande['emp_code'];
        
        $etatModel = new EtatRembModel();
        
        // Si create_new = true, générer automatiquement le numéro
        if (!empty($data['create_new'])) {
            $currentYear = date('y'); // 2 digits
            $newEtatNum = $this->generateEtatNum($currentYear);
        } else {
            $newEtatNum = $data['new_etat_num'];
            
            // Vérifier unicité
            $existing = $etatModel->where('etat_num', $newEtatNum)->first();
            if ($existing) {
                return $this->fail('Ce numéro d\'état existe déjà');
            }
        }

        $newId = $etatModel->insert([
            'etat_num' => $newEtatNum,
            'emp_code' => $empCode,
            'eta_date' => date('Y-m-d'),
            'eta_total' => 0
        ]);

        if (!$newId) {
            return $this->fail('Erreur création état');
        }

        $etaCode = $newId;
    }

    if (!$etaCode) {
        return $this->fail('Veuillez sélectionner un état ou créer un nouvel état');
    }    

        // Mettre à jour la demande
        $model->update($id, [
            'eta_code' => $etaCode,
            'rem_status' => true
        ]);

        // Recalculer le total de l'état
        $etatController = new EtatRembController();
        $newTotal = $etatController->recalculerTotal($etaCode);

        return $this->respond([
            'success' => true,
            'message' => 'Demande traitée avec succès',
            'eta_code' => $etaCode,
            'eta_total' => $newTotal,
            'demande' => $model->find($id)
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
        $pdf->Cell(0, 5, utf8_decode('N°: ' . ($demande['rem_num'] ?? '___/ARMP/DG/DAAF/SRH-' . date('y'))), 0, 1, 'L');
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
