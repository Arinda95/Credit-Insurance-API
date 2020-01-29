<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model {

    use SoftDeletes;

    protected $table = "notifications";

    protected $fillable = [];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    protected $hidden = ['id', 'notification_id', 'recipient_id', 'updated_at', 'deleted_at'];

    // Relationships

}
