<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model {

    use SoftDeletes;

    protected $table = "businesses";

    protected $fillable = [];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    protected $hidden = ['id', 'pin', 'password', 'created_at', 'updated_at', 'deleted_at'];

    // Relationships

}
