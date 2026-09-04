<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreServiceRequest;
use App\Http\Requests\Catalog\UpdateServiceRequest;
use App\Models\Activity;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        $categories = ServiceCategory::query()
            ->with('services')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'audience' => $category->audience,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'services' => $category->services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'group_name' => $service->group_name,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'price_max' => $service->price_max === null ? null : (float) $service->price_max,
                    'duration_minutes' => $service->duration_minutes,
                    'is_active' => $service->is_active,
                    'sort_order' => $service->sort_order,
                    'can_delete' => ! $service->isBilled(),
                ])->values(),
            ]);

        return Inertia::render('services/Index', [
            'categories' => $categories,
            'audiences' => ServiceCategory::AUDIENCES,
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] = ((int) Service::where('service_category_id', $data['service_category_id'])->max('sort_order')) + 1;
        $data['is_active'] = true;

        $service = Service::create($data);

        Activity::log('service.created', '₹'.number_format((float) $service->price), $service, null, $service->display_name);

        return back()->with('success', 'Service added.');
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $before = $service->only(['name', 'group_name', 'price', 'price_max', 'is_active']);
        $service->update($request->validated());
        $after = $service->only(array_keys($before));
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

        Activity::log(
            'service.updated',
            $changed ? implode(', ', $changed).' changed' : 'Saved',
            $service,
            $changed ? ['from' => array_intersect_key($before, array_flip($changed)), 'to' => array_intersect_key($after, array_flip($changed))] : null,
            $service->display_name,
        );

        return back()->with('success', 'Saved.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        if ($service->isBilled()) {
            return back()->with('error', 'This service has been billed before. Deactivate it instead of deleting.');
        }

        Activity::log('service.deleted', 'Removed from the catalog', $service, null, $service->display_name);
        $service->delete();

        return back()->with('success', 'Service deleted.');
    }

    /** Body: { ids: [...] } — ordered service ids within one category. */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:services,id']])['ids'];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                Service::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return back();
    }
}
