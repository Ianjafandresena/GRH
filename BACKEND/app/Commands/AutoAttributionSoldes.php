<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\conge\DecisionModel;
use App\Models\conge\SoldeCongeModel;
use App\Models\employee\EmployeeModel;

/**
 * Auto-Attribution Annuelle des Soldes de Congé
 * 
 * Exécution: php spark soldes:auto-attribution
 * CRON: 0 0 1 1 * cd /path/to/BACKEND && php spark soldes:auto-attribution >> /var/log/grh-auto-soldes.log 2>&1
 */
class AutoAttributionSoldes extends BaseCommand
{
    protected $group       = 'cron';
    protected $name        = 'soldes:auto-attribution';
    protected $description = 'Génère automatiquement les décisions et soldes annuels (1er Janvier)';
    
    // Configuration
    private const SOLDE_INITIAL = 30.0;
    
    public function run(array $params)
    {
        CLI::write('========================================', 'cyan');
        CLI::write('  AUTO-ATTRIBUTION SOLDES ANNUELS', 'cyan');
        CLI::write('========================================', 'cyan');
        CLI::newLine();
        
        $startTime = microtime(true);
        
        try {
            // 1. Année courante
            $currentYear = (int)date('Y');
            $soldeYear = $currentYear - 1; // Règle: décision YY pour soldes YY-1
            
            CLI::write("📅 Année courante: $currentYear", 'yellow');
            CLI::write("🎯 Soldes à générer: $soldeYear", 'yellow');
            CLI::newLine();
            
            // 2. Vérifier si déjà effectué (FAIL-SAFE: Idempotence)
            if ($this->isAlreadyGenerated($currentYear, $soldeYear)) {
                CLI::error("⚠️  Attribution $currentYear déjà effectuée !");
                CLI::write("Aucune action nécessaire.", 'yellow');
                return;
            }
            
            // 3. Récupérer employés actifs
            CLI::write('[1/2] Récupération employés actifs...', 'cyan');
            $employees = $this->getActiveEmployees();
            CLI::write("  ✓ " . count($employees) . " employés trouvés", 'green');
            CLI::newLine();
            
            // 4. Commencer transaction atomique
            $db = \Config\Database::connect();
            $db->transStart();
            
            // 5. Créer décisions ET soldes (1 décision par employé)
            CLI::write('[2/2] Création décisions et attribution soldes...', 'cyan');
            $count = $this->createDecisionsAndSoldes($employees, $currentYear, $soldeYear);
            CLI::write("  ✓ $count décisions et soldes créés", 'green');
            CLI::newLine();
            
            // 6. Commit transaction
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \RuntimeException("Échec transaction database");
            }
            
            // Log succès
            $this->logSuccess($currentYear, $count, $count);
            
            $duration = round(microtime(true) - $startTime, 2);
            
            CLI::write('========================================', 'green');
            CLI::write('  ✅  SUCCÈS !', 'green');
            CLI::write('========================================', 'green');
            CLI::write("Décisions: $count", 'white');
            CLI::write("Soldes: $count", 'white');
            CLI::write("Durée: {$duration}s", 'white');
            
        } catch (\Exception $e) {
            // FAIL-SAFE: Rollback + Log
            if (isset($db)) {
                $db->transRollback();
            }
            
            $this->logError($e);
            
            CLI::error('========================================');
            CLI::error('  ❌  ERREUR !');
            CLI::error('========================================');
            CLI::error($e->getMessage());
            CLI::error("Fichier: " . $e->getFile() . ":" . $e->getLine());
            
            // Ne pas crasher le système parent
            return;
        }
    }
    
    /**
     * Vérifier si attribution déjà effectuée (Idempotence)
     */
    private function isAlreadyGenerated(int $year, int $soldeYear): bool
    {
        $soldeModel = new SoldeCongeModel();
        
        // Vérifier si soldes pour cette année existent déjà
        $exists = $soldeModel
            ->where('sld_anne', $soldeYear)
            ->first();
        
        return $exists !== null;
    }
    
    /**
     * Créer 1 décision + 1 solde par employé
     */
    private function createDecisionsAndSoldes(array $employees, int $currentYear, int $soldeYear): int
    {
        $decisionModel = new DecisionModel();
        $soldeModel = new SoldeCongeModel();
        $yearSuffix = str_pad($currentYear % 100, 2, '0', STR_PAD_LEFT); // 2026 → 26
        $count = 0;
        $totalEmployees = count($employees);
        
        foreach ($employees as $index => $employee) {
            $empCode = $employee['emp_code'];
            
            // 1. Créer décision unique pour cet employé
            $decNum = sprintf('%03d/ARMP/DG-%s', $empCode, $yearSuffix);
            
            $decisionId = $decisionModel->insert([
                'dec_num' => $decNum
            ]);
            
            if ($decisionId === false) {
                throw new \RuntimeException("Impossible de créer décision pour employé $empCode");
            }
            
            // 2. Créer solde lié à cette décision
            $inserted = $soldeModel->insert([
                'sld_dispo' => 1,
                'sld_anne' => $soldeYear,
                'sld_initial' => self::SOLDE_INITIAL,
                'sld_restant' => self::SOLDE_INITIAL,
                'sld_maj' => date('Y-m-d H:i:s'),
                'emp_code' => $empCode,
                'dec_code' => $decisionId
            ]);
            
            if ($inserted === false) {
                throw new \RuntimeException("Impossible d'attribuer solde pour employé $empCode");
            }
            
            $count++;
            
            // Afficher progression tous les 5 employés
            if ($count % 5 == 0 || $count == $totalEmployees) {
                $percent = round(($count / $totalEmployees) * 100);
                CLI::write("  → Employé $empCode: $decNum", 'white');
                CLI::write("  Progression: $count/$totalEmployees ($percent%)", 'white');
            }
        }
        
        return $count;
    }
    
    /**
     * Récupérer tous employés actifs
     */
    private function getActiveEmployees(): array
    {
        $employeeModel = new EmployeeModel();
        
        $employees = $employeeModel
            ->where('emp_disponibilite', true)
            ->findAll();
        
        if (empty($employees)) {
            throw new \RuntimeException("Aucun employé actif trouvé");
        }
        
        return $employees;
    }
    
    /**
     * Logger succès
     */
    private function logSuccess(int $year, int $decisions, int $soldes): void
    {
        log_message('info', sprintf(
            '[AutoAttribution] Succès année %d: %d décisions, %d soldes créés',
            $year,
            $decisions,
            $soldes
        ));
    }
    
    /**
     * Logger erreur (FAIL-SAFE)
     */
    private function logError(\Exception $e): void
    {
        log_message('error', sprintf(
            '[AutoAttribution] ERREUR: %s | Fichier: %s:%d | Trace: %s',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }
}
