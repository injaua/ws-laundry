<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One translated text (dot key inside a group, e.g. app / "login" or validation / "between.numeric"). */
class Translation extends Model
{
    protected $fillable = ["locale", "group", "key", "value"];
}
