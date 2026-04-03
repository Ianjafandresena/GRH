<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class HierarchySeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        // 1. Mise à jour des abréviations des directions (nécessaire pour la logique de filtrage)
        $directions = [
            1 => 'DG',
            2 => 'CRR',
            3 => 'CRD',
            4 => 'DAI',
            5 => 'DAAF',
            6 => 'DFD',
            7 => 'DSI'
        ];

        foreach ($directions as $id => $abbr) {
            $db->table('direction')->where('dir_code', $id)->update(['dir_abbreviation' => $abbr]);
        }

        // 2. Création des employés clés de la hiérarchie
        $hierarchy = [
            // DG (Sign Code 1)
            [
                'nom' => 'RAKOTO', 'prenom' => 'Jean DG', 'im' => '001DG', 'mail' => 'dg@armp.mg',
                'pst' => 1, 'sign_code' => 1, 'sign_lib' => 'Directeur Général'
            ],
            // DAAF (Sign Code 2)
            [
                'nom' => 'RANDRIA', 'prenom' => 'Pierre DAAF', 'im' => '002DAAF', 'mail' => 'daaf@armp.mg',
                'pst' => 5, 'sign_code' => 2, 'sign_lib' => 'DAAF'
            ],
            // RRH (Sign Code 3)
            [
                'nom' => 'ANDRIA', 'prenom' => 'Marie RRH', 'im' => '003RRH', 'mail' => 'rrh@armp.mg',
                'pst' => 9, 'sign_code' => 3, 'sign_lib' => 'RRH'
            ],
            // Chef de Service SASR (Sign Code 4 - Exemple pour DSI)
            [
                'nom' => 'SOAN', 'prenom' => 'Alice Chef', 'im' => '004CHEF', 'mail' => 'chef.sasr@armp.mg',
                'pst' => 16, 'sign_code' => 4, 'sign_lib' => 'Chef de Service'
            ],
            // Directeur DSI (Sign Code 5 - Exemple pour DSI)
            [
                'nom' => 'FENO', 'prenom' => 'Marc Dir', 'im' => '005DIR', 'mail' => 'dir.dsi@armp.mg',
                'pst' => 7, 'sign_code' => 5, 'sign_lib' => 'Directeur'
            ]
        ];

        foreach ($hierarchy as $h) {
            // Créer Employé
            $db->table('employe')->insert([
                'emp_nom' => $h['nom'],
                'emp_prenom' => $h['prenom'],
                'emp_im_armp' => $h['im'],
                'emp_mail' => $h['mail'],
                'emp_disponibilite' => true,
                'date_entree' => '2020-01-01'
            ]);
            $empCode = $db->insertID();

            // Créer Affectation Active
            $db->table('affectation')->insert([
                'emp_code' => $empCode,
                'pst_code' => $h['pst'],
                'affec_date_debut' => '2020-01-01',
                'affec_etat' => 'active'
            ]);

            // Créer Signature (LIEN CRUCIAL)
            $db->table('signature')->insert([
                'sign_code' => $h['sign_code'],
                'sign_libele' => $h['sign_lib'],
                'emp_code' => $empCode
            ]);

            // Créer User correspondante
            $db->table('users')->insert([
                'username' => strtolower($h['nom']),
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'nom' => $h['nom'],
                'prenom' => $h['prenom'],
                'role' => 'validator'
            ]);
        }

        // Reset sequence signatures
        $db->query("SELECT setval('signature_sign_code_seq', 10)");
    }
}
