<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;

class Conges extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        // TODO: fetch with filters (designation, period, status, emp)
        return $this->respond(['items' => [], 'total' => 0]);
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: validate & insert into conge + validation_conge status Soumis
        return $this->respondCreated(['message' => 'Créé']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true) ?? [];
        // TODO: validate & update if modifiable
        return $this->respond(['message' => 'Mis à jour']);
    }

    public function delete($id = null)
    {
        // TODO: delete if allowed
        return $this->respondNoContent();
    }

    public function solde()
    {
        $emp = (int)($this->request->getGet('emp') ?? 0);
        $year = (int)($this->request->getGet('year') ?? date('Y'));
        // TODO: call fn_solde_conge(emp, year)
        return $this->respond(['emp' => $emp, 'year' => $year, 'solde' => 0]);
    }
}
