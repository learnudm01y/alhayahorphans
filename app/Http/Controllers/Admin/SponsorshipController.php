<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\DataTables\RecordsManagementeDataTable;
use Illuminate\Http\Request;

class SponsorshipController extends Controller
{
    /**
     * Display sponsored people
     */
    public function sponsored(RecordsManagementeDataTable $dataTable)
    {
        // TODO: Filter for sponsored people
        return $dataTable->render('admin.dashboard.sponsorships.sponsored');
    }

    /**
     * Display unsponsored people (same as records management)
     */
    public function unsponsored(RecordsManagementeDataTable $dataTable)
    {
        // Show all people (unsponsored for now)
        return $dataTable->render('admin.dashboard.sponsorships.unsponsored');
    }
}
