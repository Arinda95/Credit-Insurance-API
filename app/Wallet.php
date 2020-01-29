<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model {

    use SoftDeletes;

    protected $table = "wallets";

    protected $fillable = [];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    protected $hidden = ['id', 'created_at', 'updated_at', 'deleted_at'];

    // Relationships

}
