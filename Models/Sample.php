<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class Sample extends Model
{
    protected $table = 'samples';

    protected $guarded = ['id'];
}
