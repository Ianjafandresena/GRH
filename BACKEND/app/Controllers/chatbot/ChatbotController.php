<?php

namespace App\Controllers\chatbot;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Services\ChatbotService;

/**
 * Controller Chatbot RH Offline
 * MODULE BONUS - Isolé des fonctionnalités principales
 */
class ChatbotController extends ResourceController
{
    use ResponseTrait;
    
    protected $chatbotService;
    
    public function __construct()
    {
        try {
            $this->chatbotService = new ChatbotService();
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] Erreur init service: ' . $e->getMessage());
        }
    }
    
    /**
     * POST /api/chatbot/message
     * Traiter un message utilisateur
     */
    public function sendMessage()
    {
        try {
            $payload = $this->request->getJSON(true);
            
            log_message('info', '[Chatbot] Received payload: ' . json_encode($payload));
            
            $message = $payload['message'] ?? '';
            $empCode = $payload['emp_code'] ?? 1;  // ⚠️ Default 1 pour test (admin)
            
            if (!$message) {
                log_message('error', '[Chatbot] Message vide reçu');
                return $this->fail('Message requis', 400);
            }
            
            log_message('info', "[Chatbot] Processing: message='{$message}', emp_code={$empCode}");
            
            // Traiter via service
            $response = $this->chatbotService->processMessage($message, $empCode);
            
            return $this->respond([
                'success' => true,
                'message' => $response['text'],
                'suggestions' => $response['suggestions'] ?? [],
                'actions' => $response['actions'] ?? [],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            // Fail-safe: Ne jamais crasher, toujours retourner une réponse
            log_message('error', '[Chatbot] Erreur sendMessage: ' . $e->getMessage());
            log_message('error', '[Chatbot] Stack trace: ' . $e->getTraceAsString());
            
            return $this->respond([
                'success' => false,
                'message' => "Désolé, une erreur s'est produite. Veuillez réessayer ou tapez 'aide'.",
                'suggestions' => ['Solde de ANDRIA', 'Demandes en attente', 'Aide'],
                'actions' => []
            ]);
        }
    }
    
    /**
     * GET /api/chatbot/suggestions
     * Obtenir suggestions rapides personnalisées
     */
    public function getSuggestions()
    {
        try {
            $empCode = $this->request->getGet('emp_code');
            
            if (!$empCode) {
                return $this->fail('emp_code requis', 400);
            }
            
            $suggestions = $this->chatbotService->getQuickSuggestions($empCode);
            
            return $this->respond([
                'success' => true,
                'suggestions' => $suggestions
            ]);
            
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] Erreur getSuggestions: ' . $e->getMessage());
            
            return $this->respond([
                'success' => false,
                'suggestions' => ['Mon solde', 'Mes demandes']
            ]);
        }
    }
    
    /**
     * GET /api/chatbot/health
     * Vérifier santé du module (monitoring)
     */
    public function health()
    {
        try {
            return $this->respond([
                'status' => 'operational',
                'module' => 'chatbot',
                'version' => '1.0.0',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
