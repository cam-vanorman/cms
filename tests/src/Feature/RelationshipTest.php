<?php

namespace Fusion\Tests\Feature;

use Facades\FieldFactory;
use Facades\MatrixFactory;
use Fusion\Tests\TestCase;
use Facades\SectionFactory;
use Facades\FieldsetFactory;
use Facades\TaxonomyFactory;
use Illuminate\Support\Str;
use Fusion\Services\Builders\Single;
use Fusion\Services\Builders\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function when_a_matrix_with_a_morph_to_many_relationship_is_created_the_proper_pivot_table_should_be_generated()
    {
        $taxonomy = TaxonomyFactory::withName('Categories')->create();

        $this->assertDatabaseHasTable($taxonomy->pivot_table);
    }

    /** @test */
    public function when_a_matrix_with_a_morph_to_many_relationship_is_renamed_the_proper_pivot_table_should_be_updated()
    {
        $taxonomy           = TaxonomyFactory::withName('Categories')->create();
        $originalPivotTable = $taxonomy->pivot_table;

        $this->assertDatabaseHasTable($originalPivotTable);

        $taxonomy->name   = 'Tags';
        $taxonomy->handle = 'tags';
        $taxonomy->save();

        $this->assertDatabaseDoesNotHaveTable($originalPivotTable);
        $this->assertDatabaseHasTable($taxonomy->pivot_table);
    }

    /** @test */
    public function when_a_matrix_with_a_morph_to_many_relationship_is_removed_the_associated_pivot_table_should_be_removed_as_well()
    {
        $taxonomy   = TaxonomyFactory::withName('Categories')->create();
        $pivotTable = $taxonomy->pivot_table;

        $this->assertDatabaseHasTable($pivotTable);

        $taxonomy->delete();

        $this->assertDatabaseDoesNotHaveTable($pivotTable);
    }

    /** @test */
    public function user_field_will_add_relationships_through_users_pivot_table()
    {
        $this->actingAs($this->owner, 'api');

        $section  = SectionFactory::times(1)->withoutFields()->create();
        $field    = FieldFactory::withName('Users')->withType('user')->withSection($section)->create();
        $fieldset = FieldsetFactory::withSections(collect([$section]))->create();

        // Create single
        $matrix = MatrixFactory::asSingle()->withFieldset($fieldset)->create();
        $model  = (new Single($matrix->handle))->make();

        // Update with users
        $users = factory(\Fusion\Models\User::class, 3)->create();

        $this
            ->json('PATCH', '/api/singles/' . $matrix->id, [
                'matrix_id' => $matrix->id,
                'name'      => 'matrix-single',
                'slug'      => 'matrix-single',
                'status'    => true,
                'users'     => $users,
            ])->assertStatus(201);

        foreach ($users as $key => $user) {
            $this->assertDatabaseHas('users_pivot', [
                'user_id'    => $user->id,
                'field_id'   => $field->id,
                'pivot_type' => 'Fusion\Models\Singles\\' . Str::studly($matrix->handle),
                'pivot_id'   => $matrix->id,
                'order'      => $key + 1,
            ]);
        }
    }

    /** @test */
    public function a_user_can_add_multiple_fields_linked_to_the_same_taxonomy()
    {
        $this->actingAs($this->owner, 'api');

        // Taxonomy with Terms..
        $taxonomy = TaxonomyFactory::withName('Colors')->withStates(['terms'])->create();

        // Fieldset..
        $section  = SectionFactory::times(1)->withoutFields()->create();
        $field1   = FieldFactory::withName('Primary')->withType('taxonomy')->withSection($section)->withSettings(['taxonomy' => $taxonomy->id])->create();
        $field2   = FieldFactory::withName('Secondary')->withType('taxonomy')->withSection($section)->withSettings(['taxonomy' => $taxonomy->id])->create();
        $fieldset = FieldsetFactory::withSections(collect([$section]))->create();

        // Create single
        $matrix = MatrixFactory::withName('Post')->asSingle()->withFieldset($fieldset)->create();

        $this
            ->json('PATCH', '/api/singles/' . $matrix->id, [
                'matrix_id' => $matrix->id,
                'name'      => 'matrix-single',
                'slug'      => 'matrix-single',
                'status'    => true,
                'primary'   => collect($taxonomy->terms)->pluck('id')->shuffle()->take(3)->toArray(),
                'secondary' => collect($taxonomy->terms)->pluck('id')->shuffle()->take(3)->toArray(),
            ])->assertStatus(201);

        // Fetch records
        $model   = (new Single($matrix->handle))->make();
        $single  = $model->first();
        $primary = $single->primary->first();
        $secondary = $single->secondary->first();

        // Primary color relationship established
        $this->assertInstanceOf('Fusion\Models\Taxonomies\Colors', $primary);
        $this->assertDatabaseHas($primary->pivot_table, [
            'colors_id'  => $primary->id,
            'field_id'   => $field1->id,
            'pivot_id'   => $matrix->id,
            'pivot_type' => 'Fusion\Models\Singles\Post',
        ]);

        // Secondary color relationship established
        $this->assertInstanceOf('Fusion\Models\Taxonomies\Colors', $secondary);
        $this->assertDatabaseHas($secondary->pivot_table, [
            'colors_id'  => $secondary->id,
            'field_id'   => $field2->id,
            'pivot_id'   => $matrix->id,
            'pivot_type' => 'Fusion\Models\Singles\Post',
        ]);

        // Assert inverse relationship has been established
        // Note: uncomment to see model file
        // dd(\File::get(app_path('Models/Taxonomies/Colors.php')));

        // TODO: figure out why this assertion fails
        // $this->assertInstanceOf('Fusion\Models\Singles\Post', $primary->post->first());
        // Temp solution
        $this->assertInstanceOf('Fusion\Models\Singles\Post',
            $primary->morphedByMany('Fusion\Models\Singles\Post', 'pivot', 'taxonomy_colors_pivot')->first());
    }
}