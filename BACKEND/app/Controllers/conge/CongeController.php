<?php

namespace App\Controllers\conge;
use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\CongeModel;
use App\Models\conge\SoldeCongeModel;
use App\Models\conge\DebitSoldeCngModel;
use CodeIgniter\API\ResponseTrait;

class CongeController extends ResourceController
{
    use ResponseTrait;

    // Création du congé
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
        $jours_a_debiter = $data['cng_nb_jour'];

        $soldeModel = new SoldeCongeModel();
        $debitModel = new DebitSoldeCngModel();


        $reliquats = $soldeModel
            ->where('emp_code', $emp_code)
            ->where('sld_restant >', 0)
            ->orderBy('sld_anne', 'ASC')
            ->findAll();

        if(empty($reliquats)) {
            return $this->fail('Aucun solde restant pour cet employé');
        }

        // Calcul du débit multi-reliquat
        $reste = $jours_a_debiter;
        $mouvements = [];
        foreach ($reliquats as $reliq) {
            if ($reste <= 0) break;
            $debit = min($reste, $reliq['sld_restant']);
            $soldeModel->update($reliq['sld_code'], ['sld_restant' => $reliq['sld_restant'] - $debit]);
            $mouvements[] = [
                'emp_code' => $emp_code,
                'sld_code' => $reliq['sld_code'],
                'deb_jr'   => $debit,
                'deb_date' => date('Y-m-d')
            ];
            $reste -= $debit;
        }

        if ($reste > 0) {
            return $this->fail('Solde insuffisant sur tous les reliquats');
        }

        $data['cng_demande'] = date('Y-m-d H:i:s');
        $congeModel = new CongeModel();
        $id = $congeModel->insert($data);

        if ($id === false) {
            return $this->fail('Impossible de créer le congé');
        }

        
        foreach ($mouvements as $mouvement) {
            $mouvement['cng_code'] = $id;
            $debitModel->insert($mouvement);
        }

        $createdConge = $congeModel->find($id);
        return $this->respondCreated($createdConge);
    }

    public function getAllConges()
    {
        $congeModel = new CongeModel();
        $builder = $congeModel->select('conge.*, employee.nom AS nom_emp, employee.prenom AS prenom_emp, region.reg_nom AS nom_region, type_conge.typ_appelation AS typ_appelation, type_conge.typ_ref AS typ_ref')
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
