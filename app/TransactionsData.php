<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionsData extends Model {

    use SoftDeletes;

    protected $table = "transactionsdata";

    protected $fillable = [];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    protected $hidden = ['id', 'data_id', 'created_at', 'updated_at', 'deleted_at'];

    // Relationships

}
