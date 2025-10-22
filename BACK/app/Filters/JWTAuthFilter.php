<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JWTAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Vérifier d'abord le cookie 'sid' - Méthode directe
        $token = $_COOKIE['sid'] ?? null;
        
        // Si pas de cookie, vérifier le header Authorization
        if (empty($token)) {
            $header = $request->getHeaderLine('Authorization');
            $token = str_replace('Bearer ', '', $header);
        }
        
        if (empty($token)) {
            return service('response')->setJSON([
                'status' => false,
                'message' => 'Authentication required - no session cookie or token'
            ])->setStatusCode(401);
        }

        try {
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded) {
                return service('response')->setJSON([
                    'status' => false,
                    'message' => 'Invalid token format'
                ])->setStatusCode(401);
            }

            $request->user = (object)$decoded;
            
        } catch (\Exception $e) {
            return service('response')->setJSON([
                'status' => false,
                'message' => 'Token decoding failed'
            ])->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}