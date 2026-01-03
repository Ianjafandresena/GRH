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
                     "La prise en charge définit :\n" .
                     "• **Couverture** : Pourcentage remboursé (ex: 80%)\n" .
                     "• **Plafond** : Montant maximum par période\n" .
                     "• **Bénéficiaires** : Employés et ayants-droit couverts\n\n" .
                     "**Fonctionnalités :**\n" .
                     "• Consulter les PEC actives\n" .
                     "• Vérifier plafonds restants\n" .
                     "• Gérer bénéficiaires",
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
                     "3. Définissez :\n" .
                     "   • Pourcentage de couverture (ex: 80%)\n" .
                     "   • Plafond annuel (ex: 500 000 Ar)\n" .
                     "   • Période de validité\n" .
                     "4. Ajoutez les bénéficiaires (employé + ayants-droit)\n" .
                     "5. Enregistrez !\n\n" .
                     "💡 La PEC s'applique automatiquement lors du calcul des remboursements.",
            'suggestions' => ['Remboursements', 'Liste PEC'],
            'actions' => [
                ['label' => 'Gérer PEC', 'route' => '/remboursement/pec', 'auto' => true]
            ]
        ];
    }
