<?php

namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\conge\InterruptionModel;
use App\Models\conge\CongeModel;
use App\Models\conge\SoldeCongeModel;
use App\Models\conge\DebitSoldeCngModel;
use App\Models\conge\DecisionModel;

class InterruptionController extends ResourceController
{
    use ResponseTrait;

    /**
     * Get interruption for a specific leave
     */
    public function getByConge($cngCode = null)
    {
        if (!$cngCode) {
            return $this->fail('cng_code requis');
        }

        $model = new InterruptionModel();
        $interruption = $model->where('cng_code', $cngCode)->first();

        if (!$interruption) {
            return $this->respond(null);
        }

        return $this->respond($interruption);
    }

    /**
     * Get leave details for interruption form (active leaves only)
     */
    public function getActiveLeavesForEmployee($empCode = null)
    {
        if (!$empCode) {
            return $this->fail('emp_code requis');
        }

        $congeModel = new CongeModel();
        $today = date('Y-m-d');

        // Get leaves that are currently active (started but not ended)
        $activeLeaves = $congeModel
            ->select('conge.*, type_conge.typ_appelation')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
            ->where('conge.emp_code', $empCode)
            ->where('conge.cng_debut <=', $today)
            ->where('conge.cng_fin >=', $today)
            ->findAll();

        // Filter out leaves that already have an interruption
        $interruptionModel = new InterruptionModel();
        $result = [];
        foreach ($activeLeaves as $leave) {
            $hasInterruption = $interruptionModel->where('cng_code', $leave['cng_code'])->first();
            if (!$hasInterruption) {
                $result[] = $leave;
            }
        }

        return $this->respond($result);
    }

    /**
     * Create an interruption for a leave
     * This will:
     * 1. Validate the interruption date is within the leave period
     * 2. Calculate remaining days to restore
     * 3. Restore days to the original balances (reverse order of debit)
     * 4. Update the leave's actual days used
     * 5. Create the interruption record
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['cng_code'], $data['interup_date'])) {
            return $this->fail('Données obligatoires manquantes (cng_code, interup_date)');
        }

        $cngCode = (int)$data['cng_code'];
        $interupDate = $data['interup_date'];
        $motif = $data['interup_motif'] ?? 'Nécessité de service';

        // Get the leave
        $congeModel = new CongeModel();
        $conge = $congeModel->find($cngCode);

        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }

        // Check if already interrupted
        $interruptionModel = new InterruptionModel();
        $existing = $interruptionModel->where('cng_code', $cngCode)->first();
        if ($existing) {
            return $this->fail('Ce congé a déjà été interrompu');
        }

        // Validate interruption date is within leave period
        $debut = new \DateTime($conge['cng_debut']);
        $fin = new \DateTime($conge['cng_fin']);
        $interupDateObj = new \DateTime($interupDate);

        if ($interupDateObj < $debut || $interupDateObj > $fin) {
            return $this->fail('La date d\'interruption doit être comprise entre ' . $conge['cng_debut'] . ' et ' . $conge['cng_fin']);
        }

        // Calculate days actually used and days to restore
        // Days used = from debut to interup_date (inclusive of debut, exclusive of interup_date)
        $daysUsed = $debut->diff($interupDateObj)->days;
        if ($daysUsed < 1) {
            $daysUsed = 1; // Minimum 1 day used if interrupted on first day
        }
        
        $originalDays = (float)$conge['cng_nb_jour'];
        $daysToRestore = $originalDays - $daysUsed;

        if ($daysToRestore <= 0) {
            return $this->fail('Aucun jour à restituer. Le congé est terminé ou presque.');
        }

        // Get all debits for this leave, ordered by debit date DESC (newest first for reverse restoration)
        $debitModel = new DebitSoldeCngModel();
        $soldeModel = new SoldeCongeModel();
        
        $debits = $debitModel
            ->where('cng_code', $cngCode)
            ->orderBy('deb_code', 'DESC') // Restore to newest debited first
            ->findAll();

        if (empty($debits)) {
            return $this->fail('Aucun mouvement de débit trouvé pour ce congé');
        }

        // Restore days to original balances (in reverse order)
        $remainingToRestore = $daysToRestore;
        $restorations = [];

        foreach ($debits as $debit) {
            if ($remainingToRestore <= 0) break;

            $solde = $soldeModel->find($debit['sld_code']);
            if (!$solde) continue;

            // How much was debited from this balance for this leave?
            $debitedAmount = (float)$debit['deb_jr'];

            // How much can we restore? (up to what was debited)
            $toRestore = min($remainingToRestore, $debitedAmount);

            // Update the balance
            $newRestant = (float)$solde['sld_restant'] + $toRestore;
            $soldeModel->update($debit['sld_code'], [
                'sld_restant' => $newRestant,
                'sld_maj' => date('Y-m-d H:i:s')
            ]);

            $restorations[] = [
                'sld_code' => $debit['sld_code'],
                'restored' => $toRestore,
                'new_restant' => $newRestant
            ];

            $remainingToRestore -= $toRestore;
        }

        // Update the leave's day count to reflect actual days used
        $congeModel->update($cngCode, [
            'cng_nb_jour' => $daysUsed,
            'cng_fin' => $interupDate // Update end date to interruption date
        ]);

        // Create the interruption record
        $interruptionData = [
            'cng_code' => $cngCode,
            'interup_date' => $interupDate,
            'interup_motif' => $motif,
            'interup_restant' => $daysToRestore
        ];

        $interruptionId = $interruptionModel->insert($interruptionData);

        if ($interruptionId === false) {
            return $this->fail('Impossible de créer l\'interruption');
        }

        return $this->respondCreated([
            'interruption' => $interruptionModel->find($interruptionId),
            'days_used' => $daysUsed,
            'days_restored' => $daysToRestore,
            'restorations' => $restorations,
            'message' => $daysToRestore . ' jour(s) restitué(s) au(x) solde(s)'
        ]);
    }

    /**
     * Calculate days to restore (preview before actual interruption)
     */
    public function previewRestoration()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['cng_code'], $data['interup_date'])) {
            return $this->fail('Données obligatoires manquantes');
        }

        $cngCode = (int)$data['cng_code'];
        $interupDate = $data['interup_date'];

        $congeModel = new CongeModel();
        $conge = $congeModel->find($cngCode);

        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }

        $debut = new \DateTime($conge['cng_debut']);
        $interupDateObj = new \DateTime($interupDate);

        $daysUsed = $debut->diff($interupDateObj)->days;
        if ($daysUsed < 1) $daysUsed = 1;

        $originalDays = (float)$conge['cng_nb_jour'];
        $daysToRestore = $originalDays - $daysUsed;

        // Get balances that will receive restoration
        $debitModel = new DebitSoldeCngModel();
        $soldeModel = new SoldeCongeModel();
        $decisionModel = new DecisionModel();

        $debits = $debitModel
            ->where('cng_code', $cngCode)
            ->orderBy('deb_code', 'DESC')
            ->findAll();

        $preview = [];
        $remaining = $daysToRestore;

        foreach ($debits as $debit) {
            if ($remaining <= 0) break;

            $solde = $soldeModel->find($debit['sld_code']);
            if (!$solde) continue;

            $decision = $decisionModel->find($solde['dec_code']);
            $toRestore = min($remaining, (float)$debit['deb_jr']);

            $preview[] = [
                'decision' => $decision['dec_num'] ?? 'N/A',
                'annee' => $solde['sld_anne'] ?? 'N/A',
                'jours_a_restituer' => $toRestore,
                'solde_actuel' => $solde['sld_restant'],
                'solde_apres' => (float)$solde['sld_restant'] + $toRestore
            ];

            $remaining -= $toRestore;
        }

        return $this->respond([
            'jours_utilises' => $daysUsed,
            'jours_a_restituer' => $daysToRestore,
            'details_restitution' => $preview
        ]);
    }

    /**
     * Generate PDF for interruption attestation
     */
    public function generateAttestation($cngCode = null)
    {
        if (!$cngCode) {
            return $this->fail('cng_code requis');
        }

        $interruptionModel = new InterruptionModel();
        $interruption = $interruptionModel->where('cng_code', $cngCode)->first();

        if (!$interruption) {
            return $this->failNotFound('Interruption non trouvée');
        }

        $congeModel = new CongeModel();
        $conge = $congeModel
            ->select('conge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule')
            ->join('employee', 'employee.emp_code = conge.emp_code', 'left')
            ->where('conge.cng_code', $cngCode)
            ->first();

        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }

        // Get restoration details
        $debitModel = new DebitSoldeCngModel();
        $soldeModel = new SoldeCongeModel();
        $decisionModel = new DecisionModel();

        $debits = $debitModel->where('cng_code', $cngCode)->findAll();
        $decisionInfo = '';
        if (!empty($debits)) {
            $solde = $soldeModel->find($debits[0]['sld_code']);
            if ($solde) {
                $decision = $decisionModel->find($solde['dec_code']);
                $decisionInfo = ($decision['dec_num'] ?? '') . ' - Année ' . ($solde['sld_anne'] ?? '');
            }
        }

        $today = date('d/m/Y');

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(25, 20, 20);
        $pdf->SetAutoPageBreak(false);

        // Header
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 3.5, utf8_decode("MINISTERE DE L'ECONOMIE ET DES FINANCES\nAUTORITE DE REGULATION DES MARCHES PUBLICS\nDIRECTION DES AFFAIRES ADMINISTRATIVES ET FINANCIERES\nSERVICE DES RESSOURCES HUMAINES"));
        $pdf->Ln(8);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, utf8_decode('Antananarivo, le ' . $today), 0, 1, 'R');
        $pdf->Ln(12);

        // Title
        $pdf->SetFont('Arial', 'BU', 12);
        $pdf->Cell(0, 6, utf8_decode('ATTESTATION D\'INTERRUPTION DE CONGE'), 0, 1, 'C');
        $pdf->Ln(10);

        // Content
        $pdf->SetFont('Arial', '', 10);
        $lineHeight = 6;

        $pdf->Cell(50, $lineHeight, utf8_decode('Agent :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lineHeight, utf8_decode(strtoupper(($conge['nom_emp'] ?? '') . ' ' . ($conge['prenom_emp'] ?? ''))), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, $lineHeight, utf8_decode('Matricule :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($conge['matricule'] ?? ''), 0, 1);

        $pdf->Ln(4);
        $pdf->Cell(0, $lineHeight, utf8_decode('Le congé initialement prévu du ' . $conge['cng_debut'] . ' au ' . $conge['cng_fin']), 0, 1);
        $pdf->Cell(0, $lineHeight, utf8_decode('a été interrompu le ' . $interruption['interup_date']), 0, 1);

        $pdf->Ln(4);
        $pdf->Cell(50, $lineHeight, utf8_decode('Motif :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($interruption['interup_motif'] ?? 'Nécessité de service'), 0, 1);

        $pdf->Ln(4);
        $pdf->Cell(50, $lineHeight, utf8_decode('Jours restitués :'), 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, $lineHeight, utf8_decode($interruption['interup_restant'] . ' jour(s)'), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, $lineHeight, utf8_decode('Décision concernée :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($decisionInfo), 0, 1);

        // Signatures
        $pdf->Ln(30);
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->SetX(15);
        $colWidth = 60;
        $pdf->Cell($colWidth, 5, utf8_decode("L'intéressé"), 0, 0, 'C');
        $pdf->Cell($colWidth, 5, utf8_decode('Le Chef hiérarchique'), 0, 0, 'C');
        $pdf->Cell($colWidth, 5, utf8_decode('Le Responsable du personnel'), 0, 1, 'C');

        $content = $pdf->Output('S');
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="interruption_conge_' . $cngCode . '.pdf"')
            ->setBody($content);
    }
}
