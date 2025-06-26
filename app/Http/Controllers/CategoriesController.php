<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Category;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoriesController extends Controller
{
    public function GetCategories()
    {
       $results = DB::select('
    SELECT DISTINCT
        mc.id,
        mc.category_name,
        mc.category_en_name,
        CASE WHEN sc.id IS NULL THEN 0 ELSE 1 END AS hasSubCategories,
        CASE WHEN items.id IS NULL THEN 0 ELSE 1 END AS hasSubItems
    FROM categories mc
    LEFT JOIN categories sc ON mc.id = sc.parent_id
    LEFT JOIN items ON mc.id = items.parent_id
    WHERE mc.parent_id IS NULL
      AND (sc.id IS NOT NULL OR items.id IS NOT NULL)
');
return response()->json($this->DBCategoriesToArray($results), 200, [], JSON_UNESCAPED_UNICODE);

    }



    public function GetSubCategories(Request $request, $categoryKey)
    {
      $categoryID = base64_decode($categoryKey);
$results = DB::select('
    SELECT DISTINCT
        mc.id,
        mc.category_name,
        mc.category_en_name,
        CASE WHEN sc.id IS NULL THEN 0 ELSE 1 END AS hasSubCategories,
        CASE WHEN items.id IS NULL THEN 0 ELSE 1 END AS hasSubItems
    FROM categories mc
    LEFT JOIN categories sc ON mc.id = sc.parent_id
    LEFT JOIN items ON mc.id = items.parent_id
    WHERE mc.parent_id = ?', [$categoryID]);

return response()->json($this->DBCategoriesToArray($results), 200, [], JSON_UNESCAPED_UNICODE);

    }


    private function DBCategoriesToArray($db_result)
    {
        $arrayResult = array_map(function($category) {
            $image = Image::where('record_id', $category->id)
                ->where('is_category', true)
                ->orderBy('id', 'asc')
                ->pluck('image_name')
                ->first() ?? '';
            $arrayItem = [];
            $routeController = $category->hasSubCategories == 1 ? 'Categories' : 'Items';
            $arrayItem['route'] = '/' . $routeController . '/' . base64_encode($category->id);
            $arrayItem['name'] = $category->category_name;
            $arrayItem['en_name'] = $category->category_en_name;
            $arrayItem['has_sub_categories'] = $category->hasSubCategories;
            $arrayItem['image'] = $image;
            return $arrayItem;
        }, $db_result);
        return $arrayResult;
    }


//===================================API=====================================


    public function GetApiMainCategories()
    {
        $results = DB::select('SELECT DISTINCT mc.id id, mc.category_name category_name FROM categories mc LEFT JOIN categories sc ON mc.id = sc.parent_id LEFT JOIN items ON mc.id = items.parent_id WHERE mc.parent_id IS NULL AND items.id IS NULL');
        return response()->json($this->DBApiCategoriesToArray($results), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function GetApiSubCategories()
    {
        $results = DB::select('SELECT DISTINCT mc.id id, mc.category_name category_name FROM categories mc LEFT JOIN categories sc ON mc.id = sc.parent_id WHERE sc.id IS NULL');
        return response()->json($this->DBApiCategoriesToArray($results), 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function DBApiCategoriesToArray($db_result)
    {
        $arrayResult = array_map(function($category) {
            $arrayItem = [];
            $arrayItem['id'] = $category->id;
            $arrayItem['category_name'] = $category->category_name;
            return $arrayItem;
        }, $db_result);
        return $arrayResult;
    }


    public function CreateCategory(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'category_name' => 'required|string|unique:categories,category_name',
                'category_en_name' => 'required|string|unique:categories,category_en_name',
                'parent_id' => 'nullable|integer',
                'image' => 'required|string',
            ],
            [
                'category_name.required' => 'الاسم العربي مطلوب.',
                'category_name.unique' => 'يوجد تصنيف بالاسم العربي مسبقًا.',
                'category_en_name.required' => 'الاسم الإنجليزي مطلوب.',
                'category_en_name.unique' => 'يوجد تصنيف بالاسم الإنجليزي مسبقًا.',
                'parent_id.integer' => 'معرف التصنيف الرئيسي يجب أن يكون رقمًا.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

         // استخراج نوع الصورة
         $base64Image = '';
         if (preg_match('/^data:image\/(\w+);base64,/', $request->image, $type)) {
            $base64Image = substr($request->image, strpos($request->image, ',') + 1);
            $extension = strtolower($type[1]); // jpg, png, gif, etc.
        } else {
            return response()->json(['error' => 'الصيغة غير صحيحة للصورة'], 422, [], JSON_UNESCAPED_UNICODE);
        }
        $base64Image = str_replace(' ', '+', $base64Image);
        $imageData = base64_decode($base64Image);
        if ($imageData === false) {
            return response()->json(['error' => 'فشل في فك تشفير الصورة'], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $parentId = $request->input('parent_id');
        if ($parentId == 0) {
            $parentId = null;
        }

        $category = Category::create([
            'category_name' => $request->input('category_name'),
            'category_en_name' => $request->input('category_en_name'),
            'parent_id' => $parentId,
        ]);

        $fileName = Str::uuid() . '.' . $extension;
        $image = Image::create([
            'image_name' => $fileName,
            'record_id' => $category->id,
            'is_category' => true,
        ]);

        $path = 'images/' . $fileName;

        // تخزين الصورة
        Storage::disk('public')->put($path, $imageData);
        return response()->json([
            'state' => true,
            'data' => $category,
        ]);
    }
}

