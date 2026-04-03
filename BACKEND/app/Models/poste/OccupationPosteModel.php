<?php

namespace App\Models\poste;

use CodeIgniter\Model;

class OccupationPosteModel extends Model
{
    protected $table = 'occupation_poste';
    protected $primaryKey = 'occpst_code';
    protected $allowedFields = [
        'pst_code',
        'quota',
        'nb_occupe',
        'nb_vacant',
        'nb_encessation'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $useAutoIncrement = true;

    /**
     * Récupérer ou créer l'enregistrement d'occupation pour un poste
     */
    public function getOrCreate($pstCode)
    {
        // Utiliser une requête directe pour éviter les problèmes avec first()
        $db = \Config\Database::connect();
        $result = $db->table($this->table)
            ->where('pst_code', $pstCode)
            ->get();
        
        $occupation = null;
        if ($result && $result !== false) {
            $occupation = $result->getRowArray();
        }
        
        if (!$occupation) {
            // Compter les affectations actives réelles
            $nbOccupeReel = $db->table('affectation')
                ->where('pst_code', $pstCode)
                ->where('affec_date_fin IS NULL')
                ->countAllResults();
            
            // Créer un nouvel enregistrement avec valeurs par défaut
            $data = [
                'pst_code' => $pstCode,
                'quota' => 0,
                'nb_occupe' => $nbOccupeReel,
                'nb_vacant' => 0,
                'nb_encessation' => 0
            ];
            
            // Insérer en utilisant une requête directe
            $insertResult = $db->table($this->table)->insert($data);
            
            if ($insertResult === false) {
                // Si l'insertion échoue (peut-être à cause d'une contrainte UNIQUE), réessayer de récupérer
                $result = $db->table($this->table)
                    ->where('pst_code', $pstCode)
                    ->get();
                
                if ($result && $result !== false) {
                    $occupation = $result->getRowArray();
                }
            } else {
                // Récupérer l'enregistrement nouvellement créé
                $result = $db->table($this->table)
                    ->where('pst_code', $pstCode)
                    ->get();
                
                if ($result && $result !== false) {
                    $occupation = $result->getRowArray();
                }
            }
            
            if (!$occupation) {
                throw new \RuntimeException("Impossible de créer ou récupérer l'enregistrement d'occupation pour le poste {$pstCode}");
            }
        }
        
        return $occupation;
    }

    /**
     * Incrémenter le nombre de postes occupés
     */
    public function incrementerOccupe($pstCode)
    {
        $occupation = $this->getOrCreate($pstCode);
        $newNbOccupe = $occupation['nb_occupe'] + 1;
        
        // Décrémenter vacant seulement s'il y en a, sinon laisser tel quel
        $newNbVacant = $occupation['nb_vacant'] > 0 ? $occupation['nb_vacant'] - 1 : $occupation['nb_vacant'];
        
        $this->update($occupation['occpst_code'], [
            'nb_occupe' => $newNbOccupe,
            'nb_vacant' => $newNbVacant
        ]);
    }

    /**
     * Décrémenter le nombre de postes occupés et incrémenter vacant ou cessation
     */
    public function decrementerOccupe($pstCode, $type = 'vacant')
    {
        $occupation = $this->getOrCreate($pstCode);
        
        $data = [
            'nb_occupe' => max(0, $occupation['nb_occupe'] - 1)
        ];
        
        if ($type === 'vacant') {
            $data['nb_vacant'] = $occupation['nb_vacant'] + 1;
        } elseif ($type === 'cessation') {
            $data['nb_encessation'] = $occupation['nb_encessation'] + 1;
        }
        
        $this->update($occupation['occpst_code'], $data);
    }

    /**
     * Synchroniser les compteurs avec les affectations réelles
     */
    public function synchroniser($pstCode)
    {
        $db = \Config\Database::connect();
        
        // Compter les affectations actives
        $nbOccupe = $db->table('affectation')
            ->where('pst_code', $pstCode)
            ->where('affec_date_fin IS NULL')
            ->countAllResults();
        
        // Récupérer ou créer l'enregistrement
        $occupation = $this->getOrCreate($pstCode);
        
        // Calculer vacant et cessation (si quota est défini)
        $quota = $occupation['quota'] ?? 0;
        $nbVacant = max(0, $quota - $nbOccupe - ($occupation['nb_encessation'] ?? 0));
        
        $this->update($occupation['occpst_code'], [
            'nb_occupe' => $nbOccupe,
            'nb_vacant' => $nbVacant
        ]);
    }
}

