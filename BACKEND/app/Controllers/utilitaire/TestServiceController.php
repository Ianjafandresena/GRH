<?php
namespace App\Controllers\utilitaire;

use CodeIgniter\Controller;

class TestServiceController extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();
        $query = $db->query('SELECT * FROM service');
        $results = $query->getResultArray();
        
        return $this->response->setJSON($results);
    }
}

