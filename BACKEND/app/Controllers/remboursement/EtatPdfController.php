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
     * Générer le PDF d'un État de Remboursement
     * Format conforme au document officiel
     */
    public function generateEtatPdf($etaCode)
    {
        $db = \Config\Database::connect();
        
        // 1. Récupérer l'état
        $etat = $db->table('etat_remb')
            ->select('etat_remb.*, 
                      employee.emp_nom, 
                      employee.emp_prenom, 
                      employee.emp_imarmp')
            ->join('employee', 'employee.emp_code = etat_remb.emp_code', 'left')
            ->where('etat_remb.eta_code', $etaCode)
            ->get()->getRowArray();
        
        if (!$etat) {
            return $this->failNotFound('État non trouvé');
        }
        
        // 2. Récupérer toutes les demandes liées
        $demandes = $db->table('demande_remb')
            ->select('demande_remb.*,
                      facture.fac_num,
                      facture.fac_date,
                      objet_facture.obj_article,
                      pris_en_charge.pec_num,
                      pris_en_charge.beneficiaire_nom,
                      pris_en_charge.beneficiaire_type,
                      centre_sante.cen_nom')
            ->join('facture', 'facture.fac_code = demande_remb.fac_code', 'left')
            ->join('objet_facture', 'objet_facture.obj_code = demande_remb.obj_code', 'left')
            ->join('pris_en_charge', 'pris_en_charge.pec_code = demande_remb.pec_code', 'left')
            ->join('centre_sante', 'centre_sante.cen_code = demande_remb.cen_code', 'left')
            ->where('demande_remb.eta_code', $etaCode)
            ->orderBy('demande_remb.rem_code', 'ASC')
            ->get()->getResultArray();
        
        // 3. Extraire le mois depuis le numéro (format: NNN/ARMP/DG/DAAF/SRH/FM-YY)
        $parts = explode('/', $etat['etat_num']);
        $lastPart = end($parts); // "FM-25"
        $moisCode = explode('-', $lastPart)[0]; // "FM"
        
        $moisMap = [
            'JA' => 'Janvier', 'FE' => 'Février', 'MA' => 'Mars', 'AV' => 'Avril',
            'MI' => 'Mai', 'JU' => 'Juin', 'JL' => 'Juillet', 'AO' => 'Août',
            'SE' => 'Septembre', 'OC' => 'Octobre', 'NO' => 'Novembre', 'DE' => 'Décembre'
        ];
        $mois = $moisMap[$moisCode] ?? 'N/A';
        
        // 4. Générer le HTML
        $html = $this->buildPdfHtml($etat, $demandes, $mois);
        
        // 5. Générer le PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        // 6. Retourner le PDF
        $output = $dompdf->output();
        $filename = 'etat_remb_' . $etat['etat_num'] . '.pdf';
        
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody($output);
    }
    
    private function buildPdfHtml($etat, $demandes, $mois)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page { margin: 20px; }
                body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
                h2 { text-align: center; font-size: 12px; margin: 10px 0; }
                h3 { font-size: 10px; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #000; padding: 4px; text-align: left; }
                th { background: #f0f0f0; font-weight: bold; font-size: 8px; }
                td { font-size: 8px; }
                .header { text-align: center; margin-bottom: 10px; }
                .info-line { margin: 3px 0; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>ÉTAT DE REMBOURSEMENT DE FRAIS MÉDICAUX</h2>
                <p style="margin: 5px 0;"><strong>N°: ' . htmlspecialchars($etat['etat_num']) . '</strong></p>
                <p style="margin: 5px 0;">Mois : ' . $mois . '</p>
            </div>
            
            <div class="info-line">
                <strong>Nom de l\'Établissement</strong>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%;">N° Facture</th>
                        <th style="width: 15%;">Désignation de l\'acte</th>
                        <th style="width: 15%;">N° Prise en charge</th>
                        <th style="width: 10%;">PEC N°</th>
                        <th style="width: 18%;">Nom et prénom(s) de l\'agent</th>
                        <th style="width: 18%;">Nom et prénom(s) du malade</th>
                        <th style="width: 7%;">Lien</th>
                        <th style="width: 12%;" class="text-right">Montant total</th>
                    </tr>
                </thead>
                <tbody>';
        
        $total = 0;
        foreach ($demandes as $dem) {
            $total += $dem['rem_montant'];
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($dem['fac_num'] ?? '-') . ' du ' . htmlspecialchars($dem['fac_date'] ?? '') . '</td>
                        <td>' . htmlspecialchars($dem['obj_article'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($dem['pec_num'] ?? '-') . '</td>
                        <td>' . htmlspecialchars(explode('/', $dem['pec_num'] ?? '')[0] ?? '-') . '</td>
                        <td>' . htmlspecialchars($etat['emp_nom'] . ' ' . $etat['emp_prenom']) . '</td>
                        <td>' . htmlspecialchars($dem['beneficiaire_nom'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($dem['beneficiaire_type'] ?? 'Agent') . '</td>
                        <td class="text-right">' . number_format($dem['rem_montant'], 2, ',', ' ') . '</td>
                    </tr>';
        }
        
        $html .= '
                    <tr>
                        <td colspan="7" class="text-right"><strong>TOTAL</strong></td>
                        <td class="text-right"><strong>' . number_format($total, 2, ',', ' ') . ' Ar</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';
        
        return $html;
    }
}
