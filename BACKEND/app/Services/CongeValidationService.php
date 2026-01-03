<?php

namespace App\Services;

use App\Models\conge\SignatureModel;

/**
 * Service central pour la logique de validation des congés
 * Gère la construction dynamique des chaînes de validation et la recherche des validateurs.
 */
class CongeValidationService
{
    // Mapping des codes signature (doit correspondre à la table Signature)
    public const SIGN_DG = 1;
    public const SIGN_DAAF = 2;
    public const SIGN_RRH = 3;
    public const SIGN_CHEF = 4;
    public const SIGN_DIRECTEUR = 5; 

    private EmailService $emailService;
    private SignatureModel $signatureModel;

    public function __construct()
    {
        $this->emailService = new EmailService();
        $this->signatureModel = new SignatureModel();
    }

    /**
     * Détermine les étapes de validation pour un employé donné
     * @param int $empCode
     * @return array Liste des étapes [['step' => 'Chef de Service', 'code' => 4], ...]
     */
    public function getValidationSteps(int $empCode): array
    {
        $db = \Config\Database::connect();

        // 1. Récupérer infos demandeur (Poste, Direction)
        $emp = $db->table('employee e')
            ->select('e.emp_code, p.pst_fonction, d.dir_abreviation, d.dir_code')
            ->join('affectation a', 'a.emp_code = e.emp_code')
            ->join('poste p', 'p.pst_code = a.pst_code')
            ->join('direction d', 'd.dir_code = a.dir_code', 'left')
            ->where('e.emp_code', $empCode)
            ->get()->getRowArray();

        if (!$emp) return [];

        $poste = strtoupper($emp['pst_fonction']);
        $dirAbbr = strtoupper($emp['dir_abreviation'] ?? '');

        // 2. Définir la chaîne complète avec CODES DYNAMIQUES
        $chefCode = $this->getChefSignCode($emp['dir_code'] ?? 0) ?? self::SIGN_CHEF;
        $dirCodeSign = $this->getDirectorSignCode($emp['dir_code'] ?? 0) ?? self::SIGN_DIRECTEUR;

        $chain = [
            ['step' => 'Chef de Service', 'code' => $chefCode],
            ['step' => 'Directeur', 'code' => $dirCodeSign], 
            ['step' => 'RRH', 'code' => self::SIGN_RRH], 
            ['step' => 'DAAF', 'code' => self::SIGN_DAAF], 
            ['step' => 'Directeur General', 'code' => self::SIGN_DG]
        ];

        // 3. Adapter la chaîne selon la Direction
        
        // Si Direction = DAAF, Directeur = DAAF. Retirer 'Directeur'
        if ($dirAbbr === 'DAAF') {
             // Filter out generic 'Directeur'
             $chain = array_values(array_filter($chain, fn($s) => $s['code'] !== self::SIGN_DIRECTEUR));
        }

        // Si Direction = SRH, Directeur = RRH. Retirer 'Directeur'
        if ($dirAbbr === 'SRH' || $dirAbbr === 'RH') {
             $chain = array_values(array_filter($chain, fn($s) => $s['code'] !== self::SIGN_DIRECTEUR));
        }

        // 4. Filtrer selon le Poste (Point de départ)

        if ($poste === 'DIRECTEUR GENERAL') {
            return []; // Aucune validation
        }

        if (str_contains($poste, 'DIRECTEUR')) {
            // Directeur -> DG uniquement
            return [['step' => 'Directeur General', 'code' => self::SIGN_DG]];
        }

        if (str_contains($poste, 'CHEF DE SERVICE') || str_contains($poste, 'CHEF')) {
            // Chef -> saute la première étape si c'est Chef
            // Chercher l'index de l'étape "Chef de Service" dans la chaîne
            $chefIndex = null;
            foreach ($chain as $index => $step) {
                if ($step['step'] === 'Chef de Service') {
                    $chefIndex = $index;
                    break;
                }
            }
            
            // Si trouvé, on retire cette étape
            if ($chefIndex !== null) {
                array_splice($chain, $chefIndex, 1);
            }
            
            return $chain;
        }

        // Si Agent : Chaîne complète
        return $chain;
    }

    /**
     * Trouve l'employé validateur pour une étape donnée et une demande donnée
     * @param string $stepLibele Libellé de l'étape (ex: 'Chef de Service')
     * @param int $requesterEmpCode Code de l'employé demandeur
     * @return array|null Données de l'employé validateur (ou null si non trouvé)
     */
    public function getValidatorForStep(string $stepLibele, int $requesterEmpCode): ?array
    {
        $db = \Config\Database::connect();

        // Récupérer dir_code du demandeur
        $requester = $db->table('affectation')
            ->select('dir_code')
            ->where('emp_code', $requesterEmpCode)
            ->get()->getRowArray();
        
        $dirCode = $requester['dir_code'] ?? null;

        $targetSignCode = null;
        $filterByDir = false;

        switch ($stepLibele) {
            case 'Directeur General':
                $targetSignCode = self::SIGN_DG;
                break;
            case 'DAAF':
                $targetSignCode = self::SIGN_DAAF;
                break;
            case 'RRH':
                $targetSignCode = self::SIGN_RRH;
                break;
            case 'Directeur':
                $targetSignCode = $this->getDirectorSignCode($dirCode);
                $filterByDir = false; 
                break;
            case 'Chef de Service':
                $targetSignCode = $this->getChefSignCode($dirCode);
                $filterByDir = false; 
                break;
            default:
                return null;
        }

        // Rechercher l'employé
        // Rechercher l'employé via la table Signature
        $builder = $db->table('employee e')
            ->select('e.*') 
            ->join('signature s', 's.emp_code = e.emp_code')
            ->where('s.sign_code', $targetSignCode);

        // Si c'est un poste "local", on filtre par direction
        if ($filterByDir && $dirCode) {
            $builder->join('affectation a', 'a.emp_code = e.emp_code')
                    ->where('a.dir_code', $dirCode);
        }

        return $builder->get()->getRowArray();
    }

    /**
     * Envoie la notification de validation pour une étape donnée
     * @param array $conge Détails du congé
     * @param int $signCode Code de la signature (sign_code)
     * @return bool
     */
    public function sendValidationNotification(array $conge, int $signCode): bool
    {
        // 1. Récupérer la signature
        $signature = $this->signatureModel->find($signCode);
        if (!$signature) {
             log_message('error', "Signature introuvable pour sign_code=$signCode");
             return false;
        }
        
        $stepName = $signature['sign_libele'];
        
        // 2. Récupérer l'employé validateur directement depuis signature.emp_code
        $db = \Config\Database::connect();
        $validator = $db->table('employee')->where('emp_code', $signature['emp_code'])->get()->getRowArray();

        if (!$validator || empty($validator['emp_mail'])) {
            log_message('error', "Validateur introuvable pour sign_code=$signCode (emp_code={$signature['emp_code']})");
            return false;
        }

        // 3. Générer Token
        $token = EmailService::generateToken();
        
        // 4. Insérer dans validation_cng (Status en attente: null)
        $db->table('validation_cng')->insert([
            'cng_code' => $conge['cng_code'],
            'sign_code' => $signCode,
            'val_token' => $token,
            'val_token_expires' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'val_token_used' => false,
            'val_status' => null
        ]);

        // 5. Envoyer email (fail-safe: ne pas bloquer si échec)
        try {
            $emailSent = $this->emailService->sendValidationRequest(
                $validator['emp_mail'],
                $validator['emp_nom'] . ' ' . $validator['emp_prenom'],
                $conge,
                $token . '&action=approve',
                $token . '&action=reject'
            );
            
            if ($emailSent) {
                log_message('info', "[Validation] Email envoyé à {$validator['emp_mail']} pour étape '$stepName' (sign_code=$signCode)");
            } else {
                log_message('warning', "[Validation] Email non envoyé (mais validation enregistrée) pour '$stepName'");
            }
        } catch (\Throwable $e) {
            log_message('error', "[Validation] Erreur email: " . $e->getMessage() . " - Validation enregistrée malgré tout");
        }
        
        // IMPORTANT: Retour TRUE car la validation est créée dans la DB
        // L'email est OPTIONNEL - le workflow continue même si email échoue
        return true;
    }
    
    /**
     * Finds the Signature Code for the Chef de Service of a given Direction
     */
    private function getChefSignCode(int $dirCode): ?int
    {
        if (!$dirCode) return null;
        $db = \Config\Database::connect();
        // Post 3 = Chef de Service
        $row = $db->table('affectation a')
            ->select('s.sign_code')
            ->join('employee e', 'e.emp_code = a.emp_code')
            ->join('signature s', 's.emp_code = e.emp_code')
            ->where('a.dir_code', $dirCode)
            ->where('a.pst_code', 3) // Chef de Service
            ->get()->getRowArray();
        return $row ? (int)$row['sign_code'] : null;
    }

    /**
     * Finds the Signature Code for the Director of a given Direction
     */
    private function getDirectorSignCode(int $dirCode): ?int
    {
        if (!$dirCode) return null;
        $db = \Config\Database::connect();
        // Post 2 = Directeur
        $row = $db->table('affectation a')
            ->select('s.sign_code')
            ->join('employee e', 'e.emp_code = a.emp_code')
            ->join('signature s', 's.emp_code = e.emp_code')
            ->where('a.dir_code', $dirCode)
            ->where('a.pst_code', 2) // Directeur
            ->get()->getRowArray();
        return $row ? (int)$row['sign_code'] : null;
    }
}

