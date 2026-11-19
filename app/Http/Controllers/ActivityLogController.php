<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();
        $query = ActivityLog::with($isAdmin ? ['actor', 'store'] : ['actor'])->latest('id');

        // An owner sees only what happened in their store. The admin sees everything: every
        // store, plus company-wide entries (store_id null: admin logins, global settings, backups).
        if (!$isAdmin) {
            $query->where('store_id', CurrentStore::id() ?? 0);
        } elseif ($request->input('store') === 'global') {
            $query->whereNull('store_id');
        } elseif ($request->filled('store')) {
            $query->where('store_id', $request->integer('store'));
        }

        // Filter by action type
        if ($request->input('action') === 'security') {
            $query->whereIn('action', ActivityLog::SECURITY_ACTIONS);
        } elseif ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by subject type
        if ($request->filled('subject_type')) {
            $query->where('subject_type', 'App\\Models\\' . $request->subject_type);
        }

        $logs = $query->paginate(20);

        $actionTypes = array_merge(['created', 'updated', 'deleted', 'security'], ActivityLog::SECURITY_ACTIONS);
        $subjectTypes = ['Order', 'Customer', 'Payment', 'CreditNote', 'Expense', 'Invoice', 'User', 'SiteSetting', 'Service'];
        // Integrity of the whole trail (hash chain), shown as a badge.
        $integrity = ActivityLog::verifyChain();

        $stores = $isAdmin ? Store::orderBy('name')->get() : collect();

        return view('activity-logs.index', compact('logs', 'actionTypes', 'subjectTypes', 'integrity', 'stores'));
    }
}
