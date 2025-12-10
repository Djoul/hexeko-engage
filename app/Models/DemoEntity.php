<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DemoEntity extends Model
{
    use HasUuids;

    public $table = 'demo_entities';
}
