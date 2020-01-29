<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CredentialReset extends Model {

    use SoftDeletes;

    protected $table = "credential_reset";

    protected $fillable = [];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

}
