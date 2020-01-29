<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admin extends Model {

    use SoftDeletes;

    protected $table = "admins";

    protected $fillable = [];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    protected $hidden = ['id', 'password', 'created_at', 'updated_at', 'deleted_at'];

    // Relationships

}
