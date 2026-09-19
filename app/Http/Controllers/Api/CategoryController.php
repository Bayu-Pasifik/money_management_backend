<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->categories()->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $category = $request->user()->categories()->create($data);

        return response()->json($category, 201);
    }

    public function show(Request $request, Category $category)
    {
        $this->authorizeOwner($request, $category);

        return $category;
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeOwner($request, $category);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:income,expense'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $category->update($data);

        return $category;
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorizeOwner($request, $category);
        $category->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, Category $category): void
    {
        abort_if($category->user_id !== $request->user()->id, 403);
    }
}
