<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\RecordsManagementeDataTable;
use App\Http\Controllers\Controller;
use App\Models\AcademicDegree;
use App\Models\CategoryOfRelation;
use App\Models\City;
use App\Models\GeneralCategory;
use App\Models\Data;
use App\Models\DisplacementStatus;
use App\Models\DocumentType;
use App\Models\Employment;
use App\Models\HealthStatus;
use App\Models\HousingStatus;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfAccommodation;
use App\Models\TypeOfGuarantee;
use Illuminate\Http\Request;
use PhpParser\Comment\Doc;

class RecordsManagementController extends Controller
{
    public function index(RecordsManagementeDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.records_management.index');
    }
    public function create()
    {
        $generalSection = GeneralCategory::all();
        $file_id_number = generateFiveDigitCode(Data::class, 'file_id_number');
        $category_of_relationship = CategoryOfRelation::all();
        $marital_status = MaritalStatus::all();
        $academic_qualification = AcademicDegree::all();
        $displacement_status = DisplacementStatus::all();
        $city = City::all();
        $province = Province::all();
        $health_status = HealthStatus::all();
        $employment_status_breadwinner = Employment::all();
        $HousingStatus = HousingStatus::all();
        $TypeOfAccommodation = TypeOfAccommodation::all();
        $documentTypes = DocumentType::all(); // Assuming you have a DocumentType model
        $sponsorship_status = SponsorshipStatus::all();
        $guarantee_types = TypeOfGuarantee::all(); // Assuming you have a TypeOfGuarantee model
        return view('admin.dashboard.records_management.create',
         compact(
            'generalSection',
            'file_id_number',
            'category_of_relationship',
            'marital_status',
            'academic_qualification',
            'displacement_status',
            'city',
            'province',
            'health_status',
            'employment_status_breadwinner',
            'HousingStatus',
            'TypeOfAccommodation',
            'documentTypes',
            'sponsorship_status',
            'guarantee_types',
        ));
    }
    public function store(Request $request)
    {
        // Logic to store a new record
        // Validate and save the data
        // Redirect or return a response
    }
//     public function edit($id)
//     {
//         // Logic to show the form for editing an existing record
//         // Fetch the record by ID and pass it to the view
//         return view('admin.dashboard.records_management.edit', compact('id'));
//     }
//     public function update(Request $request, $id)
//     {
//         // Logic to update an existing record
//         // Validate and update the data
//         // Redirect or return a response
//     }
//     public function destroy($id)
//     {
//         // Logic to delete an existing record
//         // Find the record by ID and delete it
//         // Redirect or return a response
//     }
}
