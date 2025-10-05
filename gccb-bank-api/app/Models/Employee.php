<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    // Tell Laravel which table to use
    protected $table = 'users';

    // Tell Laravel the primary key is 'id' but it's a string, not an integer
    protected $primaryKey = 'id';
    public $incrementing = false; // Because your ID is a varchar, not auto-incrementing int
    protected $keyType = 'string';

    // Define the fields that can be filled manually
    protected $fillable = [
        'id', 'username', 'password', 'role', 'status'
    ];

    // Hide the password for security when the model is converted to JSON
    protected $hidden = [
        'password',
    ];

    /**
     * Get the details for this employee.
     * This defines a relationship: one Employee has one EmpDetail
     */
    public function details()
    {
        // 'id' on the *Employee* model (users table)
        // is related to 'id' on the *EmpDetail* model (emp_details table)
        return $this->hasOne(EmpDetail::class, 'id', 'id');
    }
}