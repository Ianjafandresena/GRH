<?php

namespace App\Controllers\conge;
use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\CongeModel;
use App\Models\conge\SoldeCongeModel;
use App\Models\conge\DebitSoldeCngModel;
use CodeIgniter\API\ResponseTrait;
// FPDF est chargé via Composer (setasign/fpdf)

class CongeController extends ResourceController
{
    use ResponseTrait;

    // Création du congé (sans débit immédiat - attente validation)
    public function createConge()
    {
        $data = $this->request->getJSON(true);

        if (!isset(
            $data['cng_nb_jour'],
            $data['cng_debut'],
            $data['cng_fin'],
            $data['emp_code'],
            $data['typ_code'],
            $data['reg_code']
        )) {
            return $this->fail('Données obligatoires manquantes');
        }

        $emp_code = $data['emp_code'];
        $jours_demandes = $data['cng_nb_jour'];

        // Vérifier que le solde est suffisant (sans débiter)
        $soldeModel = new SoldeCongeModel();

        $reliquats = $soldeModel
            ->where('emp_code', $emp_code)
            ->where('sld_restant >', 0)
            ->orderBy('sld_anne', 'ASC')
            ->findAll();

        if (empty($reliquats)) {
            return $this->fail('Aucun solde restant pour cet employé');
        }

        // Calculer le total disponible
        $totalDisponible = 0;
        foreach ($reliquats as $reliq) {
            $totalDisponible += (float)$reliq['sld_restant'];
        }

        if ($totalDisponible < $jours_demandes) {
            return $this->fail('Solde insuffisant. Disponible: ' . $totalDisponible . ' jours, Demandé: ' . $jours_demandes . ' jours');
        }

        // Créer le congé avec status = false (en attente de validation)
        // La date de demande peut être fournie par le formulaire ou auto-générée
        $data['cng_demande'] = $data['cng_demande'] ?? date('Y-m-d H:i:s');
        $data['cng_status'] = false; // En attente de validation

        $congeModel = new CongeModel();
        $id = $congeModel->insert($data);

        if ($id === false) {
            return $this->fail('Impossible de créer le congé');
        }

        // Note: Le débit du solde se fera uniquement après validation complète
        // (CHEF → RRH → DAAF → DG) via ValidationCongeController::finalizeValidation()

        $createdConge = $congeModel->find($id);
        return $this->respondCreated([
            'conge' => $createdConge,
            'message' => 'Demande de congé créée. En attente de validation par le chef hiérarchique.',
            'next_step' => 'CHEF'
        ]);
    }

    public function getAllConges()
    {
        $congeModel = new CongeModel();
        $builder = $congeModel->select('conge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, region.reg_nom AS nom_region, type_conge.typ_appelation AS typ_appelation, type_conge.typ_ref AS typ_ref')
            ->join('employee', 'employee.emp_code = conge.emp_code', 'left')
            ->join('region', 'region.reg_code = conge.reg_code', 'left')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left');

        $typ = $_GET['typ_code'] ?? null;
        $emp = $_GET['emp_code'] ?? null;
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $lieu = $_GET['lieu'] ?? null;

        if ($typ) {
            $builder->where('conge.typ_code', $typ);
        }
        if ($emp) {
            $builder->where('conge.emp_code', $emp);
        }
        if ($start) {
            $builder->where('conge.cng_debut >=', $start);
        }
        if ($end) {
            $builder->where('conge.cng_fin <=', $end);
        }
        if ($lieu) {
            $builder->like('region.reg_nom', $lieu);
        }

        $allConges = $builder->findAll();
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($allConges);
    }

    public function getConge($id = null)
    {
        $congeModel = new CongeModel();
        $conge = $congeModel->find($id);
        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }
        return $this->respond($conge);
    }

    public function getCongeDetail($id)
    {
        $congeModel = new CongeModel();
        $detail = $congeModel->select('conge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, region.reg_nom AS nom_region, type_conge.typ_appelation AS typ_appelation, type_conge.typ_ref AS typ_ref')
            ->join('employee', 'employee.emp_code = conge.emp_code', 'left')
            ->join('region', 'region.reg_code = conge.reg_code', 'left')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
            ->where('conge.cng_code', $id)
            ->first();
        if (!$detail) return $this->failNotFound('Congé non trouvé');

        $interimModel = new \App\Models\conge\InterimCongeModel();
        $interims = $interimModel->select('interim_conge.*, e.emp_nom AS nom, e.emp_prenom AS prenom')
            ->join('employee e', 'e.emp_code = interim_conge.emp_code', 'left')
            ->where('interim_conge.cng_code', $id)
            ->findAll();

        // Décision/année exactes à défalquer: basées sur les mouvements de débit du congé
        $debitModel = new \App\Models\conge\DebitSoldeCngModel();
        $soldeModel = new \App\Models\conge\SoldeCongeModel();
        $decisionModel = new \App\Models\conge\DecisionModel();
        $mouvs = $debitModel->where('cng_code', $id)->findAll();
        $decNum = null; $sldAnne = null; $sldRestant = null;
        if (!empty($mouvs)) {
           
            $candidats = [];
            foreach ($mouvs as $mv) {
                $solde = $soldeModel->find($mv['sld_code']);
                if ($solde) {
                    $dec = $solde['dec_code'] ? $decisionModel->find($solde['dec_code']) : null;
                    $candidats[] = [
                        'anne' => $solde['sld_anne'] ?? null,
                        'restant' => $solde['sld_restant'] ?? null,
                        'dec_num' => $dec['dec_num'] ?? null,
                    ];
                }
            }
            // Choisir celui avec restant > 0, sinon le plus ancien par année
            $choix = null;
            foreach ($candidats as $c) { if (($c['restant'] ?? 0) > 0) { $choix = $c; break; } }
            if ($choix === null) {
                usort($candidats, function($a,$b){ return strcmp((string)$a['anne'], (string)$b['anne']); });
                $choix = $candidats[0] ?? null;
            }
            if ($choix) { $decNum = $choix['dec_num']; $sldAnne = $choix['anne']; $sldRestant = $choix['restant']; }
        }

        $payload = [
            'conge' => $detail,
            'interims' => $interims,
            'decision' => [ 'dec_num' => $decNum, 'sld_anne' => $sldAnne, 'sld_restant' => $sldRestant ]
        ];
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($payload);
    }

    public function exportAttestationPdf($id)
    {
        $req = service('request');
        // Réutilise la logique de getCongeDetail
        $congeModel = new CongeModel();
        $detail = $congeModel->select('conge.*, employee.emp_nom AS nom_emp, employee.emp_prenom AS prenom_emp, employee.emp_imarmp AS matricule, region.reg_nom AS nom_region, type_conge.typ_appelation AS typ_appelation, type_conge.typ_ref AS typ_ref')
            ->join('employee', 'employee.emp_code = conge.emp_code', 'left')
            ->join('region', 'region.reg_code = conge.reg_code', 'left')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
            ->where('conge.cng_code', $id)
            ->first();
        if (!$detail) return $this->failNotFound('Congé non trouvé');

        $interimModel = new \App\Models\conge\InterimCongeModel();
        $interims = $interimModel->select('interim_conge.*, e.emp_nom AS nom, e.emp_prenom AS prenom')
            ->join('employee e', 'e.emp_code = interim_conge.emp_code', 'left')
            ->where('interim_conge.cng_code', $id)
            ->findAll();

        $debitModel = new \App\Models\conge\DebitSoldeCngModel();
        $soldeModel = new \App\Models\conge\SoldeCongeModel();
        $decisionModel = new \App\Models\conge\DecisionModel();
        $mouvs = $debitModel->where('cng_code', $id)->findAll();
        $decNum = null; $sldAnne = null; $sldRestant = null;
        if (!empty($mouvs)) {
            $candidats = [];
            foreach ($mouvs as $mv) {
                $solde = $soldeModel->find($mv['sld_code']);
                if ($solde) {
                    $dec = $solde['dec_code'] ? $decisionModel->find($solde['dec_code']) : null;
                    $candidats[] = [
                        'anne' => $solde['sld_anne'] ?? null,
                        'restant' => $solde['sld_restant'] ?? null,
                        'dec_num' => $dec['dec_num'] ?? null,
                    ];
                }
            }
            $choix = null;
            foreach ($candidats as $c) { if (($c['restant'] ?? 0) > 0) { $choix = $c; break; } }
            if ($choix === null) {
                usort($candidats, function($a,$b){ return strcmp((string)$a['anne'], (string)$b['anne']); });
                $choix = $candidats[0] ?? null;
            }
            if ($choix) { $decNum = $choix['dec_num']; $sldAnne = $choix['anne']; $sldRestant = $choix['restant']; }
        }

        $interimNames = implode(', ', array_map(function($i){ return trim(($i['nom'] ?? '') . ' ' . ($i['prenom'] ?? '')); }, $interims));
        $today = date('d/m/Y');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            @page { size: A4; margin: 15mm; }
            body { font-family: Arial, sans-serif; margin: 0; }
            .sheet { width: 210mm; min-height: 297mm; }
            .content { padding: 15mm; }
            .ministry { font-size: 11px; line-height: 1.4; text-align: left; margin-bottom: 10mm; }
            .city { text-align: right; }
            .title { font-weight: bold; text-align: center; margin: 15mm 0 10mm; }
            .row { margin: 6px 0; }
            .label { display: inline-block; min-width: 180px; }
            .signature { margin-top: 25mm; display: flex; justify-content: space-between; }
        </style></head><body>
            <div class="sheet"><div class="content">
            <div class="ministry">MINISTERE DE L\'ECONOMIE ET DES FINANCES<br/>AUTORITE DE REGULATION DES MARCHES PUBLICS<br/>DIRECTION DES AFFAIRES ADMINISTRATIVES ET FINANCIERES<br/>SERVICE DES RESSOURCES HUMAINES</div>
            <div class="city">Antananarivo, le '.$today.'</div>
            <div class="title">ATTESTATION DE DEPART EN CONGÉ</div>
            <div class="row"><span class="label">Nom et prénoms :</span> '.htmlspecialchars(($detail['nom_emp'] ?? '').' '.($detail['prenom_emp'] ?? '')).'</div>
            <div class="row"><span class="label">Fonction :</span></div>
            <div class="row"><span class="label">Matricule :</span> '.htmlspecialchars($detail['matricule'] ?? '').'</div>
            <div class="row"><span class="label">Corps :</span></div>
            <div class="row"><span class="label">Grade :</span></div>
            <div class="row"><span class="label">Structure :</span></div>
            <div class="row"><span class="label">Est autorisé à s\'absenter pour une durée de :</span> '.htmlspecialchars((string)($detail['cng_nb_jour'] ?? '')).' jour(s)</div>
            <div class="row"><span class="label">À compter du :</span> '.htmlspecialchars($detail['cng_debut'] ?? '').' au '.htmlspecialchars($detail['cng_fin'] ?? '').'</div>
            <div class="row"><span class="label">Suppléant :</span> '.htmlspecialchars($interimNames).'</div>
            <div class="row"><span class="label">Motif :</span> '.htmlspecialchars($detail['typ_appelation'] ?? '').'</div>
            <div class="row"><span class="label">Lieu de jouissance :</span> '.htmlspecialchars($detail['nom_region'] ?? '').'</div>
            <div class="row"><span class="label">Décision(s) à défalquer :</span> '.htmlspecialchars($decNum ?? '').' au titre de l\'année '.htmlspecialchars((string)($sldAnne ?? '')).' ('.htmlspecialchars((string)($sldRestant ?? '')).' jours restants)</div>
            <div class="signature"><div>L\'intéressé</div><div>Le Chef hiérarchique</div><div>Le Responsable du personnel</div></div>
            </div></div>
        </body></html>';

        // Génération directe PDF avec FPDF - Format officiel
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(25, 20, 20);
        $pdf->SetAutoPageBreak(false);
        
        // En-tête ministère
        $pdf->SetFont('Arial','',8);
        $pdf->MultiCell(0, 3.5, utf8_decode("MINISTERE DE L'ECONOMIE ET DES FINANCES\nAUTORITE DE REGULATION DES MARCHES PUBLICS\nDIRECTION DES AFFAIRES ADMINISTRATIVES ET FINANCIERES\nSERVICE DES RESSOURCES HUMAINES"));
        $pdf->Ln(8);
        
        // Ville/date à droite
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(0, 5, utf8_decode('Antananarivo, le ' . $today), 0, 1, 'R');
        $pdf->Ln(12);
        
        // Titre centré et souligné
        $pdf->SetFont('Arial','BU',12);
        $pdf->Cell(0, 6, utf8_decode('ATTESTATION DE DEPART EN CONGE'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Corps du document
        $pdf->SetFont('Arial','',10);
        $lineHeight = 5;
        
        // Nom et prénoms (valeur en gras)
        $pdf->Cell(50, $lineHeight, utf8_decode('Nom et prenoms :'), 0, 0);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, $lineHeight, utf8_decode(strtoupper(($detail['nom_emp'] ?? '') . ' ' . ($detail['prenom_emp'] ?? ''))), 0, 1);
        
        $pdf->Ln(1);
        
        // Fonction (vide)
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(0, $lineHeight, utf8_decode('Fonction :'), 0, 1);
        
        // Matricule
        $pdf->Cell(50, $lineHeight, utf8_decode('Matricule :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($detail['matricule'] ?? ''), 0, 1);
        
        // Corps (vide)
        $pdf->Cell(0, $lineHeight, utf8_decode('Corps :'), 0, 1);
        
        // Grade (vide)
        $pdf->Cell(0, $lineHeight, utf8_decode('Grade :'), 0, 1);
        
        // Structure (vide)
        $pdf->Cell(0, $lineHeight, utf8_decode('Structure :'), 0, 1);
        
        $pdf->Ln(0.5);
        
        // Est autorisé... (en gras pour le nombre)
        $nbJours = $detail['cng_nb_jour'] ?? 0;
        // Formatter le nombre : enlever .0 si entier
        $nbJoursFormatted = $nbJours + 0;
        
        $pdf->Write($lineHeight, utf8_decode("Est autorise a s'absenter pour une duree de "));
        $pdf->SetFont('Arial','B',10);
        $pdf->Write($lineHeight, utf8_decode($nbJoursFormatted . ' jour(s)'));
        $pdf->Ln();
        
        // À compter du
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(50, $lineHeight, utf8_decode('A compter du :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode(($detail['cng_debut'] ?? '') . ' au ' . ($detail['cng_fin'] ?? '')), 0, 1);
        
        // Suppléant
        $pdf->Cell(50, $lineHeight, utf8_decode('Suppleant :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($interimNames), 0, 1);
        
        // Motif
        $pdf->Cell(50, $lineHeight, utf8_decode('Motif :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($detail['typ_appelation'] ?? ''), 0, 1);
        
        // Lieu de jouissance
        $pdf->Cell(50, $lineHeight, utf8_decode('Lieu de jouissance :'), 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode($detail['nom_region'] ?? ''), 0, 1);
        
        $pdf->Ln(1);
        
        // Décision(s) à défalquer
        $pdf->Cell(0, $lineHeight, utf8_decode('Decision(s) a defalquer :'), 0, 1);
        $pdf->Cell(8, $lineHeight, '', 0, 0);
        $pdf->Cell(0, $lineHeight, utf8_decode('- ' . ($decNum ?? '') . ' au titre de l\'annee ' . (string)($sldAnne ?? '') . ' (' . (string)($sldRestant ?? '') . ' jours restants)'), 0, 1);
        
        // Section signatures - mieux centrée
        $pdf->Ln(35);
        $pdf->SetFont('Arial','',9.5);
        
        // Commencer plus à gauche pour un meilleur centrage
        $pdf->SetX(15);
        $colWidth = 60;
        $pdf->Cell($colWidth, 5, utf8_decode("L'interesse"), 0, 0, 'C');
        $pdf->Cell($colWidth, 5, utf8_decode('Le Chef hierarchique'), 0, 0, 'C');
        $pdf->Cell($colWidth, 5, utf8_decode('Le Responsable du personnel'), 0, 1, 'C');

        $content = $pdf->Output('S');
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="attestation_conge_'.$id.'.pdf"')
            ->setBody($content);
    }

    public function exportCsv()
    {
        $congeModel = new CongeModel();
        $rows = $congeModel->select('conge.cng_code, conge.cng_nb_jour, conge.cng_debut, conge.cng_fin, conge.emp_code, conge.typ_code, conge.reg_code')
            ->findAll();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['cng_code', 'cng_nb_jour', 'cng_debut', 'cng_fin', 'emp_code', 'typ_code', 'reg_code']);
        foreach ($rows as $r) {
            fputcsv($csv, [
                $r['cng_code'],
                $r['cng_nb_jour'],
                $r['cng_debut'],
                $r['cng_fin'],
                $r['emp_code'],
                $r['typ_code'],
                $r['reg_code'],
            ]);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $bom = "\xEF\xBB\xBF";
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="conges.csv"')
            ->setBody($bom . $content);
    }

    public function importCsv()
    {
        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            return $this->failValidationErrors('Fichier CSV manquant ou invalide');
        }
        $path = $file->getTempName();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return $this->fail('Impossible de lire le fichier CSV');
        }
        $header = fgetcsv($handle);
        $required = ['cng_nb_jour', 'cng_debut', 'cng_fin', 'emp_code', 'typ_code', 'reg_code'];
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            $valid = true;
            foreach ($required as $key) {
                if (!isset($row[$key]) || $row[$key] === '') {
                    $valid = false;
                    break;
                }
            }
            if ($valid) {
                $rows[] = [
                    'cng_nb_jour' => (float)$row['cng_nb_jour'],
                    'cng_debut' => $row['cng_debut'],
                    'cng_fin' => $row['cng_fin'],
                    'emp_code' => (int)$row['emp_code'],
                    'typ_code' => (int)$row['typ_code'],
                    'reg_code' => (int)$row['reg_code'],
                    'cng_demande' => date('Y-m-d H:i:s'),
                ];
            }
        }
        fclose($handle);
        if (empty($rows)) {
            return $this->failValidationErrors('Aucune ligne valide trouvée');
        }
        $model = new CongeModel();
        $model->insertBatch($rows);
        return $this->respondCreated(['imported' => count($rows)]);
    }


    public function exportExcel()
    {
        $congeModel = new CongeModel();
        $rows = $congeModel->select('conge.cng_code, conge.cng_nb_jour, conge.cng_debut, conge.cng_fin, employee.nom, employee.prenom, type_conge.typ_appelation, type_conge.typ_ref, region.reg_nom')
            ->join('employee', 'employee.emp_code = conge.emp_code')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code')
            ->join('region', 'region.reg_code = conge.reg_code')
            ->findAll();

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><table border="1"><thead><tr>' .
            '<th>Employé</th><th>Début</th><th>Fin</th><th>Nb jours</th><th>Type</th><th>Réf</th><th>Région</th>' .
            '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>' .
                '<td>' . htmlspecialchars($r['nom'] . ' ' . $r['prenom']) . '</td>' .
                '<td>' . htmlspecialchars($r['cng_debut']) . '</td>' .
                '<td>' . htmlspecialchars($r['cng_fin']) . '</td>' .
                '<td>' . htmlspecialchars((string)$r['cng_nb_jour']) . '</td>' .
                '<td>' . htmlspecialchars($r['typ_appelation']) . '</td>' .
                '<td>' . htmlspecialchars($r['typ_ref']) . '</td>' .
                '<td>' . htmlspecialchars($r['reg_nom']) . '</td>' .
                '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        $bom = "\xEF\xBB\xBF";
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="conges.xls"')
            ->setBody($bom . $html);
    }
}
