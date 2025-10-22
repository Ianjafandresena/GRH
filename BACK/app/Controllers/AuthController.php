<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class AuthController extends ResourceController
{
    use ResponseTrait;

    public function login()
    {
        $rawInput = $this->request->getBody();
        $input = json_decode($rawInput);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->failValidationErrors('Invalid JSON format');
        }

        $username = $input->username ?? '';
        $password = $input->password ?? '';

        if (empty($username) || empty($password)) {
            return $this->failValidationErrors('Username and password required');
        }

        $userModel = new UserModel();
        $user = $userModel->getUserByUsername($username);

        if (!$user) {
            return $this->failUnauthorized('User not found');
        }

        if (!$user['is_active']) {
            return $this->failUnauthorized('Account is disabled');
        }

        if ($password !== $user['pswrd']) {
            return $this->failUnauthorized('Invalid password');
        }

        $userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);

        // Génération d'un SID UNIQUE par session
        $sessionId = bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
        $tokenData = [
            'sid' => $sessionId,      // UNIQUE à chaque connexion
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'login_time' => time(),
            'expires' => time() + 3600
        ];

        $simpleToken = base64_encode(json_encode($tokenData));

        // Créer le cookie avec le token
        $this->response->setCookie(
            'sid',
            $simpleToken,
            3600,
            '',
            '/',
            '',
            false,
            true,
            'Strict'
        );

        return $this->respond([
            'status' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'] == 0 ? 'admin' : 'user',
                'emp_code' => $user['emp_code']
            ]
        ]);
    }

    public function logout()
    {
        // Supprimer le cookie
        $this->response->deleteCookie('sid', '', '/');

        return $this->respond([
            'status' => true,
            'message' => 'Logout successful'
        ]);
    }

    public function me()
    {
        $token = $_COOKIE['sid'] ?? null;
        
        if (empty($token)) {
            return $this->failUnauthorized('No session cookie found');
        }

        try {
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded) {
                return $this->failUnauthorized('Invalid token in cookie');
            }

            // Vérifier l'expiration
            if (time() > $decoded['expires']) {
                return $this->failUnauthorized('Session expired');
            }

            return $this->respond([
                'status' => true,
                'user' => [
                    'id' => $decoded['id'],
                    'username' => $decoded['username'],
                    'role' => $decoded['role'] == 0 ? 'admin' : 'user'
                ],
                'session_info' => [
                    'sid' => $decoded['sid'], // Pour voir le SID unique
                    'login_time' => date('Y-m-d H:i:s', $decoded['login_time']),
                    'expires' => date('Y-m-d H:i:s', $decoded['expires'])
                ]
            ]);
        } catch (\Exception $e) {
            return $this->failUnauthorized('Token decoding failed');
        }
    }
}