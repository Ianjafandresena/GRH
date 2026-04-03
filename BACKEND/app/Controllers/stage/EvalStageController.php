<?php
namespace App\Controllers\stage;

use CodeIgniter\RESTful\ResourceController;
use App\Models\stage\EvalStageModel;
use App\Models\stage\AssiduiteModel;
use App\Models\stage\StageModel;

class EvalStageController extends ResourceController
{
    protected $modelName = EvalStageModel::class;
    protected $format    = 'json';

    public function index()
    {
        $evals = $this->model
            ->select('eval_stage.*, assiduite.asdt_remarque, assiduite.asdt_nb_abscence, assiduite.asdt_nb_retard')
            ->join('assiduite', 'assiduite.asdt_code = eval_stage.asdt_code', 'left')
            ->orderBy('eval_stage.evstg_code', 'DESC')
            ->findAll();

        return $this->respond($evals);
    }

    public function show($id = null)
    {
        $eval = $this->model
            ->select('eval_stage.*, assiduite.asdt_remarque, assiduite.asdt_nb_abscence, assiduite.asdt_nb_retard')
            ->join('assiduite', 'assiduite.asdt_code = eval_stage.asdt_code', 'left')
            ->where('eval_stage.evstg_code', $id)
            ->first();

        if (!$eval) {
            return $this->failNotFound('Évaluation de stage introuvable');
        }

        return $this->respond($eval);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        $assiduiteModel = new AssiduiteModel();
        $assiduiteData = [
            'asdt_remarque'    => $data['asdt_remarque'] ?? null,
            'asdt_nb_abscence' => $data['asdt_nb_abscence'] ?? null,
            'asdt_nb_retard'   => $data['asdt_nb_retard'] ?? null,
        ];

        if (!$assiduiteModel->insert($assiduiteData)) {
            return $this->failValidationErrors($assiduiteModel->errors());
        }

        $asdtCode = $assiduiteModel->getInsertID();

        $evalData = [
            'evstg_lieu'      => $data['evstg_lieu'] ?? null,
            'evstg_note'      => $data['evstg_note'] ?? null,
            'evstg_aptitude'  => $data['evstg_aptitude'] ?? null,
            'evstg_date_eval' => $data['evstg_date_eval'] ?? null,
            'asdt_code'       => $asdtCode,
        ];

        if (!$this->model->insert($evalData)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $evstgCode = $this->model->getInsertID();

        if (!empty($data['stg_code'])) {
            $stageModel = new StageModel();
            $stageModel->update($data['stg_code'], ['evstg_code' => $evstgCode]);
        }

        $eval = $this->model
            ->select('eval_stage.*, assiduite.asdt_remarque, assiduite.asdt_nb_abscence, assiduite.asdt_nb_retard')
            ->join('assiduite', 'assiduite.asdt_code = eval_stage.asdt_code', 'left')
            ->where('eval_stage.evstg_code', $evstgCode)
            ->first();

        return $this->respondCreated([
            'status'  => 'success',
            'message' => 'Évaluation de stage créée avec succès',
            'data'    => $eval,
        ]);
    }

    public function update($id = null)
    {
        $eval = $this->model->find($id);
        if (!$eval) {
            return $this->failNotFound('Évaluation de stage introuvable');
        }

        $data = $this->request->getJSON(true);

        if (!empty($eval['asdt_code'])) {
            $assiduiteModel = new AssiduiteModel();
            $assiduiteData = [
                'asdt_remarque'    => $data['asdt_remarque'] ?? null,
                'asdt_nb_abscence' => $data['asdt_nb_abscence'] ?? null,
                'asdt_nb_retard'   => $data['asdt_nb_retard'] ?? null,
            ];
            $assiduiteModel->update($eval['asdt_code'], $assiduiteData);
        }

        $evalData = [
            'evstg_lieu'      => $data['evstg_lieu'] ?? null,
            'evstg_note'      => $data['evstg_note'] ?? null,
            'evstg_aptitude'  => $data['evstg_aptitude'] ?? null,
            'evstg_date_eval' => $data['evstg_date_eval'] ?? null,
        ];

        if (!$this->model->update($id, $evalData)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $eval = $this->model
            ->select('eval_stage.*, assiduite.asdt_remarque, assiduite.asdt_nb_abscence, assiduite.asdt_nb_retard')
            ->join('assiduite', 'assiduite.asdt_code = eval_stage.asdt_code', 'left')
            ->where('eval_stage.evstg_code', $id)
            ->first();

        return $this->respond([
            'status'  => 'success',
            'message' => 'Évaluation de stage mise à jour avec succès',
            'data'    => $eval,
        ]);
    }

    public function delete($id = null)
    {
        $eval = $this->model->find($id);
        if (!$eval) {
            return $this->failNotFound('Évaluation de stage introuvable');
        }

        $stageModel = new StageModel();
        $stageModel->where('evstg_code', $id)->set(['evstg_code' => null])->update();

        $this->model->delete($id);

        if (!empty($eval['asdt_code'])) {
            $assiduiteModel = new AssiduiteModel();
            $assiduiteModel->delete($eval['asdt_code']);
        }

        return $this->respondDeleted([
            'status'  => 'success',
            'message' => 'Évaluation de stage supprimée avec succès',
        ]);
    }

    public function getByStage($stgCode = null)
    {
        $stageModel = new StageModel();
        $stage = $stageModel->find($stgCode);

        if (!$stage) {
            return $this->failNotFound('Stage introuvable');
        }

        if (empty($stage['evstg_code'])) {
            return $this->respond(null);
        }

        $eval = $this->model
            ->select('eval_stage.*, assiduite.asdt_remarque, assiduite.asdt_nb_abscence, assiduite.asdt_nb_retard')
            ->join('assiduite', 'assiduite.asdt_code = eval_stage.asdt_code', 'left')
            ->where('eval_stage.evstg_code', $stage['evstg_code'])
            ->first();

        return $this->respond($eval);
    }
}

