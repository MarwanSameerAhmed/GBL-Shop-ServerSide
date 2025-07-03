<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Category;
use App\Models\Item;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoriesApiController extends Controller
{
    public function GetCategories()
    {
        $categories = DB::table('categories as c')
        ->select(
            'c.id',
            DB::raw('COALESCE(c.parent_id, 0) as parent_id'),
            'c.category_name',
            'c.category_en_name',
            'images.image_name',
            DB::raw('CASE WHEN sub_c.id IS NULL THEN 0 ELSE 1 END as has_sub_categories'),
            DB::raw('CASE WHEN i.id IS NULL THEN 0 ELSE 1 END as has_items')
        )
            ->leftJoin('images', function ($join) {
                $join->on('c.id', '=', 'images.record_id')
                     ->where('images.is_category', true);
        })
            ->leftJoin('categories as sub_c', 'c.id', '=', 'sub_c.parent_id')
            ->leftJoin('items as i', 'c.id', '=', 'i.parent_id')
            ->distinct()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'parent_id' => $item->parent_id,
                    'category_name' => $item->category_name,
                    'category_en_name' => $item->category_en_name,
                    'image_url' => $item->image_name ? Storage::disk('public')->url( $item->image_name) : null,
                    'has_sub_categories' => (bool) $item->has_sub_categories,
                    'has_items' => (bool) $item->has_items,
                ];
        });

        return response()->json($categories);
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
        $path = 'images/' . $fileName;

        // تخزين الصورة
        Storage::disk('public')->put($path, $imageData);

        $image = Image::create([
            'image_name' => $path,
            'record_id' => $category->id,
            'is_category' => true,
        ]);
        return response()->json([
            'state' => true,
            'data' => $category,
        ]);
    }

    public function DeleteCategory(Request $request)
    {
        $validator = Validator::make(
            $request->all(),[
                'category_id' => 'required|integer',
            ],
            [
                'category_id.required' => 'يجب ارسال رقم القسم المراد حذفه.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $category = Category::where('id', $request->category_id)->first();
        if (!$category){
            return response()->json([
                'success' => false,
                'errors' => "القسم غير موجود",
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $sub_category = Category::where('parent_id', $category->id)->first();
        if ($sub_category){
            return response()->json([
                'success' => false,
                'errors' => "يجب حذف جميع الأقسام الفرعية اولا",
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $item = Item::where('parent_id', $category->id)->first();
        if ($item){
            return response()->json([
                'success' => false,
                'errors' => "يجب حذف جميع المنتجات اولا",
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $images = Image::where('record_id', $category->id)
        ->where('is_category', true)
        ->pluck('image_name')
        ->toArray();

        foreach($images as $image){
            if(!Storage::disk('public')->delete('images/' . $image)){
                return response()->json([
                    'success' => false,
                    'errors' => "فشلت عمليت حذف الصورة",
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }
        }

        Image::where('record_id', $category->id)->where('is_category', false)->delete();
        $category->delete();
        if ($category->parent_id == null)
            $category->parent_id = 0;
        return response()->json([
            'state' => true,
            'data' => $category
        ]);
    }

}
