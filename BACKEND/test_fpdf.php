<?php
// Test FPDF bulletin generation
require __DIR__ . '/vendor/autoload.php';

try {
    echo "Testing FPDF...\n";
    
    $pdf = new \FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, 'Hello World!');
    
    $output = $pdf->Output('S');
    echo "PDF generated successfully! Size: " . strlen($output) . " bytes\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
