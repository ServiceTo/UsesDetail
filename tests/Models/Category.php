<?php

namespace ServiceTo\UsesDetail\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use ServiceTo\UsesDetail;

class Category extends Model
{
    use UsesDetail;

    protected $table = 'categories';
    protected $guarded = [];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product', 'category_id', 'product_id');
    }
}
