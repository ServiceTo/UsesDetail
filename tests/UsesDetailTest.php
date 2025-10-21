<?php

namespace ServiceTo\UsesDetail\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ServiceTo\UsesDetail\Tests\Models\TestModel;
use ServiceTo\UsesDetail\Tests\Models\ModelWithoutDetailColumn;
use ServiceTo\MissingDetailColumnException;
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

    /** @test */
    public function it_can_query_detail_columns_using_regular_where()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->save();

        // Query using regular where() on a detail column (name is not in schema)
        // This should automatically use where('detail->name') behind the scenes
        $results = TestModel::where('name', '=', 'First Model')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_query_schema_columns_using_regular_where()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->save();

        // Query using regular where() on a schema column (id)
        // This should use where('id') directly
        $results = TestModel::where('id', '=', $model1->id)->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model1->id, $results->first()->id);

        // Query using regular where() on a schema column (updated_at)
        $results = TestModel::where('updated_at', '>', '2020-01-01')->get();
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_chain_where_clauses_for_both_schema_and_detail_columns()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->description = 'Description A';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->description = 'Description B';
        $model2->save();

        // Chain where clauses: schema column + detail column
        $results = TestModel::where('id', '=', $model1->id)
                           ->where('name', '=', 'First Model')
                           ->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model1->id, $results->first()->id);

        // Chain multiple detail columns
        $results = TestModel::where('name', '=', 'Second Model')
                           ->where('description', '=', 'Description B')
                           ->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model2->id, $results->first()->id);
    }

    /** @test */
    public function it_can_use_or_where_for_detail_and_schema_columns()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->save();

        // Use orWhere with detail columns
        $results = TestModel::where('name', '=', 'First Model')
                           ->orWhere('name', '=', 'Second Model')
                           ->get();
        $this->assertCount(2, $results);

        // Use orWhere with schema column
        $results = TestModel::where('id', '=', $model1->id)
                           ->orWhere('id', '=', $model2->id)
                           ->get();
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_use_where_in_for_detail_and_schema_columns()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->status = 'active';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->status = 'inactive';
        $model2->save();

        $model3 = new TestModel();
        $model3->name = 'Third Model';
        $model3->status = 'active';
        $model3->save();

        // whereIn on detail column
        $results = TestModel::whereIn('status', ['active'])->get();
        $this->assertCount(2, $results);

        // whereIn on schema column
        $results = TestModel::whereIn('id', [$model1->id, $model2->id])->get();
        $this->assertCount(2, $results);

        // whereNotIn on detail column
        $results = TestModel::whereNotIn('status', ['inactive'])->get();
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_use_where_null_for_detail_and_schema_columns()
    {
        $model1 = new TestModel();
        $model1->name = 'First Model';
        $model1->description = 'Has description';
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Second Model';
        $model2->save();

        // whereNull on detail column (description not set means null in JSON)
        $results = TestModel::whereNull('description')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model2->id, $results->first()->id);

        // whereNotNull on detail column
        $results = TestModel::whereNotNull('description')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model1->id, $results->first()->id);

        // whereNotNull on schema column
        $results = TestModel::whereNotNull('id')->get();
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_use_where_between_for_detail_and_schema_columns()
    {
        $model1 = new TestModel();
        $model1->name = 'Model 1';
        $model1->priority = 5;
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Model 2';
        $model2->priority = 10;
        $model2->save();

        $model3 = new TestModel();
        $model3->name = 'Model 3';
        $model3->priority = 15;
        $model3->save();

        // whereBetween on detail column
        $results = TestModel::whereBetween('priority', [8, 12])->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model2->id, $results->first()->id);

        // whereNotBetween on detail column
        $results = TestModel::whereNotBetween('priority', [8, 12])->get();
        $this->assertCount(2, $results);

        // whereBetween on schema column (id)
        $results = TestModel::whereBetween('id', [$model1->id, $model2->id])->get();
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_combine_multiple_where_methods()
    {
        $model1 = new TestModel();
        $model1->name = 'Active Model';
        $model1->status = 'active';
        $model1->priority = 10;
        $model1->save();

        $model2 = new TestModel();
        $model2->name = 'Inactive Model';
        $model2->status = 'inactive';
        $model2->priority = 5;
        $model2->save();

        $model3 = new TestModel();
        $model3->name = 'Another Active';
        $model3->status = 'active';
        $model3->priority = 20;
        $model3->save();

        // Combine multiple where methods
        $results = TestModel::where('status', 'active')
                           ->whereBetween('priority', [8, 15])
                           ->whereNotNull('name')
                           ->get();
        $this->assertCount(1, $results);
        $this->assertEquals($model1->id, $results->first()->id);

        // Use orWhere with whereIn
        $results = TestModel::whereIn('status', ['active'])
                           ->orWhere('priority', '<', 6)
                           ->get();
        $this->assertCount(3, $results);
    }

    /** @test */
    public function it_throws_exception_when_saving_non_schema_attribute_without_detail_column()
    {
        // Create a table without the detail column
        Schema::create('models_without_detail', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $this->expectException(MissingDetailColumnException::class);
        $this->expectExceptionMessage(
            "The model [ServiceTo\UsesDetail\Tests\Models\ModelWithoutDetailColumn] uses the UsesDetail trait " .
            "but the table [models_without_detail] does not have a 'detail' column."
        );

        $model = new ModelWithoutDetailColumn();
        $model->name = 'Test'; // Non-schema attribute
        $model->save(); // This should throw the exception
    }

    /** @test */
    public function it_allows_saving_schema_attributes_without_detail_column()
    {
        // Create a table without the detail column but with a name column
        Schema::create('models_without_detail', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Should NOT throw an exception because 'name' is a schema column
        $model = new ModelWithoutDetailColumn();
        $model->name = 'Test';
        $model->save();

        $this->assertDatabaseHas('models_without_detail', [
            'id' => $model->id,
            'name' => 'Test',
        ]);
    }

    /** @test */
    public function it_throws_exception_when_querying_non_schema_column_without_detail_column()
    {
        // Create a table without the detail column
        Schema::create('models_without_detail', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->expectException(MissingDetailColumnException::class);

        // Trying to query a non-schema column should throw
        ModelWithoutDetailColumn::where('status', 'active')->get();
    }

    /** @test */
    public function it_allows_querying_schema_columns_without_detail_column()
    {
        // Create a table without the detail column
        Schema::create('models_without_detail', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $model = new ModelWithoutDetailColumn();
        $model->name = 'Test';
        $model->save();

        // Should NOT throw an exception because 'name' is a schema column
        $results = ModelWithoutDetailColumn::where('name', 'Test')->get();
        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_provides_helpful_error_message_with_migration_hint()
    {
        // Create a table without the detail column
        Schema::create('models_without_detail', function ($table) {
            $table->id();
            $table->timestamps();
        });

        try {
            $model = new ModelWithoutDetailColumn();
            $model->custom_field = 'value'; // Non-schema attribute
            $model->save();
            $this->fail('Expected MissingDetailColumnException was not thrown');
        } catch (MissingDetailColumnException $e) {
            $this->assertStringContainsString('detail', $e->getMessage());
            $this->assertStringContainsString('models_without_detail', $e->getMessage());
            $this->assertStringContainsString("\$table->json('detail')", $e->getMessage());
        }
    }
} 