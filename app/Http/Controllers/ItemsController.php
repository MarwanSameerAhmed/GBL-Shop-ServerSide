<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\ArabicTextProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItemsController extends Controller
{
    public function Search(Request $request)
    {
        $itemName = $request->query('name');
        $language = $request->query('lang');
        if ($itemName == null) return response()->json([], 200, [], JSON_UNESCAPED_UNICODE);
        if ($language == 'ar'){
            $itemName = ArabicTextProcessor::processArabicText($itemName);
            $itemName = trim(str_replace(['مكينة', 'مكاين', 'اله'], '', $itemName));
        }
        $names = explode(' ', $itemName);

        $columnName = $language == 'ar' ? 'item_processed_name' : 'item_en_name';
        $condition = 'where 1=1';
        $parameters = [];
        $i = 1;
        foreach($names as $name){
            $condition = $condition . ' ' . " and $columnName like :name$i";
            $parameters["name$i"] = "%$name%";
            $i++;
        }

        $results = DB::select("
            SELECT id, item_name, item_en_name, item_short_description, item_en_short_description,
                   CASE WHEN price IS NULL THEN -1 ELSE price END as price
            FROM items $condition
        ", $parameters);

        return response()->json($this->DBItemsToArray($results), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function GetItems(Request $request, $categoryKey)
    {
        $categoryID = base64_decode($categoryKey);
        $results = DB::select('
            SELECT id, item_name, item_en_name, item_short_description, item_en_short_description,
                   CASE WHEN price IS NULL THEN -1 ELSE price END as price
            FROM items
            WHERE parent_id = ?', [$categoryID]);

        return response()->json($this->DBItemsToArray($results), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function GetItem(Request $request, $itemKey)
    {
        $itemID = base64_decode($itemKey);
        $results = DB::select('
            SELECT id, item_name, item_en_name, item_description, item_en_description,
                   item_short_description, item_en_short_description,
                   CASE WHEN price IS NULL THEN -1 ELSE price END as price
            FROM items
            WHERE id = ?', [$itemID]);

        return response()->json($this->DBItemToArray($results)[0], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function DBItemsToArray($db_result)
    {
        $arrayResult = array_map(function($item) {
            $image = Image::where('record_id', $item->id)
                 ->where('is_category', false)
                 ->orderBy('id', 'asc')
                 ->pluck('image_name')
                 ->first() ?? '';
            if ($image !== '') $image = Storage::disk('public')->url($image);

            return [
                'route' => '/Item/' . base64_encode($item->id),
                'name' => $item->item_name,
                'en_name' => $item->item_en_name,
                'short_description' => $item->item_short_description,
                'en_short_description' => $item->item_en_short_description,
                'price' => $item->price,
                'image' => $image,
            ];
        }, $db_result);

        return $arrayResult;
    }

    private function DBItemToArray($db_result)
    {
        $arrayResult = array_map(function($item) {
            $images = Image::where('record_id', $item->id)
                ->where('is_category', false)
                ->orderBy('id', 'asc')
                ->skip(1)
                ->take(10)
                ->pluck('image_name')
                ->map(fn($img) => Storage::disk('public')->url($img))
                ->toArray();

            return [
                'route' => '/Item/' . base64_encode($item->id),
                'name' => $item->item_name,
                'en_name' => $item->item_en_name,
                'description' => $item->item_description,
                'short_description' => $item->item_short_description,
                'en_description' => $item->item_en_description,
                'en_short_description' => $item->item_en_short_description,
                'price' => $item->price,
                'images' => $images,
            ];
        }, $db_result);

        return $arrayResult;
    }

    public function CreateItem(Request $request)
    {
        $validator = Validator::make(
            $request->all(), [
                'name' => 'required|string|max:150|unique:items,item_name',
                'en_name' => 'required|string|max:150|unique:items,item_en_name',
                'description' => 'nullable|string',
                'short_description' => 'nullable|string|max:150',
                'en_description' => 'nullable|string',
                'en_short_description' => 'nullable|string|max:150',
                'parent_id' => 'nullable|integer|exists:categories,id',
                'price' => 'nullable|numeric',
                'extrnal_image' => 'required|string',
                'images' => 'required|array',
                'images.*' => 'required|string',
            ],
            [
                'name.required' => 'الاسم العربي مطلوب.',
                'name.unique' => 'يوجد منتج بالاسم العربي مسبقًا.',
                'en_name.required' => 'الاسم الإنجليزي مطلوب.',
                'en_name.unique' => 'يوجد منتج بالاسم الإنجليزي مسبقًا.',
                'parent_id.integer' => 'معرف القسم الرئيسي يجب أن يكون رقمًا.',
                'parent_id.exists' => 'القسم غير موجود',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $savedImages = [];

        $result = $this->ValidateImage($savedImages, $request->extrnal_image);
        if ($result !== '')
            return response()->json(['error' => $result], 422, [], JSON_UNESCAPED_UNICODE);

        foreach ($request->images as $base64Image) {
            $result = $this->ValidateImage($savedImages, $base64Image);
            if ($result !== '')
                return response()->json(['error' => $result], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $item_processed_name = ArabicTextProcessor::processArabicText($request->input('name'));
        $item_processed_name = trim(str_replace(['مكينه', 'مكاين', 'اله'], '', $item_processed_name));

        $item = Item::create([
            'item_name' => $request->input('name'),
            'item_processed_name' => $item_processed_name,
            'item_en_name' => $request->input('en_name'),
            'item_description' => $request->input('description'),
            'item_short_description' => $request->input('short_description'),
            'item_en_short_description' => $request->input('en_short_description'),
            'item_en_description' => $request->input('en_description'),
            'price' => $request->input('price'),
            'parent_id' => $request->input('parent_id'),
        ]);

        foreach ($savedImages as $file) {
            $this->SaveItemImage($file[0], $file[1], $item->id);
        }

        return response()->json([
            'state' => true,
            'data' => $item,
            'image' => Storage::url( $file[0])
        ]);
    }

    private function SaveItemImage(string $imageName, string $imageData, int $recordId)
    {
        $path = 'images/' . $imageName;
        Storage::disk('public')->put($path, $imageData);
        Image::create([
            'image_name' => $path,
            'record_id' => $recordId,
            'is_category' => false,
        ]);
    }

    private function ValidateImage(array &$savedImages, string $base64Image): string
    {
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            return 'الصيغة غير صحيحة للصورة';
        }

        $extension = strtolower($type[1]);
        $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
        $base64Image = str_replace(' ', '+', $base64Image);
        $imageData = base64_decode($base64Image);

        if ($imageData === false) {
            return 'فشل في فك تشفير الصورة';
        }

        $fileName = Str::uuid() . '.' . $extension;
        $savedImages[] = [$fileName, $imageData];
        return '';
    }

    public function DeleteItem(Request $request)
    {
        $validator = Validator::make(
            $request->all(),[
                'item_name' => 'required|string',
            ],
            [
                'item_name.required' => 'يجب ارسال اسم المنتج المراد حذفه.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $item = Item::where('item_name', $request->item_name)->first();
        if (!$item){
            return response()->json([
                'success' => false,
                'errors' => "المنتج غير موجود",
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }
        $images = Image::where('record_id', $item->id)
        ->where('is_category', false)
        ->pluck('image_name')
        ->toArray();

        foreach($images as $image){
            if(!Storage::disk('public')->delete('images/' . $image)){
                return response()->json([
                    'success' => false,
                    'errors' => "فشلت عمليت حذف الصورة",
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }        }

        Image::where('record_id', $item->id)->where('is_category', false)->delete();
        $item->delete();

        return response()->json([
            'state' => true,
            'data' => $item
        ]);
    }
}
