<?php

namespace App\Exports;

use App\Services\CashImageService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;

class CashParchaseExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStrictNullComparison,
    ShouldAutoSize,
    WithStyles
{
    protected $searchParams;
    protected $cashImageService;
    protected $invoiceCounter = 1;

    public function __construct(array $searchParams = [])
    {
        $this->searchParams = $searchParams;
        $this->cashImageService = app(CashImageService::class);
    }

    /**
     * Get the data collection for export
     */
    public function collection()
    {
        // Convert array to Request object for the service
        $request = new Request($this->searchParams);

        // Get all summary data without pagination
        $result = $this->cashImageService->getAllParchaseForExport($request);

        // Check for error response or empty data
        if ($result['status'] !== 1 || empty($result['result']['data']) || empty($result['result']['data']['data'])) {
            return collect([]);
        }

        return collect($result['result']['data']['data']);
    }

    /**
     * Define the headings for the CSV - matching frontend table
     */
    public function headings(): array
    {
        return [
            'Date',
            'EXP Number',
            'Company',
            'S. Total Value',
            'S. Amount VAT',
            'I. Total Value',
            'I. Amount VAT',
            'C. Total Value',
            'C. Amount VAT',
            'Hotel',
            'Attraction',
            'Cash Amount',
            'Deposit Count',
            'Date Time',
            'Crm Number',
        ];
    }

    /**
     * Map each row of data - matching frontend logic
     */
    public function map($cashImage): array
    {
        // Get the selected month from search params or current month
        $selectedMonth = isset($this->searchParams['month']) ? $this->searchParams['month'] : date('n');

        // Generate EXP Number like frontend: "EXP" + "0" + selectedMonth + "000" + (index + 1)
        static $counter = 0;
        $counter++;
        $expNumber = "EXP0" . $selectedMonth . "000" . $counter;

        // Get company legal name from relatable items
        $companyName = '';
        if (isset($cashImage['relatable']['items'][0]['product']['legal_name'])) {
            $companyName = $cashImage['relatable']['items'][0]['product']['legal_name'];
        }

        // Calculate S. Total Value (like getTotalValue function)
        $sTotalValue = '';
        if ($cashImage['relatable_type'] == 'App\\Models\\BookingItemGroup' && isset($cashImage['relatable']['items'])) {
            $total = 0;
            foreach ($cashImage['relatable']['items'] as $item) {
                $total += $item['total_cost_price'] ?? 0;
            }
            $sTotalValue = $this->formatAmount($total);
        }

        // Calculate S. Amount VAT
        $sAmountVat = isset($cashImage['vat']) ? $this->formatAmount($cashImage['vat']) : '-';

        // Calculate I. Total Value (like calculateInvoiceTotal function)
        $iTotalValue = '';
        if (isset($cashImage['relatable']['booking_confirm_letter'])) {
            $total = 0;
            foreach ($cashImage['relatable']['booking_confirm_letter'] as $item) {
                $total += $item['meta']['total_after_tax'] ?? 0;
            }
            $iTotalValue = $this->formatAmount($total);
        }

        // Calculate I. Amount VAT (like calculateInvoiceVat function)
        $iAmountVat = '';
        if (isset($cashImage['relatable']['booking_confirm_letter'])) {
            $total = 0;
            foreach ($cashImage['relatable']['booking_confirm_letter'] as $item) {
                $total += $item['meta']['total_tax_withold'] ?? 0;
            }
            $iAmountVat = $this->formatAmount($total);
        }

        // Calculate C. Total Value (like calculateTaxTotal function)
        $cTotalValue = '';
        if (isset($cashImage['relatable']['tax_credit'])) {
            $total = 0;
            foreach ($cashImage['relatable']['tax_credit'] as $item) {
                $total += $item['total_after_tax'] ?? 0;
            }
            $cTotalValue = $this->formatAmount($total);
        }

        // Calculate C. Amount VAT (like calculateTaxVAT function)
        $cAmountVat = '';
        if (isset($cashImage['relatable']['tax_credit'])) {
            $total = 0;
            foreach ($cashImage['relatable']['tax_credit'] as $item) {
                $total += $item['total_tax_withold'] ?? 0;
            }
            $cAmountVat = $this->formatAmount($total);
        }

        // Hotel check
        $hotel = ($cashImage['relatable']['product_type'] ?? '') == "App\\Models\\Hotel" ? '✓' : '-';

        // Attraction check
        $attraction = ($cashImage['relatable']['product_type'] ?? '') == "App\\Models\\EntranceTicket" ? '✓' : '-';

        // Date Time format
        $dateTime = $this->formatDateForExport($cashImage['date'] ?? '') . ' , ' . $this->formatTimeForExport($cashImage['date'] ?? '');

        return [
            $this->formatDateForExport($cashImage['date'] ?? ''),           // Date
            $expNumber,                                                      // EXP Number
            $companyName,                                                    // Company
            $sTotalValue,                                                    // S. Total Value
            $sAmountVat,                                                     // S. Amount VAT
            $iTotalValue,                                                    // I. Total Value
            $iAmountVat,                                                     // I. Amount VAT
            $cTotalValue,                                                    // C. Total Value
            $cAmountVat,                                                     // C. Amount VAT
            $hotel,                                                          // Hotel
            $attraction,                                                     // Attraction
            $this->formatAmount($cashImage['amount'] ?? 0),                  // Cash Amount
            'final deposit',                                                 // Deposit Count
            $dateTime,                                                       // Date Time
            $cashImage['crm_id'] ?? '',                                      // Crm Number
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold
            1 => ['font' => ['bold' => true]],

            // Set background color for header
            'A1:O1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE2EFDA']
                ]
            ]
        ];
    }

    /**
     * Format date for export
     */
    private function formatDateForExport($dateString)
    {
        if (!$dateString) return '';

        try {
            $date = new \DateTime($dateString);
            return $date->format('d M y');
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    /**
     * Format amount for export
     */
    private function formatAmount($amount)
    {
        if (!$amount || $amount == 0) return '';
        return number_format($amount, 2);
    }

    /**
     * Format time for export
     */
    private function formatTimeForExport($timeString)
    {
        if (!$timeString) return '';

        try {
            $time = new \DateTime($timeString);
            return $time->format('H:i');
        } catch (\Exception $e) {
            return $timeString;
        }
    }
}
