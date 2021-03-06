<?php

namespace Fusion\Fieldtypes;

use File;
use Fusion\Models\Field;
use Illuminate\Support\Str;
use Fusion\Http\Resources\FormResource;

class FormFieldtype extends Fieldtype
{
    /**
     * @var string
     */
    public $name = 'Form';

    /**
     * @var string
     */
    public $icon = 'clipboard';

    /**
     * @var string
     */
    public $description = 'A set of forms';

    /**
     * @var string
     */
    public $relationship = 'morphToMany';

    /**
     * @var array
     */
    public $settings = [
        'limit' => null,
    ];

    /**
     * @var string
     */
    public $namespace = 'Fusion\Models\Form';

    /**
     * Generate relationship methods for associated Model.
     *
     * @param  Fusion\Models\Field $field
     * @return string
     */
    public function generateRelationship($field)
    {
        $stub = File::get(fusion_path("/stubs/relationships/{$this->relationship}.stub"));

        return strtr($stub, [
            '{handle}'            => $field->handle,
            '{studly_handle}'     => Str::studly($field->handle),
            '{related_pivot_key}' => 'form_id',
            '{related_namespace}' => $this->namespace,
            '{related_table}'     => 'forms_pivot',
            '{where_clause}'      => "->where('field_id', {$field->id})",
            '{order_clause}'      => "->orderBy('order')",
        ]);
    }

    /**
     * Update relationship data in storage.
     *
     * @param  Illuminate\Eloquent\Model  $model
     * @param  Fusion\Models\Field        $field
     * @return void
     */
    public function persistRelationship($model, Field $field)
    {
        $oldValues = $model->{$field->handle}->pluck('id');
        $newValues = collect(request()->input($field->handle))->mapWithKeys(function($item, $key) use ($field) {
            return [
                $item['id'] => [
                    'field_id' => $field->id,
                    'order'    => $key + 1
                ]];
        });

        $model->{$field->handle}()->detach($oldValues);
        $model->{$field->handle}()->attach($newValues);
    }

    /**
     * Returns resource object of field.
     *
     * @param  Illuminate\Eloquent\Model  $model
     * @param  Fusion\Models\Field        $field
     * @return \Fusion\Http\Resources\FormResource
     */
    public function getResource($model, Field $field)
    {
        return FormResource::collection($this->getValue($model, $field));
    }
}
