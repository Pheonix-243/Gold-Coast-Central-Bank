<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountHolder extends Model
{
    protected $table = 'accountsholder';
    protected $primaryKey = 'account';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'account', 'name', 'fname', 'cnic', 'contect', 'dob', 
        'gender', 'email', 'image', 'postal', 'city', 'houseaddress'
    ];

    /**
     * Get the account info associated with this holder.
     */
    public function accountInfo()
    {
        return $this->hasOne(AccountInfo::class, 'account', 'account');
    }
}