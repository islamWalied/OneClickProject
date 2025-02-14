<?php

namespace App\Traits;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


trait ImageTrait
{
    public function saveImage($requestOrArray, $property, $directoryName, $location = 'public')
    {



        // if($request->hasFile($property))
        // return $request->file($property)->store($directoryName,$location);



        if (is_array($requestOrArray) && isset($requestOrArray[$property])) {
            return $requestOrArray[$property]->store($directoryName, $location);
        }

        if ($requestOrArray instanceof Request && $requestOrArray->hasFile($property)) {
            return $requestOrArray->file($property)->store($directoryName, $location);
        }

        return null;
    }



    // public function updateImage($request,$property,$directoryName,$model,$location = 'public')
    // {
    //     if ($model)
    //         Storage::disk($location)->delete($model);
    //     if($request->hasFile($property))
    //         return $request->file($property)->store($directoryName,$location);
    // }


    public function updateImage($request, $property, $directoryName, $model = null, $location = 'public')
    {
        // Delete the existing file if applicable
        if ($model) {
            Storage::disk($location)->delete($model);
        }

        // Save the new file if provided
        if ($request->hasFile($property)) {
            return $request->file($property)->store($directoryName, $location);
        }

        return $model;
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
