<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class DemandeRembModel extends Model
{
    protected $table      = 'demande_remb';
    protected $primaryKey = 'rem_code';

    protected $allowedFields = [
        'num_demande',
        'rem_objet',
        'rem_date',
        'rem_montant',
        'rem_montant_lettre',
        'nom_malade',
        'lien_malade',
        'has_ordonnance',
        'has_facture',
        'has_prise_en_charge',
        'pec_reference',
        'date_consultation',
        'montant_valide',
        'motif_rejet',
        'num_engagement',
        'date_engagement',
        'date_paiement',
        'emp_code',
        'pec_code',
        'eta_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
