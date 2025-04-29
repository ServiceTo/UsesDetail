<?php

namespace ServiceTo\UsesDetail\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use ServiceTo\UsesDetail;

class TestModel extends Model
{
    use UsesDetail;

    protected $table = 'test_models';
    protected $guarded = [];
} 