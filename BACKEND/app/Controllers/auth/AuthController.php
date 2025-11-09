<?php

namespace App\Controllers\auth;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\user\AdminModel;
use Firebase\JWT\JWT;

class AuthController extends ResourceController
{
    use ResponseTrait;

    public function login()
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
         $request = $this->request;
    
        $adminModel = new AdminModel();
    
    
        $json = $request->getJSON();
            
            if (!$json) {
                return $this->fail('Données manquantes', 400);
            }
            
            $username = $json->username ?? null;
            $password = $json->password ?? null;

            if (!$username || !$password) {
                return $this->fail('username et mot de passe requis', 400);
            }

            $admin = $adminModel->where('username', $username)->first();

            if (!$admin || !password_verify($password, $admin['password'])) {
                return $this->failUnauthorized('username ou mot de passe incorrect');
            }

            // Générer le token JWT
            $key = getenv('JWT_SECRET');
            if (!$key) {
                $key = 'MaCleSecreteUnique123456789ComplexeEtLongue';
            }
            
            $iat = time();
            $ttl = getenv('JWT_TIME_TO_LIVE');
            $ttl = $ttl ? (int)$ttl : 3600;  // ⭐ Garder TTL en variable
            $exp = $iat + $ttl;  // Timestamp absolu pour le JWT

            $payload = [
                'iss' => base_url(),
                'aud' => base_url(),
                'iat' => $iat,
                'exp' => $exp,
                'data' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'nom' => $admin['nom'],
                    'prenom' => $admin['prenom'],
                    'role' => $admin['role']
                ]
            ];

            $token = JWT::encode($payload, $key, 'HS256');

       
        $this->response->setCookie([
            'name'     => 'sid',
            'value'    => $token,
            'expire'   => $ttl,
            'domain'   => '',
            'path'     => '/',
            'secure'   => false, 
            'httponly' => true,   
            'samesite' => 'Lax'  
        ]);

        // Retourner la réponse (sans le token dans le body)
        return $this->respond([
            'status' => 'success',
            'message' => 'Connexion réussie',
            'admin' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'nom' => $admin['nom'],
                'prenom' => $admin['prenom'],
                'role' => $admin['role']
            ]
        ], 200);
    }

    public function logout()
    {
        // Supprimer le cookie
        $this->response->deleteCookie('auth_token');
        
        return $this->respond([
            'status' => 'success',
            'message' => 'Déconnexion réussie'
        ], 200);
    }
}
