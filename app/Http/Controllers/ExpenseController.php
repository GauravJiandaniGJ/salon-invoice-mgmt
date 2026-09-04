<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $this->month($request->query('month'));
        $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->endOfMonth();
        $user = $request->user();

        $expenses = Expense::query()
            ->with('user:id,name')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $byMode = [];
        foreach (ReportService::MODES as $mode) {
            $byMode[$mode] = round((float) $expenses->where('payment_mode', $mode)->sum('amount'), 2);
        }

        $categories = collect(config('salon.expense_categories'))
            ->merge(Expense::query()->distinct()->orderBy('category')->pluck('category'))
            ->unique()
            ->values();

        return Inertia::render('expenses/Index', [
            'month' => $month,
            'expenses' => $expenses->map(fn (Expense $e) => [
                'id' => $e->id,
                'expense_date' => $e->expense_date->toDateString(),
                'category' => $e->category,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'payment_mode' => $e->payment_mode,
                'user' => ['id' => $e->user->id, 'name' => $e->user->name],
                'can_edit' => $this->canEdit($user, $e),
            ])->values(),
            'totals' => [
                'total' => round((float) $expenses->sum('amount'), 2),
                'by_mode' => $byMode,
            ],
            'categories' => $categories,
            'payment_modes' => Expense::PAYMENT_MODES,
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = Expense::create([...$request->validated(), 'user_id' => $request->user()->id]);

        Activity::log('expense.created', 'Expense added', $expense);

        return back()->with('success', 'Expense added.');
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        abort_unless($this->canEdit($request->user(), $expense), 403);

        $before = $expense->only(['category', 'description', 'amount', 'payment_mode']);
        $expense->update($request->validated());

        Activity::log('expense.updated', $expense->category.' · ₹'.number_format((float) $expense->amount), $expense, array_diff_assoc($expense->only(array_keys($before)), $before) ? ['from' => $before, 'to' => $expense->only(array_keys($before))] : null, $expense->description);

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($this->canEdit($request->user(), $expense), 403);

        Activity::log('expense.deleted', $expense->category.' · ₹'.number_format((float) $expense->amount), $expense, null, $expense->description);
        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }

    protected function canEdit(User $user, Expense $expense): bool
    {
        return $user->isOwner() || $expense->user_id === $user->id;
    }

    protected function month(?string $input): string
    {
        if ($input && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $input)) {
            return $input;
        }

        return CarbonImmutable::today()->format('Y-m');
    }
}
