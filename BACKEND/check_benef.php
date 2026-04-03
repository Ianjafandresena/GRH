<?php
define('FCPATH', __DIR__ . '/public/');
require_once __DIR__ . '/app/Config/Paths.php';
$paths = new \Config\Paths();
require_once __DIR__ . '/vendor/codeigniter4/framework/system/bootstrap.php';

$db = \Config\Database::connect();
$emp_code = 3;

echo "--- Conjoints for ID 3 ---\n";
$conjoints = $db->table('emp_conj')
            ->select('conjointe.*')
            ->join('conjointe', 'conjointe.conj_code = emp_conj.conj_code')
            ->where('emp_conj.emp_code', $emp_code)
            ->get()
            ->getResultArray();
var_dump($conjoints);

echo "\n--- Enfants for ID 3 ---\n";
$enfants = $db->table('enfant')
            ->where('emp_code', $emp_code)
            ->get()
            ->getResultArray();
var_dump($enfants);
