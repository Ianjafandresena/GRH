<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['username', 'pswrd', 'role', 'emp_code', 'is_active', 'last_login'];

    public function getUserByUsername($username)
    {
        return $this->where('username', $username)->first();
    }
}