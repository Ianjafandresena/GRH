<?php
namespace App\Models\poste;
use CodeIgniter\Model;

class PosteModel extends Model
{
    protected $table = 'poste';
    protected $primaryKey = 'pst_code';
    protected $allowedFields = [
        'pst_fonction',
        'pst_mission',
        'tsup_code',
        'rhq_code',
        'srvc_code',
        'dir_code'
    ];

    public function getPostes($filters = [])
    {
        // 🔹 Solution PostgreSQL pour l'encoding
        $this->db->query("SET NAMES 'utf8'");
        $this->db->query("SET client_encoding TO 'UTF8'");

        $builder = $this->db->table('poste');
        $builder->select('poste.*, service.srvc_nom, 
                         COALESCE(d_direct.dir_nom, d_service.dir_nom) as dir_nom,
                         rang_hierarchique.rhq_niveau, rang_hierarchique.rhq_rang, 
                         COALESCE(occupation_poste.quota, 0) as quota,
                         COALESCE(occupation_poste.nb_occupe, 0) as nb_occupe,
                         COALESCE(occupation_poste.nb_vacant, 0) as nb_vacant,
                         COALESCE(occupation_poste.nb_encessation, 0) as nb_encessation')
            ->join('rang_hierarchique', 'poste.rhq_code = rang_hierarchique.rhq_code', 'left')
            ->join('service', 'poste.srvc_code = service.srvc_code', 'left')
            // Join for direct direction assignment
            ->join('direction as d_direct', 'poste.dir_code = d_direct.dir_code', 'left')
            // Join for service-based direction
            ->join('direction as d_service', 'service.dir_code = d_service.dir_code', 'left')
            ->join('occupation_poste', 'poste.pst_code = occupation_poste.pst_code', 'left');

        // Appliquer les filtres
        if (!empty($filters['pst_fonction'])) {
            $builder->like('poste.pst_fonction', $filters['pst_fonction']);
        }

        // Filtre par service : accepter soit srvc_code (ID) soit srvc_nom (nom)
        if (!empty($filters['srvc_code'])) {
            $builder->where('poste.srvc_code', $filters['srvc_code']);
        } elseif (!empty($filters['srvc_nom'])) {
            // Utiliser une comparaison exacte mais insensible à la casse pour le nom du service
            $builder->where('LOWER(service.srvc_nom)', strtolower(trim($filters['srvc_nom'])));
        }

        if (!empty($filters['dir_nom'])) {
            $builder->groupStart()
                ->like('d_direct.dir_nom', $filters['dir_nom'])
                ->orLike('d_service.dir_nom', $filters['dir_nom'])
                ->groupEnd();
        }



        // Filtre par rang : accepter soit rhq_code (ID) soit rhq_rang (nom)
        if (!empty($filters['rhq_code'])) {
            $builder->where('poste.rhq_code', $filters['rhq_code']);
        } elseif (!empty($filters['rhq_rang'])) {
            // Utiliser une comparaison exacte mais insensible à la casse pour le nom du rang
            $builder->where('LOWER(rang_hierarchique.rhq_rang)', strtolower(trim($filters['rhq_rang'])));
        }

        if (!empty($filters['statut'])) {
            $statut = strtolower(trim($filters['statut']));
            if ($statut === 'vacant') {
                $builder->where('occupation_poste.nb_vacant >', 0);
            } elseif ($statut === 'occupe' || $statut === 'occupé') {
                $builder->where('occupation_poste.nb_occupe >', 0);
            } elseif ($statut === 'en cessation' || $statut === 'cessation') {
                $builder->where('occupation_poste.nb_encessation >', 0);
            }
        }

        // Filtre postes disponibles (vacants ou cessation)
        if (!empty($filters['disponibles_only']) && $filters['disponibles_only'] === 'true') {
            $builder->groupStart()
                ->where('occupation_poste.nb_vacant >', 0)
                ->orWhere('occupation_poste.nb_encessation >', 0)
                ->groupEnd();
        }

        // No Group By needed anymore since we are not aggregating

        // Log pour débogage (à retirer en production)
        log_message('debug', 'Filtres appliqués: ' . json_encode($filters));
        log_message('debug', 'Requête SQL: ' . $builder->getCompiledSelect(false));

        $postes = $builder->get()->getResultArray();

        return $postes;
    }
}

