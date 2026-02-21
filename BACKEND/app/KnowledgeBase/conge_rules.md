# Gestion des Congés et Absences

## Règle FIFO (First-In First-Out)
Le système applique la règle FIFO pour le débit des congés :
- Les jours de congés sont débités en priorité sur le solde de l'année la plus ancienne disponible.
- Un employé possède plusieurs lignes de solde (un par année de décision).
- Exemple: Si un employé a 30 jours de 2023 et 30 jours de 2024, une demande de 40 jours prendra les 30 jours de 2023 et 10 jours de 2024.

## Types d'Absences
- **Congé Payé**: Soumis à un solde, nécessite une décision administrative.
- **Permission**: Absence de courte durée (heures ou jours), souvent pour motifs familiaux ou exceptionnels.

## Flux de Validation
1. Soumission par l'employé ou l'admin.
2. Validation par les supérieurs hiérarchiques (Direction, etc.).
3. Une fois validée (status = true), le solde est officiellement impacté.
