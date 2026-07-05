<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::active()->get(['id', 'name', 'days_per_week', 'cost', 'description']);
        return response()->json(['packages' => $packages]);
    }
}
