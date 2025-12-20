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
        $prises = $model->select('pris_en_charge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, centre_sante.cen_nom')
            ->join('employee', 'employee.emp_code = pris_en_charge.emp_code', 'left')
            ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
            ->findAll();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($prises);
    }

    /**
     * Liste les PECs d'un employé (pour liste déroulante demande remboursement)
     */
    public function getByEmployee($empCode = null)
    {
        if (!$empCode) return $this->fail('emp_code requis');
        
        $model = new PrisEnChargeModel();
        // On récupère les PECs de l'employé
        $prises = $model->select('pris_en_charge.*, centre_sante.cen_nom')
            ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
            ->where('pris_en_charge.emp_code', $empCode)
            ->orderBy('pris_en_charge.pec_code', 'DESC')
            ->findAll();

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
            if (isset($data['cen_code'])) {
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
     * Valider une prise en charge
     */
    public function valider($id = null)
    {
        $model = new PrisEnChargeModel();
        $prise = $model->find($id);

        if (!$prise) {
            return $this->failNotFound('Prise en charge non trouvée');
        }

        $data = $this->request->getJSON(true);
        $decision = $data['decision'] ?? null;

        if (!in_array($decision, ['APPROUVE', 'REFUSE'])) {
            return $this->failValidationErrors('Décision invalide');
        }

        // Enregistrer le validateur
        if (isset($data['validateur_emp_code'])) {
            $model->update($id, ['emp_code_1' => $data['validateur_emp_code']]);
        }

        $updated = $model->find($id);
        return $this->respond([
            'prise_en_charge' => $updated,
            'message' => 'Prise en charge ' . ($decision === 'APPROUVE' ? 'approuvée' : 'refusée')
        ]);
    }

    /**
     * Générer le bulletin PDF d'une prise en charge
     */
    public function genererBulletin($id = null)
    {
        $model = new PrisEnChargeModel();
        $prise = $model->select('pris_en_charge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, centre_sante.cen_nom, conjointe.conj_nom, enfant.enf_nom, enfant.date_naissance')
            ->join('employee', 'employee.emp_code = pris_en_charge.emp_code', 'left')
            ->join('centre_sante', 'centre_sante.cen_code = pris_en_charge.cen_code', 'left')
            ->join('conjointe', 'conjointe.conj_code = pris_en_charge.conj_code', 'left')
            ->join('enfant', 'enfant.enf_code = pris_en_charge.enf_code', 'left')
            ->where('pris_en_charge.pec_code', $id)
            ->first();

        if (!$prise) {
            return $this->failNotFound('Prise en charge non trouvée');
        }

        // Déterminer le bénéficiaire final
        $benefNom = $prise['nom_emp'] ?? '';
        $benefPrenom = $prise['prenom_emp'] ?? '';
        $lien = 'AGENT';
        $maladeAgentOuiNon = 'OUI';

        if (!empty($prise['conj_code'])) {
            $benefNom = $prise['conj_nom'] ?? '';
            $benefPrenom = '';
            $lien = 'CONJOINT(E)';
            $maladeAgentOuiNon = 'NON';
        } elseif (!empty($prise['enf_code'])) {
            $benefNom = $prise['enf_nom'] ?? '';
            $benefPrenom = '';
            $lien = 'ENFANT';
            $maladeAgentOuiNon = 'NON';
        }

        $fd = $this->getFonctionDirection((int)$prise['emp_code']);
        $fonction = $fd['fonction'] ?: 'Personnel d’Appui Administratif et Financier';
        $direction = $fd['direction'] ?: 'Direction des Affaires Administratives et Financières';

        $today = date('d/m/Y');

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(25, 20, 20);
        $pdf->SetAutoPageBreak(false);

        // En-tête
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 3.5, utf8_decode("MINISTERE DE L'ECONOMIE ET DES FINANCES\nAUTORITE DE REGULATION DES MARCHES PUBLICS\nDIRECTION DES AFFAIRES ADMINISTRATIVES ET FINANCIERES\nSERVICE DES RESSOURCES HUMAINES"));
        $pdf->Ln(8);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, utf8_decode('Antananarivo, le ' . $today), 0, 1, 'R');
        $pdf->Ln(12);

        $pdf->SetFont('Arial', 'BU', 12);
        $pdf->Cell(0, 6, utf8_decode('BULLETIN DE PRISE EN CHARGE'), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 10);
        $lineHeight = 6;

        // Renseignements Agent
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, $lineHeight, utf8_decode('RENSEIGNEMENTS CONCERNANT L’AGENT'), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(45, $lineHeight, utf8_decode('Nom et Prénoms :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lineHeight, utf8_decode(($prise['nom_emp'] ?? '') . ' ' . ($prise['prenom_emp'] ?? '')), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(45, $lineHeight, utf8_decode('Fonction :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($fonction), 0, 1);

        $pdf->Cell(45, $lineHeight, utf8_decode('Direction / Service :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($direction), 0, 1);

        $pdf->Cell(45, $lineHeight, utf8_decode('Matricule :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($prise['matricule'] ?? ''), 0, 1);

        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, $lineHeight, utf8_decode('RENSEIGNEMENTS CONCERNANT LE MALADE'), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 10);

        $pdf->Cell(60, $lineHeight, utf8_decode('Le malade est l’agent :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($maladeAgentOuiNon), 0, 1);

        $pdf->Cell(60, $lineHeight, utf8_decode('Nom et prénoms du malade :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lineHeight, utf8_decode(trim($benefNom . ' ' . $benefPrenom)), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(60, $lineHeight, utf8_decode('Lien :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($lien), 0, 1);

        $pdf->Cell(60, $lineHeight, utf8_decode('Réf. n° :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lineHeight, utf8_decode($prise['pec_num'] ?? ''), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(60, $lineHeight, utf8_decode('Centre de santé :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($prise['cen_nom'] ?? ''), 0, 1);

        $pdf->Ln(20);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 5, utf8_decode('Signature et tampon du Médecin'), 0, 1);
        $pdf->Ln(20);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, $lineHeight, utf8_decode('BULLETIN DE PRISE EN CHARGE'), 0, 1, 'C');

        $pdf->Ln(20);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 5, utf8_decode('RAZAFIMANDIMBY Danielle Tolisao'), 0, 1, 'R');

        $content = $pdf->Output('S');
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="prise_en_charge_' . $id . '.pdf"')
            ->setBody($content);
    }
}
