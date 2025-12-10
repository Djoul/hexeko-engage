<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TestAuditModel extends Model implements Auditable
{
    use AuditableModel;

    protected $fillable = ['name', 'description'];
}
