<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Dompdf\Dompdf;
use Dompdf\Options;

class EtatPdfController extends ResourceController
{
    use ResponseTrait;

    /**
     * Générer le PDF d'un État de Remboursement (Agent)
     * Format EXACT conforme au document officiel ARMP
     */
    public function generateEtatPdf($etaCode)
    {
        $db = \Config\Database::connect();
        
        // 1. Récupérer l'état avec infos employé
        $etat = $db->table('etat_remb')
            ->select('etat_remb.*, 
                      employee.emp_nom, 
                      employee.emp_prenom, 
                      employee.emp_imarmp,
                      poste.pst_fonction,
                      direction.dir_nom,
                      direction.dir_abreviation')
            ->join('employee', 'employee.emp_code = etat_remb.emp_code', 'left')
            ->join('affectation', 'affectation.emp_code = employee.emp_code', 'left')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('direction', 'direction.dir_code = affectation.dir_code', 'left')
            ->where('etat_remb.eta_code', $etaCode)
            ->orderBy('affectation.affec_date_debut', 'DESC')
            ->get()->getRowArray();
        
        if (!$etat) {
            return $this->failNotFound('État non trouvé');
        }
        
        // 2. Récupérer toutes les demandes liées
        $demandes = $db->table('demande_remb')
            ->select('demande_remb.*,
                      facture.fac_num,
                      facture.fac_date,
                      objet_remboursement.obj_article,
                      pris_en_charge.pec_num,
                      centre_sante.cen_nom,
                      COALESCE(pec_employee.emp_nom || \' \' || pec_employee.emp_prenom, pec_conj.conj_nom, pec_enf.enf_nom) AS beneficiaire_nom,
                      CASE 
                        WHEN pris_en_charge.conj_code IS NOT NULL THEN \'Conjoint(e)\'
                        WHEN pris_en_charge.enf_code IS NOT NULL THEN \'Enfant\'
                        ELSE \'Agent\'
                      END AS beneficiaire_lien')
            ->join('facture', 'facture.fac_code = demande_remb.fac_code', 'left')
            ->join('objet_remboursement', 'objet_remboursement.obj_code = demande_remb.obj_code', 'left')
            ->join('pris_en_charge', 'pris_en_charge.pec_code = demande_remb.pec_code', 'left')
            ->join('employee pec_employee', 'pec_employee.emp_code = pris_en_charge.emp_code', 'left')
            ->join('conjointe pec_conj', 'pec_conj.conj_code = pris_en_charge.conj_code', 'left')
            ->join('enfant pec_enf', 'pec_enf.enf_code = pris_en_charge.enf_code', 'left')
            ->join('centre_sante', 'centre_sante.cen_code = demande_remb.cen_code', 'left')
            ->where('demande_remb.eta_code', $etaCode)
            ->orderBy('demande_remb.rem_code', 'ASC')
            ->get()->getResultArray();
        
        // 3. Extraire le mois depuis la date de création de l'état
        $mois = $this->getMoisFromDate($etat['eta_date'] ?? null);
        
        // 4. Générer le HTML exact
        $html = $this->buildPdfHtml($etat, $demandes, $mois);
        
        // 5. Générer le PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        $output = $dompdf->output();
        $filename = 'etat_remb_' . str_replace('/', '-', $etat['etat_num'] ?? $etaCode) . '.pdf';
        
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody($output);
    }
    
    /**
     * Obtenir le mois en français depuis une date
     */
    private function getMoisFromDate($date)
    {
        if (empty($date)) return 'N/A';
        
        $moisFr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        $moisNum = (int)date('n', strtotime($date));
        return $moisFr[$moisNum] ?? 'N/A';
    }
    
    /**
     * Format EXACT du document officiel
     */
    private function buildPdfHtml($etat, $demandes, $mois)
    {
        // Logo en base64 (si GD disponible)
        $logoPath = FCPATH . 'logo.png';
        $logoBase64 = '';
        if (extension_loaded('gd') && file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
        }
        
        // Calculer le total
        $total = 0;
        foreach ($demandes as $dem) {
            $total += floatval($dem['rem_montant'] ?? 0);
        }
        $totalEnLettres = $this->nombreEnLettres($total);
        
        // Nom complet de l'agent
        $nomAgent = strtoupper($etat['emp_nom'] ?? '') . ' ' . ($etat['emp_prenom'] ?? '');
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 11px;
            line-height: 1.3;
            color: #000;
        }
        
        /* Header avec logo - format exact */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-cell {
            width: 70px;
        }
        .logo-cell img {
            width: 55px;
            height: auto;
        }
        .org-name {
            font-size: 10px;
            line-height: 1.3;
            padding-left: 10px;
        }
        
        /* Titre principal */
        .main-title {
            font-size: 13px;
            font-weight: bold;
            margin: 15px 0 5px 0;
        }
        .etat-info {
            font-size: 11px;
            margin: 2px 0;
        }
        
        /* Bloc info agent - format exact */
        .agent-info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            border: 1px solid #000;
        }
        .agent-info-table td {
            padding: 4px 8px;
            vertical-align: top;
            font-size: 10px;
        }
        .agent-info-table .label {
            font-weight: bold;
        }
        .agent-info-table .left-col {
            width: 40%;
        }
        .agent-info-table .right-col {
            width: 60%;
            text-align: left;
            vertical-align: middle;
            padding-left: 20px;
        }
        
        /* Tableau principal des demandes */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10px;
        }
        .main-table th {
            background: #e0e0e0;
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }
        .main-table td {
            border: 1px solid #000;
            padding: 5px 4px;
            vertical-align: middle;
            font-size: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        
        .total-row td {
            font-weight: bold;
            background: #f0f0f0;
        }
        
        /* Footer - Arrêté */
        .arrete-box {
            margin: 20px 0;
            padding: 8px 12px;
            border: 1px solid #000;
            font-size: 11px;
        }
        
        /* Signatures - format exact du document */
        .signatures-container {
            margin-top: 25px;
            width: 100%;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signatures-table td {
            width: 33%;
            vertical-align: top;
            font-size: 10px;
            padding: 5px;
        }
        .sig-label {
            margin-bottom: 8px;
        }
        .sig-title {
            font-style: italic;
            margin-bottom: 35px;
        }
        .sig-name {
            font-weight: bold;
            text-transform: uppercase;
        }
        .sig-center {
            text-align: center;
        }
        .sig-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <!-- HEADER avec logo - aligné verticalement -->
    <table class="header-table">
        <tr>';
        
        if ($logoBase64) {
            $html .= '
            <td class="logo-cell">
                <img src="' . $logoBase64 . '" alt="Logo">
            </td>';
        }
        
        $html .= '
            <td>
                <div class="org-name">AUTORITE DE REGULATION<br>DES MARCHES PUBLICS</div>
            </td>
        </tr>
    </table>
    
    <!-- Titre et numéro -->
    <div class="main-title">ETAT DE REMBOURSEMENT DES FRAIS MEDICAUX</div>
    <div class="etat-info">N° : ' . htmlspecialchars($etat['etat_num'] ?? '') . '</div>
    <div class="etat-info">Mois : ' . $mois . '</div>
    
    <!-- Info Agent - Format exact avec IM centré verticalement à droite -->
    <table class="agent-info-table">
        <tr>
            <td class="left-col"><span class="label">Nom de l\'Agent :</span> ' . htmlspecialchars($nomAgent) . '</td>
            <td class="right-col" rowspan="3"><span class="label">IM :</span> ' . htmlspecialchars($etat['emp_imarmp'] ?? '') . '</td>
        </tr>
        <tr>
            <td><span class="label">Fonction :</span> ' . htmlspecialchars($etat['pst_fonction'] ?? '') . '</td>
        </tr>
        <tr>
            <td><span class="label">Direction :</span> ' . htmlspecialchars($etat['dir_nom'] ?? '') . '</td>
        </tr>
    </table>
    
    <!-- Tableau des demandes -->
    <table class="main-table">
        <thead>
            <tr>
                <th style="width: 4%;">N°</th>
                <th style="width: 20%;">Nom du malade</th>
                <th style="width: 9%;">Lien</th>
                <th style="width: 20%;">N° Prise en charge</th>
                <th style="width: 18%;">Objet du remboursement</th>
                <th style="width: 14%;">N° Facture</th>
                <th style="width: 15%;">Montant Total</th>
            </tr>
        </thead>
        <tbody>';
        
        $i = 1;
        foreach ($demandes as $dem) {
            $montant = floatval($dem['rem_montant'] ?? 0);
            
            // Format facture avec date
            $facInfo = $dem['fac_num'] ?? '';
            if (!empty($dem['fac_date'])) {
                $facInfo .= ' du ' . date('d/m/Y', strtotime($dem['fac_date']));
            }
            
            // Bénéficiaire
            $nomMalade = $dem['beneficiaire_nom'] ?? ($etat['emp_nom'] . ' ' . $etat['emp_prenom']);
            $lien = $dem['beneficiaire_lien'] ?? 'Agent';
            
            $html .= '
            <tr>
                <td class="text-center">' . $i . '</td>
                <td>' . htmlspecialchars($nomMalade) . '</td>
                <td class="text-center">' . htmlspecialchars($lien) . '</td>
                <td>' . htmlspecialchars($dem['pec_num'] ?? '') . '</td>
                <td>' . htmlspecialchars($dem['obj_article'] ?? '') . '</td>
                <td>' . htmlspecialchars($facInfo) . '</td>
                <td class="text-right">' . number_format($montant, 2, ',', ' ') . '</td>
            </tr>';
            $i++;
        }
        
        // Ligne TOTAL dans le tableau
        $html .= '
            <tr class="total-row">
                <td colspan="6" class="text-right">TOTAL</td>
                <td class="text-right">' . number_format($total, 2, ',', ' ') . '</td>
            </tr>
        </tbody>
    </table>
    
    <!-- Arrêté -->
    <div class="arrete-box">
        <strong>Arrêté le présent état à la somme de :</strong> ' . $totalEnLettres . '
    </div>
    
    <!-- Signatures - Format exact du document officiel -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-label">Présenté :</div>
                <div class="sig-title">Le Responsable des Ressources Humaines p.i</div>
            </td>
            <td class="sig-center">
            </td>
            <td class="sig-right">
                <div class="sig-label">Antananarivo, le <span style="display: inline-block; width: 100px;"></span></div>
                <div class="sig-title">L\'Ordonnateur Secondaire</div>
            </td>
        </tr>
    </table>
    
    <div style="margin-top: 40px; font-size: 9px;">
        Signature :
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Convertir nombre en lettres
     */
    private function nombreEnLettres($nombre)
    {
        $nombre = round($nombre, 2);
        $entier = floor($nombre);
        
        if ($entier == 0) return 'Zéro ariary';
        
        $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
        $dizDix = ['dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $dizaines = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
        
        $resultat = '';
        
        // Millions
        if ($entier >= 1000000) {
            $millions = floor($entier / 1000000);
            if ($millions == 1) {
                $resultat .= 'un million ';
            } else {
                $resultat .= $this->convertirCentaines($millions, $unites, $dizDix, $dizaines) . ' millions ';
            }
            $entier = $entier % 1000000;
        }
        
        // Milliers
        if ($entier >= 1000) {
            $milliers = floor($entier / 1000);
            if ($milliers == 1) {
                $resultat .= 'mille ';
            } else {
                $resultat .= $this->convertirCentaines($milliers, $unites, $dizDix, $dizaines) . ' mille ';
            }
            $entier = $entier % 1000;
        }
        
        // Reste
        if ($entier > 0) {
            $resultat .= $this->convertirCentaines($entier, $unites, $dizDix, $dizaines);
        }
        
        return ucfirst(trim($resultat)) . ' ariary';
    }
    
    private function convertirCentaines($n, $unites, $dizDix, $dizaines)
    {
        if ($n == 0) return '';
        $result = '';
        
        if ($n >= 100) {
            $cent = floor($n / 100);
            if ($cent == 1) {
                $result .= 'cent ';
            } else {
                $result .= $unites[$cent] . ' cent ';
            }
            $n = $n % 100;
        }
        
        if ($n >= 10 && $n < 20) {
            $result .= $dizDix[$n - 10];
        } else if ($n >= 20) {
            $diz = floor($n / 10);
            $uni = $n % 10;
            
            if ($diz == 7) {
                $result .= 'soixante-' . $dizDix[$uni];
            } else if ($diz == 9) {
                $result .= 'quatre-vingt-' . $dizDix[$uni];
            } else {
                $result .= $dizaines[$diz];
                if ($uni == 1 && $diz != 8) {
                    $result .= ' et un';
                } else if ($uni > 0) {
                    $result .= '-' . $unites[$uni];
                }
            }
        } else if ($n > 0) {
            $result .= $unites[$n];
        }
        
        return trim($result);
    }
}
