<?php

namespace App\Http\Controllers;

use App\Services\StatusCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(Request $request, StatusCollector $collector): View
    {
        return view('status', $collector->collect($request->boolean('refresh')));
    }

    public function json(Request $request, StatusCollector $collector): JsonResponse
    {
        return response()->json($collector->collect($request->boolean('refresh')));
    }
}
