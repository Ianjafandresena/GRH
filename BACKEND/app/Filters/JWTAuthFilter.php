<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
         /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        
        // Lire le token depuis le cookie
        $token = $request->getCookie('sid');
        
        // Si pas de cookie, essayer l'en-tête Authorization (fallback)
        if (!$token) {
            $authHeader = $request->getHeaderLine('Authorization');
            
            if (!$authHeader) {
                return service('response')->setJSON([
                    'status' => 401,
                    'error' => 401,
                    'messages' => ['error' => 'Token manquant']
                ])->setStatusCode(401);
            }
            
            $token = str_replace('Bearer ', '', $authHeader);
        }

        try {
            $key = getenv('JWT_SECRET');
            if (!$key) {
                $key = 'MaCleSecreteUnique123456789ComplexeEtLongue';
            }
            
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            
            // Stocker les données de l'utilisateur dans la requête 
            $request->admin = $decoded->data;
            
        } catch (\Exception $e) {
            return service('response')->setJSON([
                'status' => 401,
                'error' => 401,
                'messages' => ['error' => 'Token invalide ou expiré']
            ])->setStatusCode(401);
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Rien à faire
    }
}
