<?php
namespace App\Models\conge;

use CodeIgniter\Model;

class DebitSoldeCngModel extends Model
{
    protected $table      = 'debit_solde_cng';
    protected $primaryKey = 'deb_code';

    protected $allowedFields = [
        'emp_code',
        'sld_code',
        'cng_code',
        'deb_jr',
        'deb_date'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
