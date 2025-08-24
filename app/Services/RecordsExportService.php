<?php

namespace App\Services;

use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordsExportService
{
    /**
     * Export all records from Data, DeadPepole and RePeople into one Excel file
     * with three sheets. Returns a StreamedResponse for download.
     *
     * @return StreamedResponse
     */
    public function exportAll(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Data
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        $dataHeaders = [
            'id','file_id_number','data_section_id','data_id_number','data_first_name','data_father_name','data_grand_father_name','data_family_name','data_relationship','data_birth_date','data_gender','data_phone_number','data_alt_phone_number','data_number_of_individuals','data_marital_status','data_academic_qualification','data_displacement_status','data_address_before_displacement','data_current_address','data_city','data_province','data_health_status','data_description_needs','data_user_insert_data','created_at','updated_at'
        ];

        $col = 'A';
        foreach ($dataHeaders as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $rowNum = 2;
        foreach (Data::cursor() as $row) {
            $col = 'A';
            foreach ($dataHeaders as $key) {
                $sheet->setCellValue($col . $rowNum, $row->{$key} ?? null);
                $col++;
            }
            $rowNum++;
        }

        // Sheet 2: DeadPepole
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('DeadPepole');

        $deadHeaders = [
            'id','re_file_id','father_first_name','father_second_name','father_third_name','father_last_name','father_id','father_death_date','father_death_reason','mother_first_name','mother_second_name','mother_third_name','mother_last_name','mother_id','mother_death_date','mother_death_reason','created_at','updated_at'
        ];

        $col = 'A';
        foreach ($deadHeaders as $header) {
            $sheet2->setCellValue($col . '1', $header);
            $col++;
        }

        $rowNum = 2;
        foreach (DeadPepole::cursor() as $row) {
            $col = 'A';
            foreach ($deadHeaders as $key) {
                $sheet2->setCellValue($col . $rowNum, $row->{$key} ?? null);
                $col++;
            }
            $rowNum++;
        }

        // Sheet 3: RePeople
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('RePeople');

        $repHeaders = [
            'id','registration_id','sponsorship_status','first_name','second_name','third_name','last_name','person_id','person_birth_date','person_age','person_gender','person_health_status','person_type_of_guarantee','created_at','updated_at'
        ];

        $col = 'A';
        foreach ($repHeaders as $header) {
            $sheet3->setCellValue($col . '1', $header);
            $col++;
        }

        $rowNum = 2;
        foreach (RePeople::cursor() as $row) {
            $col = 'A';
            foreach ($repHeaders as $key) {
                $sheet3->setCellValue($col . $rowNum, $row->{$key} ?? null);
                $col++;
            }
            $rowNum++;
        }

        // Prepare writer and streamed response
        $writer = new Xlsx($spreadsheet);
        $fileName = 'records_export_' . date('Ymd_His') . '.xlsx';

        $response = new StreamedResponse(function () use ($writer) {
            // Ensure output buffer is clean
            if (ob_get_length()) {
                ob_end_clean();
            }
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
