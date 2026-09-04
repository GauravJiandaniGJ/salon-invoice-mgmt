<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreServiceCategoryRequest;
use App\Http\Requests\Catalog\UpdateServiceCategoryRequest;
use App\Models\Activity;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceCategoryController extends Controller
{
    public function store(StoreServiceCategoryRequest $request): RedirectResponse
    {
        $category = ServiceCategory::create([
            ...$request->validated(),
            'sort_order' => ((int) ServiceCategory::max('sort_order')) + 1,
            'is_active' => true,
        ]);

        Activity::log('category.created', 'For '.$category->audience, $category, null, $category->name);

        return back()->with('success', 'Category added.');
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->update($request->validated());

        Activity::log('category.updated', 'Category updated', $serviceCategory, null, $serviceCategory->name);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        if ($serviceCategory->services()->exists()) {
            return back()->with('error', 'Move or delete the services in this category first.');
        }

        Activity::log('category.deleted', 'Category removed', $serviceCategory, null, $serviceCategory->name);
        $serviceCategory->delete();

        return back()->with('success', 'Category deleted.');
    }

    /** Body: { ids: [3, 1, 2] } — full ordered list of category ids. */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:service_categories,id']])['ids'];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                ServiceCategory::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return back();
    }
}
