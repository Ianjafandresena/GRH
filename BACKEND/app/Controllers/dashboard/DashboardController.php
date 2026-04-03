<?php
namespace App\Controllers\dashboard;

use CodeIgniter\RESTful\ResourceController;

class DashboardController extends ResourceController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function index()
    {
        // Filtres potentiels
        $srvcCode = $this->request->getVar('srvc_code');
        $period = $this->request->getVar('period') ?? 'year'; // year, month

        $stats = [
            'kpi' => $this->getKpis($srvcCode),
            'charts' => [
                'contrats' => $this->getRepartContrats($srvcCode),
                'evolution' => $this->getEvolutionEffectifs($srvcCode),
                'ages' => $this->getRepartAge($srvcCode),
                'sexe' => $this->getRepartSexe($srvcCode)
            ],
            'lists' => [
                'fin_contrats' => $this->getFinContrats($srvcCode),
                'dernieres_affectations' => $this->getDernieresAffectations($srvcCode)
            ]
        ];

        return $this->respond($stats);
    }

    private function getKpis($srvcCode = null)
    {
        // 1. Total Postes
        $builderPoste = $this->db->table('poste');
        if ($srvcCode) {
            $builderPoste->where('srvc_code', $srvcCode);
        }
        $totalPostes = $builderPoste->countAllResults();

        // 2. Postes Occupés (Affectations actives)
        // Active = date_fin IS NULL OR date_fin >= today
        $today = date('Y-m-d');
        $builderOccupes = $this->db->table('affectation');
        $builderOccupes->join('poste', 'poste.pst_code = affectation.pst_code');

        $builderOccupes->groupStart()
            ->where('affec_date_fin >=', $today)
            ->orWhere('affec_date_fin', null)
            ->groupEnd();

        if ($srvcCode) {
            $builderOccupes->where('poste.srvc_code', $srvcCode);
        }
        $postesOccupes = $builderOccupes->countAllResults();

        // 3. Total Employés Actifs
        // Par défaut: employés dont date_sortie IS NULL.
        // Si un filtre service est appliqué: employés avec affectation active sur ce service.
        $totalEmployes = 0;
        if ($srvcCode) {
            $builderEmp = $this->db->table('affectation');
            $builderEmp->select('COUNT(DISTINCT affectation.emp_code) as total');
            $builderEmp->join('employe', 'employe.emp_code = affectation.emp_code');
            $builderEmp->join('poste', 'poste.pst_code = affectation.pst_code');
            $builderEmp->where('employe.date_sortie', null);
            $builderEmp->groupStart()
                ->where('affec_date_fin >=', $today)
                ->orWhere('affec_date_fin', null)
                ->groupEnd();
            $builderEmp->where('poste.srvc_code', $srvcCode);
            $resEmp = $builderEmp->get()->getRow();
            $totalEmployes = $resEmp ? $resEmp->total : 0;
        } else {
            $builderEmp = $this->db->table('employe');
            $builderEmp->select('COUNT(*) as total');
            $builderEmp->where('date_sortie', null);
            $resEmp = $builderEmp->get()->getRow();
            $totalEmployes = $resEmp ? $resEmp->total : 0;
        }

        // 4. Alertes Contrats (finissant dans les 30 jours)
        $date30Jours = date('Y-m-d', strtotime('+30 days'));

        $builderAlerts = $this->db->table('affectation');
        $builderAlerts->join('poste', 'poste.pst_code = affectation.pst_code');
        $builderAlerts->where('affec_date_fin >=', $today);
        $builderAlerts->where('affec_date_fin <=', $date30Jours);

        if ($srvcCode) {
            $builderAlerts->where('poste.srvc_code', $srvcCode);
        }
        $finContrats = $builderAlerts->countAllResults();

        return [
            'total_employes' => $totalEmployes,
            'total_postes' => $totalPostes,
            'postes_vacants' => max(0, $totalPostes - $postesOccupes),
            'taux_occupation' => $totalPostes > 0 ? round(($postesOccupes / $totalPostes) * 100) : 0,
            'alertes_contrats' => $finContrats
        ];
    }

    private function getRepartContrats($srvcCode = null)
    {
        $today = date('Y-m-d');
        $builder = $this->db->table('affectation')
            ->select('type_contrat.tcontrat_nom as name, COUNT(*) as value')
            ->join('poste', 'poste.pst_code = affectation.pst_code')
            ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code')
            ->groupStart()
            ->where('affec_date_fin >=', $today)
            ->orWhere('affec_date_fin', null)
            ->groupEnd()
            ->groupBy('type_contrat.tcontrat_nom');

        if ($srvcCode) {
            $builder->where('poste.srvc_code', $srvcCode);
        }

        return $builder->get()->getResultArray();
    }

    private function getEvolutionEffectifs($srvcCode = null)
    {
        $sql = "SELECT to_char(affec_date_debut, 'YYYY-MM') as month, COUNT(*) as count 
                FROM affectation 
                LEFT JOIN poste ON poste.pst_code = affectation.pst_code
                WHERE affec_date_debut >= CURRENT_DATE - INTERVAL '12 months' ";

        $params = [];

        if ($srvcCode) {
            $sql .= " AND poste.srvc_code = ? ";
            $params[] = $srvcCode;
        }

        $sql .= " GROUP BY month ORDER BY month ASC";

        return $this->db->query($sql, $params)->getResultArray();
    }

    private function getRepartSexe($srvcCode = null)
    {
        try {
            $params = [];

            if ($srvcCode) {
                $today = date('Y-m-d');
                $sql = "SELECT 
                            CASE 
                                WHEN e.emp_sexe IS TRUE THEN 'Homme'
                                WHEN e.emp_sexe IS FALSE THEN 'Femme'
                                ELSE 'Non renseigné'
                            END as name,
                            COUNT(DISTINCT e.emp_code) as value
                        FROM affectation a
                        JOIN employe e ON e.emp_code = a.emp_code
                        JOIN poste p ON p.pst_code = a.pst_code
                        WHERE e.date_sortie IS NULL
                          AND (a.affec_date_fin >= ? OR a.affec_date_fin IS NULL)
                          AND p.srvc_code = ?";
                $params = [$today, $srvcCode];
            } else {
                $sql = "SELECT 
                            CASE 
                                WHEN emp_sexe IS TRUE THEN 'Homme'
                                WHEN emp_sexe IS FALSE THEN 'Femme'
                                ELSE 'Non renseigné'
                            END as name,
                            COUNT(*) as value
                        FROM employe
                        WHERE date_sortie IS NULL";
            }

            $sql .= " GROUP BY 1 ORDER BY 1";

            $res = $this->db->query($sql, $params);
            if (!$res) {
                return [];
            }
            return $res->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[DashboardController] Erreur getRepartSexe: ' . $e->getMessage());
            return [];
        }
    }

    private function getRepartAge($srvcCode = null)
    {
        try {
            $params = [];

            if ($srvcCode) {
                $today = date('Y-m-d');
                $sql = "SELECT bucket as name, COUNT(*) as value
                        FROM (
                            SELECT DISTINCT e.emp_code,
                                CASE
                                    WHEN e.emp_datenaissance IS NULL THEN 'Non renseigné'
                                    WHEN date_part('year', age(CURRENT_DATE, e.emp_datenaissance)) < 25 THEN '<25'
                                    WHEN date_part('year', age(CURRENT_DATE, e.emp_datenaissance)) < 35 THEN '25-34'
                                    WHEN date_part('year', age(CURRENT_DATE, e.emp_datenaissance)) < 45 THEN '35-44'
                                    WHEN date_part('year', age(CURRENT_DATE, e.emp_datenaissance)) < 55 THEN '45-54'
                                    ELSE '55+'
                                END as bucket
                            FROM affectation a
                            JOIN employe e ON e.emp_code = a.emp_code
                            JOIN poste p ON p.pst_code = a.pst_code
                            WHERE e.date_sortie IS NULL
                              AND (a.affec_date_fin >= ? OR a.affec_date_fin IS NULL)
                              AND p.srvc_code = ?";
                $params = [$today, $srvcCode];
            } else {
                $sql = "SELECT bucket as name, COUNT(*) as value
                        FROM (
                            SELECT
                                CASE
                                    WHEN emp_datenaissance IS NULL THEN 'Non renseigné'
                                    WHEN date_part('year', age(CURRENT_DATE, emp_datenaissance)) < 25 THEN '<25'
                                    WHEN date_part('year', age(CURRENT_DATE, emp_datenaissance)) < 35 THEN '25-34'
                                    WHEN date_part('year', age(CURRENT_DATE, emp_datenaissance)) < 45 THEN '35-44'
                                    WHEN date_part('year', age(CURRENT_DATE, emp_datenaissance)) < 55 THEN '45-54'
                                    ELSE '55+'
                                END as bucket
                            FROM employe
                            WHERE date_sortie IS NULL
                        ) t";
            }

            $sql .= "
                    GROUP BY bucket
                    ORDER BY CASE bucket
                        WHEN '<25' THEN 1
                        WHEN '25-34' THEN 2
                        WHEN '35-44' THEN 3
                        WHEN '45-54' THEN 4
                        WHEN '55+' THEN 5
                        ELSE 6
                    END";

            $res = $this->db->query($sql, $params);
            if (!$res) {
                return [];
            }
            return $res->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[DashboardController] Erreur getRepartAge: ' . $e->getMessage());
            return [];
        }
    }

    private function getFinContrats($srvcCode = null)
    {
        $date30Jours = date('Y-m-d', strtotime('+30 days'));
        $today = date('Y-m-d');

        $builder = $this->db->table('affectation')
            ->select('affectation.*, employe.emp_nom, employe.emp_prenom, poste.pst_fonction, type_contrat.tcontrat_nom')
            ->join('employe', 'employe.emp_code = affectation.emp_code')
            ->join('poste', 'poste.pst_code = affectation.pst_code')
            ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code', 'left')
            ->where('affec_date_fin >=', $today)
            ->where('affec_date_fin <=', $date30Jours);

        if ($srvcCode) {
            $builder->where('poste.srvc_code', $srvcCode);
        }

        return $builder->limit(5)
            ->get()
            ->getResultArray();
    }

    private function getDernieresAffectations($srvcCode = null)
    {
        $builder = $this->db->table('affectation')
            ->select('affectation.*, employe.emp_nom, employe.emp_prenom, poste.pst_fonction, type_contrat.tcontrat_nom')
            ->join('employe', 'employe.emp_code = affectation.emp_code')
            ->join('poste', 'poste.pst_code = affectation.pst_code')
            ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code', 'left')
            ->orderBy('affec_date_debut', 'DESC');

        if ($srvcCode) {
            $builder->where('poste.srvc_code', $srvcCode);
        }

        return $builder->limit(5)
            ->get()
            ->getResultArray();
    }
}

