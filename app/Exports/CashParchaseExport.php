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
     * Define the headings for the CSV - updated to match actual data structure
     */
    public function headings(): array
    {
        return [
            'TrDate',
            'EXP Number',
            'Cash Amount',
            'Tax Receipt Number',
            'Tax Receipt Date',
            'Supplier Name',
            'Supplier VAT ID',
            'Supplier Address',
            'Company Name',
            'Company VAT ID',
            'Company Address',
            'Order Description',
            'Sale Value',
            'VAT Value',
            'CRM IDs',
            'Invoice',
            'Tr. Datetime'
        ];
    }

    /**
     * Map each row of data - fixed to match actual data structure
     */
    public function map($cashImage): array
    {
        // Get the selected month from search params or current month
        $selectedMonth = isset($this->searchParams['month']) ? $this->searchParams['month'] : date('n');

        // Generate EXP Number like frontend: "EXP" + "0" + selectedMonth + "000" + (index + 1)
        static $counter = 0;
        $counter++;
        $expNumber = "EXP0" . $selectedMonth . "000" . $counter;

        // Get tax receipt information
        $taxReceiptNumber = '';
        $taxReceiptDate = '';
        if (isset($cashImage['relatable']['tax_credit']) && !empty($cashImage['relatable']['tax_credit'])) {
            $taxReceiptNumber = $cashImage['relatable']['tax_credit'][0]['invoice_number'] ?? '';
            $taxReceiptDate = $this->formatDateForExport($cashImage['relatable']['tax_credit'][0]['receipt_date'] ?? '');
        }

        // Get supplier information from sender
        $supplierName = $cashImage['relatable']['items'][0]['product']['vat_name'] ?? '';
        $supplierVatId = $cashImage['relatable']['items'][0]['product']['vat_id']; // Not available in current data structure
        $supplierAddress = $cashImage['relatable']['items'][0]['product']['vat_address']; // Not available in current data structure

        // Get order description from product type
        $orderDescription = '';
        if (isset($cashImage['product_type'])) {
            $orderDescription = str_replace('App\\Models\\', '', $cashImage['product_type']);
        }

        // Calculate Sale Value from relatable data
        $saleValue = '';
        $vatValue = '';
        if (isset($cashImage['relatable']['tax_credit'])) {
            $saleValue = $this->formatAmount($cashImage['relatable']['tax_credit'][0]['total_after_tax'] ?? 0);
            $vatValue = $this->formatAmount($cashImage['relatable']['tax_credit'][0]['total_tax_withold'] ?? 0);
        }

        // Get CRM ID
        $crmIds = '';
        if (isset($cashImage['relatable']['items']) && is_array($cashImage['relatable']['items'])) {
            for ($i = 0; $i < count($cashImage['relatable']['items']); $i++) {
                if (isset($cashImage['relatable']['items'][$i]['crm_id'])) {
                    $crmIds .= $cashImage['relatable']['items'][$i]['crm_id'] . ', ';
                }
            }
            // Remove the trailing comma and space
            $crmIds = rtrim($crmIds, ', ');
        }

        // Invoice status
        $invoiceStatus = isset($cashImage['has_invoice']) && $cashImage['has_invoice'] ? 'Yes' : 'No';

        // Date Time format
        $dateTime = $this->formatDateForExport($cashImage['date'] ?? '') . ' , ' . $this->formatTimeForExport($cashImage['date'] ?? '');

        return [
            $this->formatDateForExport($cashImage['date'] ?? ''),           // TrDate
            $expNumber,                                                      // EXP Number
            $this->formatAmount($cashImage['amount'] ?? 0),                  // Cash Amount
            $taxReceiptNumber,                                               // Tax Receipt Number
            $taxReceiptDate,                                                 // Tax Receipt Date
            $supplierName,                                                   // Supplier Name
            $supplierVatId,                                                  // Supplier VAT ID
            $supplierAddress,                                                // Supplier Address
            'TH ANYWHERE CO.LTD.',                                           // Company Name
            '0105565081822',                                                 // Company VAT ID
            '143/50, Thepprasit Rd, Pattaya City, Bang Lamung District, Chon Buri 20150',
            $orderDescription,                                               // Order Description
            $saleValue,                                                      // Sale Value
            $vatValue,                                                       // VAT Value
            $crmIds,                                                          // CRM ID
            $invoiceStatus,                                                  // Invoice Status
            $dateTime,                                                       // Tr. Datetime
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
