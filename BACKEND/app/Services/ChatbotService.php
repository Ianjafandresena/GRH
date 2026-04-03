<?php

namespace App\Services;

use App\Models\conge\CongeModel;
use App\Models\conge\SoldeCongeModel;
use App\Models\permission\PermissionModel;
use App\Models\employee\EmployeeModel;

/**
 * ChatbotService - INTELLIGENT
 * Comprend les règles métier FIFO, statuts, backoffice
 * 
 * ISOLATION: Chatbot appelle les autres modules, JAMAIS l'inverse
 */
class ChatbotService
{
    protected $congeModel;
    protected $employeeModel;
    protected $knowledgeService;
    
    public function __construct()
    {
        try {
            // Utiliser les Models EXISTANTS
            $this->congeModel = new CongeModel();
            $this->soldeCongeModel = new SoldeCongeModel();
            $this->permissionModel = new PermissionModel();
            $this->employeeModel = new EmployeeModel();
            $this->knowledgeService = new KnowledgeService();
            
            log_message('info', '[Chatbot] Service initialized successfully');
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] Erreur init Models: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Traiter un message utilisateur
     */
    public function processMessage(string $message, int $empCode): array
    {
        try {
            log_message('info', "[Chatbot] Processing message: '{$message}' for emp_code: {$empCode}");
            
            // Normaliser message
            $normalized = $this->normalizeText($message);
            log_message('info', "[Chatbot] Normalized: '{$normalized}'");
            
            // 1. Récupérer le contexte via RAG (KnowledgeBase)
            $context = $this->knowledgeService->getContext($message);
            log_message('info', "[Chatbot-RAG] Context retrieved: " . strlen($context) . " bytes");
            
            // 2. Détecter l'intention
            $intent = $this->detectIntent($normalized);
            log_message('info', "[Chatbot] Detected intent: '{$intent}'");
            
            // 3. Exécuter l'action correspondante
            $result = $this->executeIntent($intent, $empCode, $normalized);
            
            // Ajouter le contexte technique pour le débogage (optionnel, caché en prod)
            $result['rag_context'] = $context;
            
            log_message('info', "[Chatbot] Intent executed successfully");
            
            return $result;
            
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] processMessage ERROR: ' . $e->getMessage());
            log_message('error', '[Chatbot] Stack trace: ' . $e->getTraceAsString());
            
            return [
                'text' => "❌ Erreur: " . $e->getMessage(),
                'suggestions' => ['Solde de ANDRIA', 'Aide']
            ];
        }
    }
    
    /**
     * Normaliser texte
     */
    private function normalizeText(string $text): string
    {
        $text = strtolower($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^\w\s]/u', '', $text);
        return trim($text);
    }
    
    /**
     * Détecter intention - BACKOFFICE aware
     */
    private function detectIntent(string $normalized): string
    {
        $intents = [
            // Solde avec recherche employé
            'search_employee_solde' => ['solde', 'reste', 'restant', 'jours', 'combien'],
            
            // Demandes avec filtres
            'list_demandes_pending' => ['non valide', 'attente', 'pending', 'en cours'],
            'list_demandes_validated' => ['valide', 'validee', 'approuve'],
            'list_demandes_all' => ['liste', 'demandes', 'toutes'],
            
            // Remboursements
            'list_remboursements' => ['remboursement', 'rembourse', 'frais', 'facture'],
            'help_create_remb' => ['comment rembourse', 'creer rembourse', 'demander rembourse'],
            
            // Prise en charge
            'list_pec' => ['prise en charge', 'pec', 'couverture'],
            'help_create_pec' => ['comment pec', 'creer pec', 'demander pec'],
            
            // Calculs FIFO
            'calculate_fifo' => ['peut prendre', 'peut poser', 'verifier', 'suffisant'],
            
            // Recherche employé
            'search_employee' => ['employe', 'chercher', 'trouver', 'qui est'],
            
            // Guide système
            'help_create' => ['comment', 'creer', 'faire', 'demande'],
            'help_navigation' => ['ou', 'trouver', 'aller', 'page'],
            
            // Connaissances projet (RAG)
            'about_project' => ['projet', 'c quoi', 'qu’est-ce', 'qui es tu', 'systeme', 'logiciel'],
            'about_rules' => ['regle', 'loi', 'politique', 'calcul', 'fonctionnement'],
            'about_user' => ['manuel', 'utilisateur', 'comment utiliser', 'aidez moi', 'tuto', 'etape'],
            
            // Social
            'greeting' => ['bonjour', 'salut', 'hello', 'hey'],
            'thanks' => ['merci', 'super', 'parfait', 'ok'],
        ];
        
        $scores = [];
        
        foreach ($intents as $intent => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($normalized, $keyword) !== false) {
                    $score++;
                }
            }
            $scores[$intent] = $score;
        }
        
        arsort($scores);
        $bestIntent = array_key_first($scores);
        
        return $scores[$bestIntent] > 0 ? $bestIntent : 'unknown';
    }
    
    /**
     * Exécuter intention
     */
    private function executeIntent(string $intent, int $empCode, string $message): array
    {
        switch ($intent) {
            case 'search_employee_solde':
                return $this->searchEmployeeSolde($message);
                
            case 'list_demandes_pending':
                return $this->listDemandes('pending');
                
            case 'list_demandes_validated':
                return $this->listDemandes('validated');
                
            case 'list_demandes_all':
                return $this->listDemandes('all');
                
            case 'list_remboursements':
                return $this->listRemboursements();
                
            case 'help_create_remb':
                return $this->guideCreationRemb();
                
            case 'list_pec':
                return $this->listPriseEnCharge();
                
            case 'help_create_pec':
                return $this->guideCreationPec();
                
            case 'calculate_fifo':
                return $this->calculateFifo($message);
                
            case 'search_employee':
                return $this->searchEmployee($message);
                
            case 'help_create':
                return $this->guideCreationConge();
                
            case 'help_navigation':
                return $this->guideNavigation($message);
                
            case 'about_project':
                return $this->knowledgeResponse('project_overview.md');
                
            case 'about_rules':
                return $this->knowledgeResponse('rules'); // cherchera par mot clé
                
            case 'about_user':
                return $this->knowledgeResponse('user_manual.md');
                
            case 'greeting':
                return $this->greetingResponse();
                
            case 'thanks':
                return $this->thanksResponse();
                
            default:
                return $this->defaultResponse();
        }
    }
    
    /**
     * Rechercher employé et afficher soldes FIFO intelligents
     */
    private function searchEmployeeSolde(string $message): array
    {
        try {
            // Extraire nom/matricule du message
            $searchTerm = $this->extractEmployeeName($message);
            
            if (!$searchTerm) {
                return [
                    'text' => "Quel employé recherchez-vous ?\nExemple: 'solde de Tiana' ou 'solde IM123456'",
                    'suggestions' => ['Rechercher employé', 'Demandes validées']
                ];
            }
            
            // Rechercher employé
            $employee = $this->employeeModel
                ->groupStart()
                    ->like('emp_nom', $searchTerm)
                    ->orLike('emp_prenom', $searchTerm)
                    ->orLike('emp_im_armp', $searchTerm)
                ->groupEnd()
                ->first();
            
            if (!$employee) {
                return [
                    'text' => "❌ Employé introuvable : '{$searchTerm}'\n\nVérifiez le nom ou matricule.",
                    'suggestions' => ['Rechercher employé', 'Liste des demandes']
                ];
            }
            
            // Récupérer soldes FIFO (ordre chronologique)
            $soldes = $this->soldeCongeModel
                ->select('solde_conge.*, decision.dec_num')
                ->join('decision', 'decision.dec_code = solde_conge.dec_code')
                ->where('emp_code', $employee['emp_code'])
                ->orderBy('sld_anne', 'ASC') // FIFO: Plus ancien d'abord
                ->findAll();
            
            if (empty($soldes)) {
                return [
                    'text' => "📋 **{$employee['emp_nom']} {$employee['emp_prenom']}**\n" .
                             "Matricule: {$employee['emp_im_armp']}\n\n" .
                             "❌ Aucun solde de congé enregistré.",
                    'suggestions' => ['Demandes de cet employé', 'Aide']
                ];
            }
            
            // Calculer total et formater affichage FIFO
            $totalRestant = 0;
            $details = "";
            
            foreach ($soldes as $solde) {
                $totalRestant += $solde['sld_restant'];
                $emoji = $solde['sld_restant'] > 0 ? '✅' : '⚪';
                
                $details .= "{$emoji} **{$solde['sld_anne']}**: {$solde['sld_restant']} jours restants\n";
                $details .= "   _(Initial: {$solde['sld_initial']} | Décision: {$solde['dec_num']})_\n";
            }
            
            $text = "👤 **{$employee['emp_nom']} {$employee['emp_prenom']}**\n";
            $text .= "Matricule: {$employee['emp_im_armp']}\n\n";
            $text .= "📊 **Soldes disponibles (FIFO)**\n\n{$details}\n";
            $text .= "**Total: {$totalRestant} jours**";
            
            return [
                'text' => $text,
                'suggestions' => ['Peut prendre 10 jours ?', 'Demandes validées'],
                'actions' => [
                    ['label' => 'Voir soldes', 'route' => '/conge/etat', 'auto' => true]
                ]
            ];
            
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] Erreur searchEmployeeSolde: ' . $e->getMessage());
            return $this->errorResponse();
        }
    }
    
    /**
     * Lister demandes avec filtres (pending/validated/all)
     */
    private function listDemandes(string $filter = 'all'): array
    {
        try {
            $builder = $this->congeModel
                ->select('conge.*, employe.emp_nom, employe.emp_prenom, type_conge.typ_appelation')
                ->join('employe', 'employe.emp_code = conge.emp_code')
                ->join('type_conge', 'type_conge.typ_code = conge.typ_code');
            
            // Appliquer filtre statut
            switch ($filter) {
                case 'pending':
                    $builder->where('cng_status IS NULL');
                    $title = "📋 Demandes en attente de validation";
                    break;
                case 'validated':
                    $builder->where('cng_status', true);
                    $title = "✅ Demandes validées";
                    break;
                default:
                    $title = "📋 Toutes les demandes";
            }
            
            $demandes = $builder->orderBy('cng_demande', 'DESC')
                               ->limit(10)
                               ->findAll();
            
            if (empty($demandes)) {
                return [
                    'text' => "{$title}\n\n Aucune demande trouvée.",
                    'suggestions' => ['Demandes validées', 'Toutes les demandes']
                ];
            }
            
            $text = "{$title} (" . count($demandes) . ")\n\n";
            
            foreach ($demandes as $idx => $d) {
                $num = $idx + 1;
                $emoji = is_null($d['cng_status']) ? '⏳' : ($d['cng_status'] ? '✅' : '❌');
                
                $text .= "{$num}. {$emoji} **{$d['emp_nom']} {$d['emp_prenom']}**\n";
                $text .= "   {$d['typ_appelation']} - {$d['cng_nb_jour']} jours\n";
                $text .= "   {$d['cng_debut']} → {$d['cng_fin']}\n\n";
            }
            
            return [
                'text' => $text,
                'suggestions' => ['Demandes validées', 'Demandes en attente', 'Toutes les demandes'],
                'actions' => [
                    ['label' => 'Voir liste', 'route' => '/conge/index', 'auto' => true]
                ]
            ];
            
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] Erreur listDemandes: ' . $e->getMessage());
            return $this->errorResponse();
        }
    }
    
    /**
     * Calculer si X jours possible avec FIFO
     */
    private function calculateFifo(string $message): array
    {
        try {
            // Extraire nom employé et nombre de jours
            $searchTerm = $this->extractEmployeeName($message);
            $days = $this->extractNumber($message);
            
            if (!$searchTerm || !$days) {
                return [
                    'text' => "Pour vérifier, j'ai besoin de:\n• Nom employé\n• Nombre de jours\n\nExemple: 'Tiana peut prendre 40 jours ?'",
                    'suggestions' => ['ANDRIA peut 10 jours ?', 'Solde de RAKOTO']
                ];
            }
            
            // Rechercher employé
            $employee = $this->employeeModel
                ->groupStart()
                    ->like('emp_nom', $searchTerm)
                    ->orLike('emp_prenom', $searchTerm)
                ->groupEnd()
                ->first();
            
            if (!$employee) {
                return ['text' => "❌ Employé '{$searchTerm}' introuvable."];
            }
            
            // Récupérer soldes FIFO
            $soldes = $this->soldeCongeModel
                ->select('sld_anne, sld_restant, dec_num')
                ->join('decision', 'decision.dec_code = solde_conge.dec_code')
                ->where('emp_code', $employee['emp_code'])
                ->orderBy('sld_anne', 'ASC')
                ->findAll();
            
            // Simuler consommation FIFO
            $remaining = $days;
            $repartition = "";
            $possible = false;
            
            foreach ($soldes as $solde) {
                if ($remaining <= 0) break;
                
                $toTake = min($remaining, $solde['sld_restant']);
                $newSolde = $solde['sld_restant'] - $toTake;
                
                $repartition .= "• {$toTake} jours sur solde {$solde['sld_anne']} (→ {$newSolde})\n";
                
                $remaining -= $toTake;
            }
            
            $possible = ($remaining == 0);
            $emoji = $possible ? '✅' : '❌';
            
            if ($possible) {
                $text = "{$emoji} **Oui**, {$employee['emp_nom']} peut prendre {$days} jours\n\n";
                $text .= "**Répartition FIFO:**\n{$repartition}";
            } else {
                $totalDispo = array_sum(array_column($soldes, 'sld_restant'));
                $text = "{$emoji} **Non**, insuffisant\n\n";
                $text .= "Demandé: {$days} jours\n";
                $text .= "Disponible: {$totalDispo} jours\n";
                $text .= "Manque: " . ($days - $totalDispo) . " jours";
            }
            
            return [
                'text' => $text,
                'suggestions' => ['Solde détaillé', 'Demandes en attente']
            ];
            
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] Erreur calculateFifo: ' . $e->getMessage());
            return $this->errorResponse();
        }
    }
    
    /**
     * Rechercher employé (sans solde)
     */
    private function searchEmployee(string $message): array
    {
        try {
            $searchTerm = $this->extractEmployeeName($message);
            
            $employees = $this->employeeModel
                ->groupStart()
                    ->like('emp_nom', $searchTerm)
                    ->orLike('emp_prenom', $searchTerm)
                    ->orLike('emp_im_armp', $searchTerm)
                ->groupEnd()
                ->limit(5)
                ->findAll();
            
            if (empty($employees)) {
                return ['text' => "❌ Aucun employé trouvé pour '{$searchTerm}'"];
            }
            
            $text = "👥 **Employés trouvés ({" . count($employees) . "})**\n\n";
            
            foreach ($employees as $emp) {
                $text .= "• {$emp['emp_nom']} {$emp['emp_prenom']}\n";
                $text .= "  Matricule: {$emp['emp_im_armp']}\n\n";
            }
            
            return [
                'text' => $text,
                'suggestions' => ['Solde de ' . ($employees[0]['emp_prenom'] ?? 'cet employé'), 'Demandes validées']
            ];
            
        } catch (\Exception $e) {
            log_message('error', '[Chatbot] Erreur searchEmployee: ' . $e->getMessage());
            return $this->errorResponse();
        }
    }
    
    /**
     * Extraire nom employé du message
     */
    private function extractEmployeeName(string $message): ?string
    {
        // Retirer mots-clés communs
        $message = str_replace(['solde', 'de', 'pour', 'reste', 'combien', 'jours', 'peut', 'prendre'], '', $message);
        $message = trim($message);
        
        // Si reste quelque chose
        return strlen($message) > 2 ? $message : null;
    }
    
    /**
     * Extraire nombre du message
     */
    private function extractNumber(string $message): ?int
    {
        preg_match('/\d+/', $message, $matches);
        return isset($matches[0]) ? (int)$matches[0] : null;
    }
    
    /**
     * Guide création congé
     */
    private function guideCreationConge(): array
    {
        return [
            'text' => "📝 **Comment créer une demande de congé ?**\n\n" .
                     "1. Allez dans _Gestion des Absences > Création_\n" .
                     "2. Choisissez **📅 Congé**\n" .
                     "3. Recherchez l'employé\n" .
                     "4. Sélectionnez les dates et le type\n" .
                     "5. Le système vérifie automatiquement le solde disponible et décision à défalquer\n" .
                     "6. Enregistrez !\n\n" .
                     "💡 Le solde est débité du plus ancien au plus récent.",
            'suggestions' => ['Demandes en attente', 'Remboursements'],
            'actions' => [
                ['label' => 'Créer maintenant', 'route' => '/conge/create', 'auto' => true]
            ]
        ];
    }
    
    /**
     * Guide navigation
     */
    private function guideNavigation(string $message): array
    {
        return [
            'text' => "🧭 **Navigation**\n\n" .
                     "• **Gestion des Absences** : Congés et permissions\n" .
                     "  - Création, liste, validation, état\n" .
                     "• **Remboursements** : Frais médicaux\n" .
                     "• **Centres de Santé** : Gestion centres\n\n" .
                     "Que cherchez-vous ?",
            'suggestions' => ['Créer un congé', 'Demandes en attente']
        ];
    }
    
    /**
     * Salutation
     */
    private function greetingResponse(): array
    {
        return [
            'text' => "👋 Bonjour ! Je suis votre assistant RH intelligent.\n\n" .
                     "Je peux vous aider avec :\n" .
                     "• **Soldes de congés** (règle FIFO)\n" .
                     "• **Demandes** (validées, en attente)\n" .
                     "• **Remboursements** et **Prises en charge**\n" .
                     "• **Calculs** (vérifier disponibilité)\n" .
                     "• **Recherche employés**\n\n" .
                     "Que souhaitez-vous savoir ?",
            'suggestions' => ['Demandes en attente', 'Remboursements', 'Comment créer congé ?']
        ];
    }
    
    /**
     * Remerciement
     */
    private function thanksResponse(): array
    {
        return [
            'text' => "😊 De rien ! N'hésitez pas si vous avez d'autres questions.",
            'suggestions' => ['Liste des demandes', 'Prises en charge', 'Remboursements']
        ];
    }
    
    /**
     * Réponse par défaut
     */
    private function defaultResponse(): array
    {
        return [
            'text' => "🤔 Je n'ai pas bien compris.\n\n" .
                     "**Je peux vous aider avec :**\n" .
                     "• 'Solde de [nom]' - Voir soldes FIFO\n" .
                     "• 'Demandes non validées' - Lister demandes\n" .
                     "• '[Nom] peut 40 jours ?' - Calcul FIFO\n" .
                     "• 'Comment créer congé ?' - Guide\n\n" .
                     "Essayez une de ces options:",
            'suggestions' => ['Rechercher employé', 'Demandes en attente', 'Remboursements']
        ];
    }
    
    /**
     * Réponse d'erreur
     */
    private function errorResponse(): array
    {
        return [
            'text' => "❌ Erreur lors du traitement.\n\nRéessayez ou reformulez votre question.",
            'suggestions' => ['Comment créer congé ?', 'Liste remboursements', 'Aide']
        ];
    }
    
    /**
     * Lister remboursements
     */
    private function listRemboursements(): array
    {
        return [
            'text' => "💰 **Remboursements de Frais Médicaux**\n\n" .
                     "**Fonctionnalités disponibles :**\n" .
                     "• Consulter demandes de remboursement\n" .
                     "• Créer nouvelle demande\n" .
                     "• Suivre statuts de traitement\n\n" .
                     "Que souhaitez-vous faire ?",
            'suggestions' => ['Comment créer remboursement ?', 'Liste remboursements'],
            'actions' => [
                ['label' => 'Voir remboursements', 'route' => '/remboursement/index', 'auto' => true]
            ]
        ];
    }
    
    /**
     * Guide création remboursement
     */
    private function guideCreationRemb(): array
    {
        return [
            'text' => "💰 **Comment créer une demande de remboursement ?**\n\n" .
                     "1. Allez dans _Remboursements > Création_\n" .
                     "2. Sélectionnez le **bénéficiaire** (employé ou ayant-droit)\n" .
                     "3. Choisissez le **centre de santé**\n" .
                     "4. Ajoutez les **factures** avec montants\n" .
                     "5. Le système calcule automatiquement le remboursement selon la prise en charge\n" .
                     "6. Enregistrez !\n\n" .
                     "💡 Assurez-vous que le bénéficiaire a une prise en charge active.",
            'suggestions' => ['Prises en charge', 'Demandes en attente'],
            'actions' => [
                ['label' => 'Créer remboursement', 'route' => '/remboursement/create', 'auto' => true]
            ]
        ];
    }
    
    /**
     * Lister prises en charge
     */
    private function listPriseEnCharge(): array
    {
        return [
            'text' => "🏥 **Prise en Charge (PEC)**\n\n" .
                     "La prise en charge permet :\n" .
                     "• **Remboursement à 100%** des frais médicaux\n" .
                     "• **Aucun plafond** - tous les frais sont couverts\n" .
                     "• **Bénéficiaires** : Employés et ayants-droit\n\n" .
                     "**Fonctionnalités :**\n" .
                     "• Consulter les PEC actives\n" .
                     "• Gérer bénéficiaires\n" .
                     "• Suivre les remboursements",
            'suggestions' => ['Comment créer PEC ?', 'Remboursements'],
            'actions' => [
                ['label' => 'Voir prises en charge', 'route' => '/remboursement/pec', 'auto' => true]
            ]
        ];
    }
    
    /**
     * Guide création prise en charge
     */
    private function guideCreationPec(): array
    {
        return [
            'text' => "🏥 **Comment créer une prise en charge ?**\n\n" .
                     "1. Allez dans _Remboursements > Prise en Charge_\n" .
                     "2. Cliquez sur **Nouvelle PEC**\n" .
                     "3. Définissez la période de validité\n" .
                     "4. Ajoutez les bénéficiaires (employé + ayants-droit)\n" .
                     "5. Enregistrez !\n\n" .
                     "💡 Le remboursement est automatiquement **à 100%** sans plafond.",
            'suggestions' => ['Remboursements', 'Liste PEC'],
            'actions' => [
                ['label' => 'Gérer PEC', 'route' => '/remboursement/pec', 'auto' => true]
            ]
        ];
    }
    
    /**
     * Suggestions rapides personnalisées - VARIÉES et GÉNÉRALES
     */
    public function getQuickSuggestions(int $empCode): array
    {
        // Suggestions variées couvrant tous les modules
        $allSuggestions = [
            'Demandes en attente',
            'Demandes validées',
            'Remboursements',
            'Prises en charge',
            'Comment créer congé ?',
            'Comment créer remboursement ?',
            'Liste des demandes',
            'Rechercher employé'
        ];
        
        // Mélanger et prendre 5 suggestions aléatoires
        shuffle($allSuggestions);
        return array_slice($allSuggestions, 0, 5);
    }

    /**
     * Réponse basée sur la base de connaissances (RAG)
     */
    private function knowledgeResponse(string $hint): array
    {
        $context = $this->knowledgeService->getContext($hint);
        
        return [
            'text' => "📚 **D'après mon manuel utilisateur :**\n\n" . $context,
            'suggestions' => ['Guide des congés', 'Règles de remboursement', 'Comment utiliser l\'app ?']
        ];
    }
}
