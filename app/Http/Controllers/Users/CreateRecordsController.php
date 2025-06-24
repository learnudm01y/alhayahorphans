<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CreateRecordsController extends Controller
{
    public function index()
    {
        return view('user.dashboard.toolbars.index');
    }
}
