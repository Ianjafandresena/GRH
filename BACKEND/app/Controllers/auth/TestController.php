<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class TestController extends ResourceController
{
    use ResponseTrait;

    /**
     * Endpoint de test protégé par JWT
     * GET /api/test/hello
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function hello()
    {
        // Récupérer les données de l'admin depuis la requête
        // Ces données sont injectées par le JWTAuthFilter
        $adminData = $this->request->admin;
        
        return $this->respond([
            'status' => 'success',
            'message' => 'Hello World! 🎉',
            'admin_connecte' => [
                'id' => $adminData->id,
                'email' => $adminData->email,
                'nom' => $adminData->nom,
                'prenom' => $adminData->prenom,
                'role' => $adminData->role
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], 200);
    }
}
