<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ListingController extends Controller
{
    public function publicIndex()
    {
        // Track unique visits or simple page views for this month
        $cacheKey = 'website_visits_' . date('Y_m');
        \Illuminate\Support\Facades\Cache::increment($cacheKey);

        // For public pages, we might only want to show available listings
        $listings = Listing::with('user')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['data' => $listings]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Listing::with('user');
        
        if ($user->role === 'homeadvisor') {
            $query->where('user_id', $user->id);
        }
        
        $listings = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json(['data' => $listings]);
    }

    public function show(Request $request, $id)
    {
        $listing = Listing::with('user')->findOrFail($id);
        
        if ($request->user()->role === 'homeadvisor' && $listing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json(['data' => $listing]);
    }

    public function store(Request $request)
    {
$validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:available,sold_out',
            'image' => 'nullable|image|max:5120', // keep for backwards compatibility if needed
            'images.*' => 'nullable|image|max:5120',
            'images' => 'nullable|array|max:5',
            'category' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'is_rent' => 'boolean',
            'price' => 'nullable|numeric',
            'price_formatted' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'area' => 'nullable|integer',
            'legal_status' => 'nullable|string|max:255',
            'electricity' => 'nullable|string|max:255',
            'garage' => 'nullable|string|max:255',
            'features' => 'nullable|json',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $disk = config('filesystems.disks.s3.bucket') ? 's3' : config('filesystems.default', 'public');
        try {
            $path = $file->store('listings', $disk);
        } catch (\Exception $e) {
            return response()->json(['message' => 'S3/Upload Error: ' . $e->getMessage()], 500);
        }
            if ($disk === 's3') {
                $imagePath = \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
            } else {
                $imagePath = 'storage/' . $path;
            }
        }

        $imagesPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $disk = config('filesystems.disks.s3.bucket') ? 's3' : config('filesystems.default', 'public');
        try {
            $path = $file->store('listings', $disk);
        } catch (\Exception $e) {
            return response()->json(['message' => 'S3/Upload Error: ' . $e->getMessage()], 500);
        }
                if ($disk === 's3') {
                    $imagesPaths[] = \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
                } else {
                    $imagesPaths[] = 'storage/' . $path;
                }
            }
        }

        unset($validated['image']);
        unset($validated['images']);

        $listing = Listing::create(array_merge($validated, [
            'image_url' => $imagePath,
            'images' => empty($imagesPaths) ? null : $imagesPaths,
            'user_id' => $request->user()->id,
        ]));

        return response()->json(['message' => 'Properti berhasil ditambahkan', 'data' => $listing], 201);
    }
    
    public function update(Request $request, $id)
    {
        $listing = Listing::findOrFail($id);
        
        if ($request->user()->role === 'homeadvisor' && $listing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:available,sold_out',
            'image' => 'nullable|image|max:5120',
            'images.*' => 'nullable|image|max:5120',
            'images' => 'nullable|array|max:5',
            'category' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'is_rent' => 'boolean',
            'price' => 'nullable|numeric',
            'price_formatted' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'area' => 'nullable|integer',
            'legal_status' => 'nullable|string|max:255',
            'electricity' => 'nullable|string|max:255',
            'garage' => 'nullable|string|max:255',
            'features' => 'nullable|json',
            'existing_images' => 'nullable',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $disk = config('filesystems.disks.s3.bucket') ? 's3' : config('filesystems.default', 'public');
            try {
                $path = $file->store('listings', $disk);
            } catch (\Exception $e) {
                return response()->json(['message' => 'S3/Upload Error: ' . $e->getMessage()], 500);
            }
            if ($disk === 's3') {
                $imagePath = \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
            } else {
                $imagePath = 'storage/' . $path;
            }
            $listing->image_url = $imagePath;
        }

        // Handle existing images retained by user
        $keptImages = [];
        if ($request->has('existing_images')) {
            $rawExisting = $request->input('existing_images');
            if (is_string($rawExisting)) {
                $keptImages = json_decode($rawExisting, true) ?? [];
            } elseif (is_array($rawExisting)) {
                $keptImages = $rawExisting;
            }
        } elseif (!$request->hasFile('images')) {
            $currentImages = $listing->images;
            if (is_string($currentImages)) {
                $keptImages = json_decode($currentImages, true) ?? [];
            } elseif (is_array($currentImages)) {
                $keptImages = $currentImages;
            }
        }

        $newImagesPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $disk = config('filesystems.disks.s3.bucket') ? 's3' : config('filesystems.default', 'public');
                try {
                    $path = $file->store('listings', $disk);
                } catch (\Exception $e) {
                    return response()->json(['message' => 'S3/Upload Error: ' . $e->getMessage()], 500);
                }
                if ($disk === 's3') {
                    $newImagesPaths[] = \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
                } else {
                    $newImagesPaths[] = 'storage/' . $path;
                }
            }
        }

        if ($request->has('existing_images') || $request->hasFile('images')) {
            $combinedImages = array_values(array_filter(array_merge($keptImages, $newImagesPaths)));
            $listing->images = empty($combinedImages) ? null : array_slice($combinedImages, 0, 5);
        }

        unset($validated['image']);
        unset($validated['images']);
        unset($validated['existing_images']);

        $listing->fill($validated);
        $listing->save();

        return response()->json(['message' => 'Properti berhasil diupdate', 'data' => $listing]);
    }
    
    public function updateStatus(Request $request, $id)
    {
        $listing = Listing::findOrFail($id);
        
        // Check authorization
        if ($request->user()->role === 'homeadvisor' && $listing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'status' => 'required|in:available,sold_out',
        ]);
        
        $listing->update(['status' => $validated['status']]);
        
        return response()->json(['message' => 'Status berhasil diubah', 'data' => $listing]);
    }
    
    public function destroy(Request $request, $id)
    {
        $listing = Listing::findOrFail($id);
        
        if ($request->user()->role === 'homeadvisor' && $listing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $listing->delete();
        
        return response()->json(['message' => 'Properti berhasil dihapus']);
    }
}




