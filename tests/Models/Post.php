<?php

namespace ServiceTo\UsesDetail\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use ServiceTo\UsesDetail;

class Post extends Model
{
    use UsesDetail;

    protected $table = 'posts';
    protected $guarded = [];

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }
}
