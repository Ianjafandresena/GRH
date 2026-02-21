# Référence API Backend

## Authentification
- **POST `/api/auth/login`**: Authentification et retour d'un token JWT.

## Congés et Absences
- **GET `/api/conge`**: Liste des congés filtrables.
- **POST `/api/conge`**: Création d'une nouvelle demande.
- **GET `/api/conge/solde/(:num)`**: Récupère le solde FIFO détaillé d'un agent.
- **POST `/api/conge/validate/(:num)`**: Valider une étape de congé.

## Remboursements
- **GET `/api/remboursement/etats`**: Liste des états de remboursement.
- **POST `/api/remboursement/mandater/(:num)`**: Passage en statut MANDATE.
- **POST `/api/remboursement/comptable/(:num)`**: Passage en statut AGENT_COMPTABLE.
- **GET `/api/remboursement/pdf/(:num)`**: Génère le bordereau PDF.

## Employés
- **GET `/api/employee`**: Liste du personnel.
- **GET `/api/employee/(:num)`**: Détails complets d'un agent.

## Chatbot
- **POST `/api/chatbot/message`**: Envoi d'un message au chatbot intelligent.
- **GET `/api/chatbot/suggestions`**: Récupère des suggestions contextuelles.
