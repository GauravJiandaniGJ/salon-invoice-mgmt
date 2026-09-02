<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreServiceCategoryRequest;
use App\Http\Requests\Catalog\UpdateServiceCategoryRequest;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceCategoryController extends Controller
{
    public function store(StoreServiceCategoryRequest $request): RedirectResponse
    {
        ServiceCategory::create([
            ...$request->validated(),
            'sort_order' => ((int) ServiceCategory::max('sort_order')) + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Category added.');
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->update($request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        if ($serviceCategory->services()->exists()) {
            return back()->with('error', 'Move or delete the services in this category first.');
        }

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
