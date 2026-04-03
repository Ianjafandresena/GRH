<?php
namespace App\Models\employe;

use CodeIgniter\Model;

class ContactModel extends Model
{
    protected $table = 'contact';
    protected $primaryKey = 'id_contact'; // PostgreSQL convertit en minuscules
    protected $allowedFields = [
        'numero',
        'emp_code'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'numero' => 'required|max_length[50]|is_unique[contact.numero,id_contact,{id_contact}]',
        'emp_code' => 'required|integer',
        'id_contact' => 'permit_empty|integer'
    ];

    protected $skipValidation = false;
}

