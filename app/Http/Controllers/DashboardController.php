<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Office;
use App\Models\ProfileRequest;
use App\Models\Designation;
use App\Models\TransferHistory;
use App\Models\PromotionHistory;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Basic Counters
        $counts = [
            'employees' => Employee::where('status', 'active')->count(),
            'offices'   => Office::count(),
            'requests'  => ProfileRequest::where('status', 'pending')->count(),
            'designations' => Designation::count(),
        ];

        // 2. Office Density (Top 5 offices with most employees)
        $officeStats = Office::withCount(['employees' => function ($query) {
            $query->where('status', 'active');
        }])
        ->orderByDesc('employees_count')
        ->take(5)
        ->get();

        // 3. Recent Activity Feed (Merge Transfers & Promotions)
        $transfers = TransferHistory::with(['employee', 'toOffice'])
            ->latest('transfer_date')
            ->take(3)
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'transfer',
                    'date' => $item->transfer_date,
                    'text' => "{$item->employee->first_name} transferred to {$item->toOffice->name}",
                    'id' => $item->id
                ];
            });

        $promotions = PromotionHistory::with(['employee'])
            ->latest('promotion_date')
            ->take(3)
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'promotion',
                    'date' => $item->promotion_date,
                    'text' => "{$item->employee->first_name} promoted to {$item->new_designation}",
                    'id' => $item->id
                ];
            });

        $activity = $transfers->merge($promotions)->sortByDesc('date')->values()->take(5);

        return response()->json([
            'counts' => $counts,
            'office_stats' => $officeStats,
            'activity' => $activity
        ]);
    }
}