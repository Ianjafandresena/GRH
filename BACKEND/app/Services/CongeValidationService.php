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
        $emp = $db->table('employe e')
            ->select('e.emp_code, p.pst_fonction, p.dir_code, p.srvc_code, d.dir_abbreviation')
            ->join('affectation a', "a.emp_code = e.emp_code AND a.affec_etat = 'active'", 'left')
            ->join('poste p', 'p.pst_code = a.pst_code', 'left')
            ->join('direction d', 'd.dir_code = p.dir_code', 'left')
            ->where('e.emp_code', $empCode)
            ->get()->getRowArray();

        if (!$emp || !$emp['pst_fonction']) {
            log_message('warning', "Employé $empCode n'a pas de poste ou d'affectation active.");
            return [
                ['step' => 'Chef de Service', 'code' => self::SIGN_CHEF],
                ['step' => 'RRH', 'code' => self::SIGN_RRH],
                ['step' => 'DAAF', 'code' => self::SIGN_DAAF],
                ['step' => 'Directeur General', 'code' => self::SIGN_DG]
            ];
        }

        $poste = strtoupper($emp['pst_fonction']);
        $dirAbbr = strtoupper($emp['dir_abbreviation'] ?? '');
        $dirCode = (int)($emp['dir_code'] ?? 0);

        // 2. Définir la chaîne complète avec CODES DYNAMIQUES
        $chefCode = $this->getChefSignCode($dirCode) ?? self::SIGN_CHEF;
        $dirCodeSign = $this->getDirectorSignCode($dirCode) ?? self::SIGN_DIRECTEUR;

        $chain = [
            ['step' => 'Chef de Service', 'code' => $chefCode],
            ['step' => 'Directeur', 'code' => $dirCodeSign], 
            ['step' => 'RRH', 'code' => self::SIGN_RRH], 
            ['step' => 'DAAF', 'code' => self::SIGN_DAAF], 
            ['step' => 'Directeur General', 'code' => self::SIGN_DG]
        ];

        // 3. Adapter la chaîne selon le Poste et la Direction
        
        // Si Direction = DAAF, Directeur = DAAF. Retirer 'Directeur'
        if ($dirAbbr === 'DAAF') {
             $chain = array_values(array_filter($chain, fn($s) => $s['step'] !== 'Directeur'));
        }

        // Si Direction = RH, Directeur = RRH. Retirer 'Directeur'
        if ($dirAbbr === 'SRH' || $dirAbbr === 'RH') {
             $chain = array_values(array_filter($chain, fn($s) => $s['step'] !== 'Directeur'));
        }

        // 4. Filtrer selon le Poste (Point de départ)
        
        // Si le DG demande un congé, aucune validation nécessaire
        if ($poste === 'DIRECTEUR GÉNÉRAL' || str_contains($poste, 'DIRECTEUR GÉNÉRAL')) {
            return [];
        }

        // --- NOUVELLE LOGIQUE : FILTRAGE DYNAMIQUE POUR ÉVITER L'AUTO-VALIDATION ---
        
        $filteredChain = [];
        foreach ($chain as $s) {
            $skip = false;
            
            // Si c'est un Directeur (comme le DAAF ou DSI) qui demande :
            // 1. Il ne se valide pas lui-même en tant que "Directeur" local (code 5)
            // 2. Si c'est le DAAF réel (emp_code 3), il ne valide pas l'étape DAAF (code 2)
            // 3. Si c'est le RRH réel (emp_code 5), il ne valide pas l'étape RRH (code 3)
            
            if (str_contains($poste, 'DIRECTEUR')) {
                // Un directeur ne passe pas par l'étape "Chef de Service" ni "Directeur" (lui-même)
                if (in_array($s['step'], ['Chef de Service', 'Directeur'])) {
                    $skip = true;
                }
            }
            
            if (str_contains($poste, 'CHEF DE SERVICE') || str_contains($poste, 'CHEF')) {
                // Un chef ne passe pas par l'étape "Chef de Service" (lui-même)
                if ($s['step'] === 'Chef de Service') {
                    $skip = true;
                }
            }

            // Sécurité supplémentaire : si l'employé est précisément celui désigné pour cette signature globale
            $validator = $this->getValidatorForStep($s['step'], $empCode);
            if ($validator && (int)$validator['emp_code'] === $empCode) {
                $skip = true;
            }

            if (!$skip) {
                $filteredChain[] = $s;
            }
        }

        return array_values($filteredChain);
    }

    /**
     * Trouve l'employé validateur pour une étape donnée et une demande donnée
     */
    public function getValidatorForStep(string $stepLibele, int $requesterEmpCode): ?array
    {
        $db = \Config\Database::connect();

        // Récupérer dir_code du demandeur
        $emp = $db->table('employe e')
            ->select('p.dir_code')
            ->join('affectation a', "a.emp_code = e.emp_code AND a.affec_etat = 'active'", 'left')
            ->join('poste p', 'p.pst_code = a.pst_code', 'left')
            ->where('e.emp_code', $requesterEmpCode)
            ->get()->getRowArray();
        
        $dirCode = $emp ? (int)$emp['dir_code'] : null;

        $targetSignCode = null;

        switch ($stepLibele) {
            case 'Directeur General': $targetSignCode = self::SIGN_DG; break;
            case 'DAAF': $targetSignCode = self::SIGN_DAAF; break;
            case 'RRH': $targetSignCode = self::SIGN_RRH; break;
            case 'Directeur': $targetSignCode = $this->getDirectorSignCode($dirCode); break;
            case 'Chef de Service': $targetSignCode = $this->getChefSignCode($dirCode); break;
            default: return null;
        }

        if (!$targetSignCode) return null;

        return $db->table('employe e')
            ->select('e.*') 
            ->join('signature s', 's.emp_code = e.emp_code')
            ->where('s.sign_code', $targetSignCode)
            ->get()->getRowArray();
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
             // Fallback: si on n'a pas la signature, on ne peut pas notifier
             return false;
        }
        
        $stepName = $signature['sign_libele'];
        
        // 2. Récupérer l'employé validateur
        $db = \Config\Database::connect();
        $validator = $db->table('employe')->where('emp_code', $signature['emp_code'])->get()->getRowArray();

        if (!$validator || empty($validator['emp_mail'])) {
            log_message('warning', "Validateur introuvable ou sans email pour sign_code=$signCode (emp_code={$signature['emp_code']})");
            // On insère quand même la ligne de validation pour que l'admin puisse débloquer via l'interface
        }

        // 3. Générer Token
        $token = bin2hex(random_bytes(32));
        
        // 4. Insérer dans validation_cng (Status en attente: null)
        $db->table('validation_cng')->insert([
            'cng_code' => $conge['cng_code'],
            'sign_code' => $signCode,
            'val_token' => $token,
            'val_token_expires' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'val_token_used' => false,
            'val_status' => null
        ]);

        // 5. Envoyer email
        if ($validator && !empty($validator['emp_mail'])) {
            try {
                $this->emailService->sendValidationRequest(
                    $validator['emp_mail'],
                    $validator['emp_nom'] . ' ' . $validator['emp_prenom'],
                    $conge,
                    $token . '&action=approve',
                    $token . '&action=reject'
                );
            } catch (\Throwable $e) {
                log_message('error', "[Validation] Erreur email: " . $e->getMessage());
            }
        }
        
        return true;
    }
    
    /**
     * Finds the Signature Code for the Chef de Service of a given Direction
     */
    private function getChefSignCode(?int $dirCode): ?int
    {
        if (!$dirCode) return null;
        $db = \Config\Database::connect();
        $row = $db->table('signature s')
            ->select('s.sign_code')
            ->join('affectation a', 'a.emp_code = s.emp_code AND a.affec_etat = \'active\'')
            ->join('poste p', 'p.pst_code = a.pst_code')
            ->where('p.dir_code', $dirCode)
            ->groupStart()
                ->like('p.pst_fonction', 'Chef', 'after')
                ->orLike('p.pst_fonction', 'Responsable', 'after')
            ->groupEnd()
            ->get()->getRowArray();
        return $row ? (int)$row['sign_code'] : null;
    }

    /**
     * Finds the Signature Code for the Director of a given Direction
     */
    private function getDirectorSignCode(?int $dirCode): ?int
    {
        if (!$dirCode) return null;
        $db = \Config\Database::connect();
        $row = $db->table('signature s')
            ->select('s.sign_code')
            ->join('affectation a', 'a.emp_code = s.emp_code AND a.affec_etat = \'active\'')
            ->join('poste p', 'p.pst_code = a.pst_code')
            ->where('p.dir_code', $dirCode)
            ->like('p.pst_fonction', 'Directeur', 'after')
            ->notLike('p.pst_fonction', 'Directeur Général')
            ->get()->getRowArray();
        return $row ? (int)$row['sign_code'] : null;
    }

    /**
     * Calcul et enregistrement du débit de solde (FIFO)
     */
    public function debitSolde(array $conge): void
    {
        $db = \Config\Database::connect();
        $joursRestantsADeduire = (float)$conge['cng_nb_jour'];
        $empCode = (int)$conge['emp_code'];

        // 1. Récupérer les soldes positifs (FIFO)
        $soldes = $db->table('solde_conge')
                     ->where('emp_code', $empCode)
                     ->where('sld_restant >', 0)
                     ->orderBy('sld_anne', 'ASC')
                     ->get()->getResultArray();

        foreach ($soldes as $solde) {
            if ($joursRestantsADeduire <= 0) break;

            $dispo = (float)$solde['sld_restant'];
            $deduction = min($dispo, $joursRestantsADeduire);

            if ($deduction > 0) {
                // a. Insert Mouvement Débit
                $db->table('debit_solde_cng')->insert([
                    'emp_code' => $empCode,
                    'deb_date' => date('Y-m-d'),
                    'deb_jr' => $deduction,
                    'cng_code' => $conge['cng_code'],
                    'sld_code' => $solde['sld_code']
                ]);

                // b. Update Solde Restant
                $nouveauReste = $dispo - $deduction;
                $db->table('solde_conge')
                   ->where('sld_code', $solde['sld_code'])
                   ->update(['sld_restant' => $nouveauReste]);

                $joursRestantsADeduire -= $deduction;
            }
        }
    }
}

