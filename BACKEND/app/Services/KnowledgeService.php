<?php

namespace App\Services;

/**
 * KnowledgeService - Système RAG (Retrieval-Augmented Generation) simplifié
 * Fournit le contexte du projet à l'IA à partir des fichiers Markdown de KnowledgeBase
 */
class KnowledgeService
{
    private string $kbPath;

    public function __construct()
    {
        $this->kbPath = APPPATH . 'KnowledgeBase/';
    }

    /**
     * Recherche les sections pertinentes de la base de connaissances
     */
    public function getContext(string $query, bool $includeTech = false): string
    {
        $publicFiles = [
            'project_overview.md',
            'conge_rules.md',
            'remboursement_rules.md',
            'user_manual.md'
        ];

        $techFiles = [
            'database_guide.md',
            'frontend_architecture.md',
            'api_reference.md',
            'dev_guide.md'
        ];

        $files = $includeTech ? array_merge($publicFiles, $techFiles) : $publicFiles;

        $relevantContext = [];
        $queryWords = $this->tokenize($query);

        foreach ($files as $filename) {
            $filePath = $this->kbPath . $filename;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                
                // Analyse simple de pertinence
                foreach ($queryWords as $word) {
                    if (str_contains(strtolower($content), strtolower($word))) {
                        $relevantContext[] = $content;
                        break; // Ajouter le fichier une seule fois s'il est pertinent
                    }
                }
            }
        }

        // Si rien n'est trouvé, donner l'aperçu projet par défaut
        if (empty($relevantContext)) {
            $overview = $this->kbPath . 'project_overview.md';
            return file_exists($overview) ? file_get_contents($overview) : "";
        }

        return implode("\n\n---\n\n", $relevantContext);
    }

    /**
     * Nettoyage et découpage de la requête
     */
    private function tokenize(string $query): array
    {
        $query = strtolower($query);
        // Supprimer les mots de liaison communs
        $stopWords = ['le', 'la', 'les', 'de', 'du', 'un', 'une', 'des', 'est', 'sont', 'comment', 'pour', 'quoi'];
        $words = explode(' ', $query);
        
        return array_filter($words, function($w) use ($stopWords) {
            return strlen($w) > 2 && !in_array($w, $stopWords);
        });
    }
}
