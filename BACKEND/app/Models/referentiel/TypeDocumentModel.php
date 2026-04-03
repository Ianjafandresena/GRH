<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class TypeDocumentModel extends Model
{
    protected $table = 'type_document';
    protected $primaryKey = 'tdoc_code';
    protected $allowedFields = ['tdoc_code', 'tdoc_nom', 'tdoc_matricule'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

