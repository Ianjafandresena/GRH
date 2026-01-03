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
            ->select('pst.pst_fonction, pst.pst_mission, dir.dir_nom, dir.dir_abreviation')
            ->join('poste pst', 'pst.pst_code = a.pst_code', 'left')
            ->join('fonction_direc fd', 'fd.pst_code = pst.pst_code', 'left')
            ->join('direction dir', 'dir.dir_code = fd.dir_code', 'left')
            ->where('a.emp_code', $empCode)
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
            $emp = $db->table('employee')->where('emp_code', $empCode)->get()->getRowArray();
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
        $prises = $model->select('pris_en_charge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom')
            ->join('employee', 'employee.emp_code = pris_en_charge.emp_code', 'left')
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
        $prises = $model->select('pris_en_charge.*, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom, employee.emp_nom, employee.emp_prenom')
            ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
            ->join('conjointe', 'conjointe.conj_code = pris_en_charge.conj_code', 'left')
            ->join('enfant', 'enfant.enf_code = pris_en_charge.enf_code', 'left')
            ->join('employee', 'employee.emp_code = pris_en_charge.emp_code', 'left')
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
        $prise = $model->select('pris_en_charge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom')
            ->join('employee', 'employee.emp_code = pris_en_charge.emp_code', 'left')
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
     */
    public function genererBulletin($id = null)
    {
        try {
            $model = new PrisEnChargeModel();
            $prise = $model->select('pris_en_charge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom')
                ->join('employee', 'employee.emp_code = pris_en_charge.emp_code', 'left')
                ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
                ->join('conjointe', 'conjointe.conj_code = pris_en_charge.conj_code', 'left')
                ->join('enfant', 'enfant.enf_code = pris_en_charge.enf_code', 'left')
                ->where('pris_en_charge.pec_code', $id)
                ->first();

            if (!$prise) {
                return $this->failNotFound('Prise en charge non trouvee');
            }

            // Determiner le beneficiaire
            $benefNom = ($prise['nom_emp'] ?? '') . ' ' . ($prise['prenom_emp'] ?? '');
            $lien = 'AGENT';

            if (!empty($prise['conj_code'])) {
                $benefNom = $prise['conj_nom'] ?? '';
                $lien = 'Conjoint(e)';
            } elseif (!empty($prise['enf_code'])) {
                $benefNom = $prise['enf_nom'] ?? '';
                $lien = 'ENFANT';
            }

            $fd = $this->getFonctionDirection((int)$prise['emp_code']);
            $fonction = $fd['fonction'] ?: 'Chauffeur';
            $direction = $fd['direction'] ?: 'Direction des Affaires Administratives et Financieres';


            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(false);

            // === LOGO ET HEADER ===
            // Try multiple logo paths
            $logoPaths = [
                FCPATH . '../public/assets/logo.png',
                FCPATH . 'assets/logo.png',
                FCPATH . '../FRONTEND/public/assets/logo.png',
            ];
            
            $logoAdded = false;
            foreach ($logoPaths as $logoPath) {
                if (file_exists($logoPath)) {
                    try {
                        $pdf->Image($logoPath, 18, 18, 25);
                        $logoAdded = true;
                        break;
                    } catch (\Exception $e) {
                        log_message('error', 'Failed to add logo: ' . $e->getMessage());
                    }
                }
            }

            // Titre principal centre
            $pdf->SetY(18);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, utf8_decode('BULLETIN DE PRISE EN CHARGE'), 0, 1, 'C');
            $pdf->Ln(10);

            // === SECTION 1: RENSEIGNEMENTS CONCERNANT L'AGENT ===
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);

            // Titre section
            $pdf->SetFont('Arial', 'BU', 11);
            $pdf->Cell(0, 7, utf8_decode("RENSEIGNEMENTS CONCERNANT L'AGENT"), 0, 1, 'L');
            $pdf->Ln(2);

            // Nom et Matricule sur la meme ligne
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(90, 6, utf8_decode('Nom et Prenoms : ' . ($prise['nom_emp'] ?? '') . ' ' . ($prise['prenom_emp'] ?? '')), 0, 0);
            $pdf->Cell(0, 6, utf8_decode('Matricule : ' . ($prise['matricule'] ?? '')), 0, 1, 'R');

            // Fonction
            $pdf->Cell(0, 6, utf8_decode('Fonction : ' . $fonction), 0, 1);

            // Direction
            $pdf->Cell(0, 6, utf8_decode('Direction / Service : ' . $direction), 0, 1);
            $pdf->Ln(5);

            // === SECTION 2: RENSEIGNEMENTS CONCERNANT LE MALADE ===
            $pdf->SetFont('Arial', 'BU', 11);
            $pdf->Cell(0, 7, utf8_decode('RENSEIGNEMENTS CONCERNANT LE MALADE'), 0, 1, 'L');
            $pdf->Ln(2);

            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, utf8_decode('Le malade est l\'agent : '), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Noms et prenoms du malade : ' . trim($benefNom)), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Lien : ' . $lien), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Ref n° : ' . ($prise['pec_num'] ?? '')), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Date et heure d\'arrivee : ' . ($prise['pec_date_arrive'] ?? '')), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Date et heure de depart : ' . ($prise['pec_date_depart'] ?? '')), 0, 1);
            $pdf->Ln(15);

            // === SIGNATURE MEDECIN ===
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, utf8_decode('Signature et tampon du Medecin'), 0, 1);
            $pdf->Ln(20);

            // === FOOTER ===
            $pdf->SetY(-35);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, utf8_decode('Antananarivo, le'), 0, 1, 'R');
            $pdf->Ln(3);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, utf8_decode('Personnel d\'Appui Administratif et Financier'), 0, 1, 'R');
            $pdf->Ln(10);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, utf8_decode('RAZAFIMANDIMBY Danielle Tolisoa'), 0, 1, 'R');

            $content = $pdf->Output('S');
            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="prise_en_charge_' . $id . '.pdf"')
                ->setBody($content);
                
        } catch (\Exception $e) {
            log_message('error', 'PDF generation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->fail('Erreur lors de la generation du bulletin: ' . $e->getMessage(), 500);
        }
    }
}


