<?php

namespace ServiceTo\UsesDetail\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ServiceTo\UsesDetail\Tests\Models\Category;
use ServiceTo\UsesDetail\Tests\Models\Product;
use ServiceTo\UsesDetail\Tests\Models\Tag;
use ServiceTo\UsesDetail\Tests\Models\Post;

class PivotTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create categories table (uses UsesDetail trait)
        Schema::create('categories', function ($table) {
            $table->id();
            $table->json('detail');
            $table->timestamps();
        });

        // Create products table (does NOT use UsesDetail trait)
        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });

        // Create pivot table for category-product relationship
        Schema::create('category_product', function ($table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Create tags table (uses UsesDetail trait)
        Schema::create('tags', function ($table) {
            $table->id();
            $table->json('detail');
            $table->timestamps();
        });

        // Create posts table (uses UsesDetail trait)
        Schema::create('posts', function ($table) {
            $table->id();
            $table->json('detail');
            $table->timestamps();
        });

        // Create pivot table for post-tag relationship
        Schema::create('post_tag', function ($table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_can_query_pivot_table_with_uses_detail_and_non_uses_detail_models()
    {
        // Create a category (with UsesDetail)
        $category = new Category();
        $category->name = 'Electronics';
        $category->save();

        // Create products (without UsesDetail)
        $product1 = Product::create(['name' => 'Laptop', 'price' => 999.99]);
        $product2 = Product::create(['name' => 'Phone', 'price' => 599.99]);

        // Attach products to category with pivot data
        $category->products()->attach($product1->id, ['sort_order' => 1]);
        $category->products()->attach($product2->id, ['sort_order' => 2]);

        // Query products through relationship - this should work without treating
        // pivot table columns as detail columns
        $products = $category->products()->orderBy('category_product.sort_order')->get();

        $this->assertCount(2, $products);
        $this->assertEquals('Laptop', $products[0]->name);
        $this->assertEquals('Phone', $products[1]->name);
    }

    /** @test */
    public function it_can_query_pivot_table_columns_directly_with_uses_detail_model()
    {
        // Create a category (with UsesDetail)
        $category = new Category();
        $category->name = 'Books';
        $category->save();

        // Create products (without UsesDetail)
        $product1 = Product::create(['name' => 'Novel', 'price' => 19.99]);
        $product2 = Product::create(['name' => 'Textbook', 'price' => 89.99]);

        // Attach products with pivot data
        $category->products()->attach($product1->id, ['sort_order' => 5]);
        $category->products()->attach($product2->id, ['sort_order' => 10]);

        // Query using wherePivot - should work correctly
        $products = $category->products()->wherePivot('sort_order', '>', 7)->get();

        $this->assertCount(1, $products);
        $this->assertEquals('Textbook', $products[0]->name);
    }

    /** @test */
    public function it_can_query_pivot_table_with_both_models_using_uses_detail()
    {
        // Create tags (with UsesDetail)
        $tag1 = new Tag();
        $tag1->name = 'Laravel';
        $tag1->color = 'red';
        $tag1->save();

        $tag2 = new Tag();
        $tag2->name = 'PHP';
        $tag2->color = 'blue';
        $tag2->save();

        // Create a post (with UsesDetail)
        $post = new Post();
        $post->title = 'My First Post';
        $post->content = 'This is a test post about Laravel and PHP.';
        $post->save();

        // Attach tags to post
        $post->tags()->attach([$tag1->id, $tag2->id]);

        // Query tags through relationship - should work correctly
        $tags = $post->tags()->get();

        $this->assertCount(2, $tags);
        $this->assertTrue($tags->contains('name', 'Laravel'));
        $this->assertTrue($tags->contains('name', 'PHP'));
    }

    /** @test */
    public function it_can_order_by_pivot_table_columns_with_uses_detail_models()
    {
        // Create tags (with UsesDetail)
        $tag1 = new Tag();
        $tag1->name = 'First';
        $tag1->save();

        $tag2 = new Tag();
        $tag2->name = 'Second';
        $tag2->save();

        $tag3 = new Tag();
        $tag3->name = 'Third';
        $tag3->save();

        // Create a post (with UsesDetail)
        $post = new Post();
        $post->title = 'Test Post';
        $post->save();

        // Attach tags with timestamps in a specific order
        sleep(1);
        $post->tags()->attach($tag3->id);
        sleep(1);
        $post->tags()->attach($tag1->id);
        sleep(1);
        $post->tags()->attach($tag2->id);

        // Query tags ordered by pivot table's created_at
        $tags = $post->tags()->orderBy('post_tag.created_at', 'asc')->get();

        $this->assertCount(3, $tags);
        $this->assertEquals('Third', $tags[0]->name);
        $this->assertEquals('First', $tags[1]->name);
        $this->assertEquals('Second', $tags[2]->name);
    }

    /** @test */
    public function it_can_filter_by_detail_columns_in_related_model_with_pivot()
    {
        // Create categories (with UsesDetail)
        $category1 = new Category();
        $category1->name = 'Electronics';
        $category1->status = 'active';
        $category1->save();

        $category2 = new Category();
        $category2->name = 'Books';
        $category2->status = 'inactive';
        $category2->save();

        // Create a product (without UsesDetail)
        $product = Product::create(['name' => 'Test Product', 'price' => 49.99]);

        // Attach product to both categories
        $category1->products()->attach($product->id);
        $category2->products()->attach($product->id);

        // Find all active categories for this product
        // This tests that detail column queries still work on the main model
        // while pivot table columns are handled correctly
        $activeCategories = Category::where('status', 'active')
            ->whereHas('products', function($query) use ($product) {
                $query->where('products.id', $product->id);
            })
            ->get();

        $this->assertCount(1, $activeCategories);
        $this->assertEquals('Electronics', $activeCategories[0]->name);
    }

    /** @test */
    public function it_can_combine_pivot_queries_with_detail_column_queries()
    {
        // Create tags (with UsesDetail)
        $tag1 = new Tag();
        $tag1->name = 'Laravel';
        $tag1->priority = 10;
        $tag1->save();

        $tag2 = new Tag();
        $tag2->name = 'PHP';
        $tag2->priority = 5;
        $tag2->save();

        $tag3 = new Tag();
        $tag3->name = 'Testing';
        $tag3->priority = 8;
        $tag3->save();

        // Create posts (with UsesDetail)
        $post1 = new Post();
        $post1->title = 'Laravel Tutorial';
        $post1->status = 'published';
        $post1->save();

        $post2 = new Post();
        $post2->title = 'PHP Basics';
        $post2->status = 'draft';
        $post2->save();

        // Attach tags to posts
        $post1->tags()->attach([$tag1->id, $tag3->id]);
        $post2->tags()->attach([$tag2->id]);

        // Query tags with detail column filter (priority) on published posts
        $highPriorityTags = Tag::where('priority', '>=', 8)
            ->whereHas('posts', function($query) {
                $query->where('status', 'published');
            })
            ->get();

        $this->assertCount(2, $highPriorityTags);
        $this->assertTrue($highPriorityTags->contains('name', 'Laravel'));
        $this->assertTrue($highPriorityTags->contains('name', 'Testing'));
    }

    /** @test */
    public function it_handles_qualified_column_names_from_model_table_correctly()
    {
        // Create a category (with UsesDetail)
        $category = new Category();
        $category->name = 'Test Category';
        $category->status = 'active';
        $category->save();

        // Query using qualified column name from the model's own table
        // This should still resolve to detail column since it's the model's table
        $results = Category::where('categories.name', 'Test Category')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Test Category', $results[0]->name);
    }

    /** @test */
    public function it_distinguishes_between_model_table_and_pivot_table_qualified_columns()
    {
        // Create a category (with UsesDetail)
        $category = new Category();
        $category->name = 'Electronics';
        $category->save();

        // Create products
        $product1 = Product::create(['name' => 'Laptop', 'price' => 999.99]);
        $product2 = Product::create(['name' => 'Phone', 'price' => 599.99]);

        // Attach products with pivot data
        $category->products()->attach($product1->id, ['sort_order' => 1]);
        $category->products()->attach($product2->id, ['sort_order' => 2]);

        // This query should correctly handle:
        // - categories.name (model table, detail column)
        // - category_product.sort_order (pivot table, schema column)
        $products = $category->products()
            ->where('category_product.sort_order', '>', 0)
            ->get();

        $this->assertCount(2, $products);
    }
}
