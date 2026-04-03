<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class StatutArmpController extends ResourceController
{
    protected $statutArmpModel;
    protected $format = 'json';

    public function __construct()
    {
        $this->statutArmpModel = model('App\Models\referentiel\StatutArmpModel');
    }

    /**
     * Lister tous les statuts ARMP
     */
    public function index()
    {
        try {
            $statuts = $this->statutArmpModel
                ->orderBy('stt_armp_statut', 'ASC')
                ->findAll();

            return $this->respond([
                'status' => 'success',
                'count' => count($statuts),
                'data' => $statuts
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Récupérer un statut ARMP par son ID
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID du statut ARMP manquant');
        }

        $statut = $this->statutArmpModel->find($id);
        
        if (!$statut) {
            return $this->failNotFound('Statut ARMP non trouvé');
        }

        return $this->respond([
            'status' => 'success',
            'data' => $statut
        ]);
    }

    /**
     * Créer un nouveau statut ARMP
     */
    public function create()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!$this->statutArmpModel->insert($data)) {
                return $this->failValidationErrors($this->statutArmpModel->errors());
            }

            $id = $this->statutArmpModel->getInsertID();
            $statut = $this->statutArmpModel->find($id);

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Statut ARMP créé avec succès',
                'data' => $statut
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Mettre à jour un statut ARMP
     */
    public function update($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID du statut ARMP manquant');
        }

        $statut = $this->statutArmpModel->find($id);
        if (!$statut) {
            return $this->failNotFound('Statut ARMP non trouvé');
        }

        try {
            $data = $this->request->getJSON(true);

            if (!$this->statutArmpModel->update($id, $data)) {
                return $this->failValidationErrors($this->statutArmpModel->errors());
            }

            $statut = $this->statutArmpModel->find($id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Statut ARMP mis à jour avec succès',
                'data' => $statut
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Supprimer un statut ARMP
     */
    public function delete($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID du statut ARMP manquant');
        }

        $statut = $this->statutArmpModel->find($id);
        if (!$statut) {
            return $this->failNotFound('Statut ARMP non trouvé');
        }

        try {
            $this->statutArmpModel->delete($id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Statut ARMP supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}
