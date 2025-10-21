<?php

namespace ServiceTo\UsesDetail\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ServiceTo\UsesDetail\Tests\Models\TestModel;
use stdClass;

class UsesDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_models', function ($table) {
            $table->id();
            $table->json('detail');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_stores_dynamic_attributes_in_detail_column()
    {
        $model = new TestModel();
        $model->name = 'Test Name';
        $model->description = 'Test Description';
        $model->save();

        $this->assertDatabaseHas('test_models', [
            'id' => $model->id,
        ]);

        $retrieved = TestModel::find($model->id);
        $detail = json_decode($retrieved->getRawOriginal('detail'), true);
        
        $this->assertArrayHasKey('name', $detail);
        $this->assertArrayHasKey('description', $detail);
        $this->assertArrayHasKey('uuid', $detail);
        $this->assertEquals('Test Name', $detail['name']);
        $this->assertEquals('Test Description', $detail['description']);
        $this->assertEquals(36, strlen($detail['uuid']));
    }

    /** @test */
    public function it_retrieves_dynamic_attributes_from_detail_column()
    {
        $model = new TestModel();
        $model->name = 'Test Name';
        $model->description = 'Test Description';
        $model->save();

        $retrieved = TestModel::find($model->id);

        $this->assertEquals('Test Name', $retrieved->name);
        $this->assertEquals('Test Description', $retrieved->description);
        $this->assertEquals(36, strlen($retrieved->uuid));
    }

    /** @test */
    public function it_generates_uuid_on_initialization()
    {
        $model = new TestModel();
        $this->assertNotNull($model->uuid);
        $this->assertEquals(36, strlen($model->uuid));
    }

    /** @test */
    public function it_can_find_by_uuid()
    {
        $model = new TestModel();
        $model->save();

        $found = TestModel::find($model->uuid);
        $this->assertEquals($model->id, $found->id);
    }

    /** @test */
    public function it_can_search_using_detail_scope()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->save();

        $results = TestModel::detail('name', '=', 'First Model')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_merge_objects()
    {
        $model = new TestModel();
        $model->name = 'Original Name';
        $model->save();

        $object = new stdClass();
        $object->name = 'New Name';
        $object->description = 'New Description';

        $model->merge($object);

        $this->assertEquals('New Name', $model->name);
        $this->assertEquals('New Description', $model->description);
    }

    /** @test */
    public function it_can_merge_arrays()
    {
        $model = new TestModel();
        $model->name = 'Original Name';
        $model->save();

        $array = [
            'name' => 'New Name',
            'description' => 'New Description'
        ];

        $model->merge($array);

        $this->assertEquals('New Name', $model->name);
        $this->assertEquals('New Description', $model->description);
    }

    /** @test */
    public function it_can_merge_json_strings()
    {
        $model = new TestModel();
        $model->name = 'Original Name';
        $model->save();

        $json = '{"name": "New Name", "description": "New Description"}';
        $model->merge($json);

        $this->assertEquals('New Name', $model->name);
        $this->assertEquals('New Description', $model->description);
    }

    /** @test */
    public function it_does_not_overwrite_timestamps_when_merging()
    {
        $model = new TestModel();
        $model->save();

        $originalCreatedAt = $model->created_at;
        $originalUpdatedAt = $model->updated_at;

        $data = [
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00'
        ];

        $model->merge($data);

        $this->assertEquals($originalCreatedAt, $model->created_at);
        $this->assertEquals($originalUpdatedAt, $model->updated_at);
    }

    /** @test */
    public function it_can_query_schema_columns_using_detail_scope()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->save();

        // Query using detail() on a schema column (updated_at)
        // This should intelligently use where('updated_at') instead of where('detail->updated_at')
        $results = TestModel::detail('updated_at', '>', '2020-01-01')->get();
        $this->assertCount(2, $results);

        // Query using detail() on a schema column (id)
        $results = TestModel::detail('id', '=', $model1->id)->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_query_both_schema_and_detail_columns_using_same_method()
    {
        $model = new TestModel();
        $model->name = 'Test Model';
        $model->save();

        // Query by schema column using detail()
        $resultsBySchema = TestModel::detail('id', '=', $model->id)->get();
        $this->assertCount(1, $resultsBySchema);

        // Query by detail column using detail()
        $resultsByDetail = TestModel::detail('name', '=', 'Test Model')->get();
        $this->assertCount(1, $resultsByDetail);

        // Both should return the same model
        $this->assertEquals($resultsBySchema->first()->id, $resultsByDetail->first()->id);
    }
} 