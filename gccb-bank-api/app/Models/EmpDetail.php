<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpDetail extends Model
{
    // Tell Laravel which table to use
    protected $table = 'emp_details';

    // Tell Laravel the primary key is a string
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    // Define fillable fields
    protected $fillable = [
        'id', 'name', 'fname', 'cnic', 'contect', 'dob', 'gender', 'marital', 'email', 'postal', 'city', 'houseaddress', 'edu', 'title', 'exp', 'hier_date', 'image'
    ];

    /**
     * Get the user account associated with these details.
     * This defines the inverse of the relationship.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id', 'id');
    }
}