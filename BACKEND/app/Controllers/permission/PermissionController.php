<?php

namespace App\Controllers\permission;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\permission\PermissionModel;
use App\Models\permission\SoldePermissionModel;
use App\Models\permission\DebitSoldePrmModel;

class PermissionController extends ResourceController
{
    use ResponseTrait;

    public function createPermission()
    {
        $payload = $this->request->getJSON(true) ?? [];
        if (!isset($payload['emp_code'], $payload['prm_debut'], $payload['prm_fin'])) {
            return $this->fail('Données obligatoires manquantes (emp_code, prm_debut, prm_fin)');
        }
        
        $d1 = strtotime($payload['prm_debut']);
        $d2 = strtotime($payload['prm_fin']);
        
        if ($d1 === false || $d2 === false || $d2 < $d1) {
            return $this->failValidationErrors('Dates invalides ou fin avant début');
        }

        // Calcul durée en heures
        $start_date = date('Y-m-d', $d1);
        $end_date = date('Y-m-d', $d2);
        
        // Moments (Matin/Après-midi) avec valeurs par défaut basées sur l'heure si absent
        $dMom = $payload['prm_moment_debut'] ?? (date('H', $d1) < 12 ? 'matin' : 'apres_midi');
        $fMom = $payload['prm_moment_fin'] ?? (date('H', $d2) < 14 ? 'matin' : 'apres_midi');

        $dateTimeStart = new \DateTime($start_date);
        $dateTimeEnd = new \DateTime($end_date);
        $diff = $dateTimeStart->diff($dateTimeEnd);
        $diffDays = (int)$diff->format('%a');

        $totalH = 0;
        if ($diffDays === 0) {
            // Même jour
            if ($dMom === 'matin' && $fMom === 'matin') $totalH = 4;
            else if ($dMom === 'matin' && $fMom === 'apres_midi') $totalH = 8;
            else if ($dMom === 'apres_midi' && $fMom === 'apres_midi') $totalH = 4;
            else $totalH = 4; // Par défaut
        } else {
            // Jours différents
            $totalH += ($dMom === 'matin') ? 8 : 4; 
            $totalH += ($fMom === 'matin') ? 4 : 8; 
            if ($diffDays > 1) {
                $totalH += ($diffDays - 1) * 8;
            }
        }

        if ($totalH > 8) {
            return $this->fail('La durée de la permission ne peut pas dépasser une journée (8 heures travaillées)');
        }

        $prm_duree = (float)$totalH;
        $data = [
            'emp_code'   => (int)$payload['emp_code'],
            'prm_duree'  => $prm_duree,
            'prm_date'   => $start_date, // Stocker la date brute (DATE)
            'prm_debut'  => date('Y-m-d H:i:s', $d1),
            'prm_fin'    => date('Y-m-d H:i:s', $d2),
            'prm_status' => false
        ];
        
        $model = new PermissionModel();
        try {
            $id = $model->insert($data);
            if ($id === false || $id === null) {
                $errs = $model->errors();
                return $this->fail(!empty($errs) ? implode(', ', $errs) : 'Erreur SQL lors de l\'insertion', 500);
            }

            // GÉRER L'INTÉRIMAIRE SI FOURNI
            if (!empty($payload['interim_emp_code'])) {
                $db = \Config\Database::connect();
                $db->table('interim_permission')->insert([
                    'emp_code' => (int)$payload['interim_emp_code'],
                    'prm_code' => $id,
                    'int_prm_debut' => date('Y-m-d H:i:s', $d1),
                    'int_prm_fin' => date('Y-m-d H:i:s', $d2)
                ]);
            }

            return $this->respondCreated($model->find($id));
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    public function validatePermission($id = null)
    {
        $model = new PermissionModel();
        $prm = $model->find($id);
        if (!$prm) return $this->failNotFound('Permission non trouvée');
        
        // Correction : PostgreSQL retourne 't'/'f' (string). PHP considère 'f' comme true (chaîne non vide).
        $isValidated = ($prm['prm_status'] === 't' || $prm['prm_status'] === true || $prm['prm_status'] === 1 || $prm['prm_status'] === '1');
        if ($isValidated) {
            return $this->fail('Cette permission est déjà validée', 400);
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            // 1. Marquer comme validée
            $model->update($id, ['prm_status' => true]);

            // 2. Débiter le solde (8h = 1 jour)
            $totalH = (float)$prm['prm_duree'];
            $debitDays = $totalH / 8.0;

            if ($totalH > 0) {
                $soldeModel = new SoldePermissionModel();
                $debitModel = new DebitSoldePrmModel();
                $reste = $debitDays;
                $reliquats = $soldeModel
                    ->where('emp_code', (int)$prm['emp_code'])
                    ->where('sld_prm_dispo >', 0)
                    ->orderBy('sld_prm_anne', 'ASC')
                    ->findAll();
                    
                if (empty($reliquats)) {
                    throw new \Exception('Aucun solde disponible pour cet employé');
                }
                
                foreach ($reliquats as $reliq) {
                    if ($reste <= 0) break;
                    $debit = min($reste, (float)$reliq['sld_prm_dispo']);
                    $soldeModel->update($reliq['sld_prm_code'], [
                        'sld_prm_dispo' => (float)$reliq['sld_prm_dispo'] - $debit
                    ]);
                    
                    $debitModel->insert([
                        'emp_code' => (int)$prm['emp_code'],
                        'prm_code' => $id,
                        'sld_prm_code' => (int)$reliq['sld_prm_code'],
                        'deb_jr' => $debit,
                        'deb_date' => date('Y-m-d H:i:s')
                    ]);
                    
                    $reste -= $debit;
                }
                
                if ($reste > 0) {
                    throw new \Exception('Solde insuffisant pour valider cette permission (Reste: ' . $reste . 'j)');
                }
            }

            $db->transCommit();
            return $this->respond(['message' => 'Permission validée et débitée avec succès']);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->fail($e->getMessage(), 500);
        }
    }

    public function rejectPermission($id = null)
    {
        $model = new PermissionModel();
        $prm = $model->find($id);
        if (!$prm) return $this->failNotFound('Permission non trouvée');

        $isValidated = ($prm['prm_status'] === 't' || $prm['prm_status'] === true || $prm['prm_status'] === 1 || $prm['prm_status'] === '1');
        if ($isValidated) {
            return $this->fail('Cette permission est déjà validée et ne peut pas être refusée', 400);
        }

        $payload = $this->request->getJSON(true) ?? [];
        $motif = trim($payload['motif'] ?? '');
        if (empty($motif)) {
            return $this->fail('Le motif du refus est obligatoire', 400);
        }

        $model->update($id, [
            'prm_status'       => false,
            'prm_motif_rejet'  => $motif
        ]);

        return $this->respond(['message' => 'Permission refusée avec succès']);
    }

    public function exportPermissionPdf($id = null)
    {
        $model = new PermissionModel();
        $prm = $model->select('permission.*, employe.*, type_conge.typ_appelation')
            ->join('employe', 'employe.emp_code = permission.emp_code', 'left')
            ->join('type_conge', 'type_conge.typ_code = 2', 'left') // Type 2 = Permission (souvent)
            ->find($id);

        if (!$prm) return $this->failNotFound('Permission non trouvée');

        // Création du PDF avec FPDF (Autoloadé via Composer)
        $pdf = new \FPDF('P','mm','A4');

        // PAGE 1 : DEMANDE
        $pdf->AddPage();
        $this->generateHeader($pdf);
        
        $pdf->SetY(60);
        $pdf->SetFont('Arial','BU',12);
        $pdf->Cell(0, 10, utf8_decode("DEMANDE D'AUTORISATION D'ABSENCE"), 0, 1, 'C');
        $pdf->Ln(10);

        $this->renderPrmContent($pdf, $prm, "DEMANDE");

        // PAGE 2 : ATTESTATION
        $pdf->AddPage();
        $this->generateHeader($pdf);
        
        $pdf->SetY(60);
        $pdf->SetFont('Arial','BU',12);
        $pdf->Cell(0, 10, utf8_decode("ATTESTATION D'AUTORISATION D'ABSENCE"), 0, 1, 'C');
        $pdf->Ln(10);

        $this->renderPrmContent($pdf, $prm, "ATTESTATION");

        $content = $pdf->Output('S');
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="permission_'.$id.'.pdf"')
            ->setBody($content);
    }

    private function generateHeader($pdf)
    {
        // Logo ARMP à gauche
        if (file_exists(FCPATH . 'logo.png')) {
            $pdf->Image(FCPATH . 'logo.png', 10, 10, 20);
        }

        // En-tête type ARMP / Ministère
        $pdf->SetY(10);
        $pdf->SetX(35);
        $pdf->SetFont('Arial','B',8);
        $pdf->Cell(80, 4, utf8_decode('MINISTERE DE L\'ECONOMIE ET DES FINANCES'), 0, 0, 'L');
        $pdf->Cell(0, 4, utf8_decode('Antananarivo, le ' . date('d/m/Y')), 0, 1, 'R');
        $pdf->SetX(35);
        $pdf->Cell(80, 4, utf8_decode('AUTORITE DE REGULATION DES MARCHES PUBLICS'), 0, 1, 'L');
        $pdf->SetX(35);
        $pdf->SetFont('Arial','',7);
        $pdf->Cell(80, 4, utf8_decode('DIRECTION DES AFFAIRES ADMINISTRATIVES ET FINANCIERES'), 0, 1, 'L');
        $pdf->SetX(35);
        $pdf->Cell(80, 4, utf8_decode('SERVICE DES RESSOURCES HUMAINES'), 0, 1, 'L');
    }

    private function renderPrmContent($pdf, $prm, $mode)
    {
        $lineH = 6;
        $pdf->SetFont('Arial','',10);
        
        // Numéro de référence comme sur l'image
        $ref = 'n°' . ($prm['emp_matricule'] ?? '00000') . '-MEF/SG/ARMP/DAAF/SRH';
        $pdf->Cell(0, $lineH, utf8_decode($ref), 0, 1, 'L');
        $pdf->Ln(4);

        $this->addRow($pdf, 'Nom et prenoms :', strtoupper(($prm['emp_nom'] ?? '') . ' ' . ($prm['emp_prenom'] ?? '')), true);
        $this->addRow($pdf, 'Fonction :', $prm['emp_fonction'] ?? 'Chauffeur');
        $this->addRow($pdf, 'Matricule :', $prm['emp_matricule'] ?? '');
        $this->addRow($pdf, 'Corps :', $prm['emp_corps'] ?? '');
        $this->addRow($pdf, 'Grade :', $prm['emp_grade'] ?? '');
        $this->addRow($pdf, 'Structure :', 'MEF/ARMP/DAAF/SAF/PARAF1');

        $pdf->Ln(2);
        
        $nbJours = (float)$prm['prm_duree'] / 8.0;
        $pdf->SetFont('Arial','',10);
        $pdf->Write($lineH, utf8_decode("Est autorise a s'absenter pour une duree de "));
        $pdf->SetFont('Arial','B',10);
        $pdf->Write($lineH, utf8_decode($nbJours . ' jour(s)'));
        $pdf->Ln($lineH + 2);
        
        $this->addRow($pdf, 'A compter du :', date('d/m/Y', strtotime($prm['prm_debut'])) . ' au ' . date('d/m/Y', strtotime($prm['prm_fin'])));
        $this->addRow($pdf, 'Suppleant :', ' ');
        $this->addRow($pdf, 'Motif :', 'Convenances personnelles (Permission exceptionnelle)');
        $this->addRow($pdf, 'Lieu de jouissance :', 'Antananarivo');

        // Page footer (Signatures)
        $pdf->Ln(30);
        $pdf->SetFont('Arial','',9);
        if ($mode === "DEMANDE") {
             $pdf->Cell(95, 5, utf8_decode("L'interesse"), 0, 0, 'L');
             $pdf->Cell(95, 5, utf8_decode('Le Chef hierarchique'), 0, 1, 'R');
        } else {
             $pdf->Cell(63, 5, utf8_decode("L'interesse"), 0, 0, 'L');
             $pdf->Cell(63, 5, utf8_decode('Le Chef hierarchique'), 0, 0, 'C');
             $pdf->Cell(0, 5, utf8_decode('Le Responsable du personnel'), 0, 1, 'R');
        }
    }

    private function addRow($pdf, $label, $value, $boldValue = false)
    {
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(45, 6, utf8_decode($label), 0, 0);
        if ($boldValue) $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 6, utf8_decode($value), 0, 1);
        $pdf->SetFont('Arial','',10);
    }
    public function getAllPermissions()
    {
        $model = new PermissionModel();
        $builder = $model->select('permission.*, employe.emp_nom AS nom_emp, employe.emp_prenom AS prenom_emp')
            ->join('employe', 'employe.emp_code = permission.emp_code', 'left');
        
        // Filtres dates si présents
        if ($start = $this->request->getVar('start')) $builder->where('permission.prm_date >=', $start);
        if ($end = $this->request->getVar('end')) $builder->where('permission.prm_date <=', $end);
        
        $rows = $builder->findAll();
        return $this->respond($rows);
    }

    public function getPermission($id = null)
    {
        $model = new PermissionModel();
        $row = $model->find($id);
        if (!$row) return $this->failNotFound('Permission non trouvée');
        return $this->respond($row);
    }
}
