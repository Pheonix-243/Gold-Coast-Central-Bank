<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountInfo extends Model
{
    protected $table = 'accounts_info';
    protected $primaryKey = 'account';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'account', 'account_title', 'account_type', 'balance', 
        'registerdate', 'password', 'status'
    ];

    /**
     * Get the account holder associated with this account.
     */
    public function holder()
    {
        return $this->belongsTo(AccountHolder::class, 'account', 'account');
    }
}