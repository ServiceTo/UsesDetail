<?php

namespace ServiceTo\UsesDetail\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use ServiceTo\UsesDetail;

class ModelWithoutDetailColumn extends Model
{
    use UsesDetail;

    protected $table = 'models_without_detail';
    protected $guarded = [];
}
