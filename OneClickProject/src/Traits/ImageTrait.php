<?php

namespace App\Traits;


use Illuminate\Support\Facades\Storage;

trait ImageTrait
{
    public function saveImage($request,$property,$directoryName,$location = 'public')
    {
        if($request->hasFile($property))
            return $request->file($property)->store($directoryName,$location);
    }
    public function updateImage($request,$property,$directoryName,$model,$location = 'public')
    {
        if($request->hasFile($property))
        {
            if ($model)
                Storage::disk($location)->delete($model);
            return $request->file($property)->store($directoryName,$location);
        }
    }
    public function deleteImage($model,$location = 'public'): void
    {
        if ($model)
            Storage::disk($location)->delete($model);
    }
    public function saveManyImages($request,$property,$directoryName,$model,$relationFunction,$foreignKey,$location = 'public'): void
    {
        if ($request->hasFile($property)) {
            foreach ($request->file($property) as $image) {
                $imageName = $image->store($directoryName,$location);
                $model->$relationFunction()->create([
                    $property => $imageName,
                    $foreignKey => $model->id,
                ]);
            }
        }
    }
    public function updateManyImages($request,$property,$directoryName,$model,$relationFunction,$foreignKey,$location = 'public'): void
    {
        if ($request->hasFile($property)) {
            foreach ($model->$relationFunction as $image) {
                Storage::disk($location)->delete($image->$property);
                $image->delete();
            }
        }
        if ($request->hasFile($property)) {
            foreach ($request->file($property) as $image) {
                $imageName = $image->store($directoryName,$location);
                $model->$relationFunction()->create([
                    $property => $imageName,
                    $foreignKey => $model->id,
                ]);
            }
        }
    }
    public function deleteManyImage($property,$model,$relationFunction,$location = 'public'): void
    {
        foreach ($model->$relationFunction as $image) {
            Storage::disk($location)->delete($image->$property);
            $image->delete();
        }
    }
}
