<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    // Tell Laravel which table to use
    protected $table = 'account_history';

    // Tell Laravel the primary key is 'no' and it's an integer
    protected $primaryKey = 'no';
    public $incrementing = true;
    protected $keyType = 'int';

    // Define fillable fields
    protected $fillable = [
        'account', 'sender', 's_name', 'receiver', 'r_name', 
        'dt', 'tm', 'type', 'amount', 'balance_after', 
        'description', 'reference_number', 'status', 'fee', 'tax'
    ];

    // If you don't need Laravel's default created_at/updated_at
    public $timestamps = false;

    /**
     * Get the account that owns this transaction.
     */
    public function accountInfo()
    {
        return $this->belongsTo(AccountInfo::class, 'account', 'account');
    }

    /**
     * Get the transaction type details.
     */
    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class, 'type', 'id');
    }
}