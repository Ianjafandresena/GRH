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
        $allConges = $congeModel->findAll();
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
}
