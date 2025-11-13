<?php

namespace ServiceTo\UsesDetail\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use ServiceTo\UsesDetail;

class Tag extends Model
{
    use UsesDetail;

    protected $table = 'tags';
    protected $guarded = [];

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id');
    }
}
