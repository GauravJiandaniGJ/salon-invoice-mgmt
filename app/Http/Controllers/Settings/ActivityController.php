<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Services\CsvExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/Activity', [
            'filters' => $this->filters($request),
            'activities' => $this->query($request)->paginate(50)->withQueryString()
                ->through(fn (Activity $a) => $this->row($a)),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
            'actions' => collect(Activity::LABELS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->limit(5000)->get()
            ->map(fn (Activity $a) => [
                $a->created_at->format('Y-m-d H:i'),
                $a->user_name,
                $a->action_label,
                $a->subject_label ?? '',
                $a->description,
                $a->ip_address ?? '',
            ]);

        return CsvExporter::stream(
            'activity-'.now()->toDateString().'.csv',
            ['When', 'User', 'Action', 'Item', 'Details', 'IP'],
            $rows,
        );
    }

    /** @return array<string, string> */
    protected function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query('q', ''),
            'action' => (string) $request->query('action', ''),
            'user_id' => (string) $request->query('user_id', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];
    }

    protected function query(Request $request)
    {
        $f = $this->filters($request);

        return Activity::query()
            ->with('user:id,name')
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.$f['q'].'%')
                ->orWhere('subject_label', 'like', '%'.$f['q'].'%')
                ->orWhere('user_name', 'like', '%'.$f['q'].'%')))
            ->when($f['action'] !== '', fn ($q) => $q->where('action', $f['action']))
            ->when($f['user_id'] !== '', fn ($q) => $q->where('user_id', $f['user_id']))
            ->when($f['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $f['from']))
            ->when($f['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $f['to']))
            ->latest('created_at')
            ->latest('id');
    }

    /** @return array<string, mixed> */
    protected function row(Activity $a): array
    {
        return [
            'id' => $a->id,
            'created_at' => $a->created_at->toIso8601String(),
            'user_name' => $a->user_name,
            'action' => $a->action,
            'action_label' => $a->action_label,
            'subject_type' => $a->subject_type,
            'subject_id' => $a->subject_id,
            'subject_label' => $a->subject_label,
            'description' => $a->description,
            'changes' => $a->changes,
            'ip_address' => $a->ip_address,
        ];
    }
}
