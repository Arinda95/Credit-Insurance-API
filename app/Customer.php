<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model {

    use SoftDeletes;

    protected $table = "customers";

    protected $fillable = [];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    protected $hidden = ['id', 'type', 'pin', 'password', 'created_at', 'updated_at', 'deleted_at'];

    // Relationships

}
