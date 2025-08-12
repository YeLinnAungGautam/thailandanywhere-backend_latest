<?php

namespace App\Exports;

use App\Services\PrintPDFService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;

class CashParchaseTaxExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStrictNullComparison,
    ShouldAutoSize,
    WithStyles
{
    protected $searchParams;
    protected $printPDFService;
    protected $invoiceCounter = 1;

    public function __construct(array $searchParams = [])
    {
        $this->searchParams = $searchParams;
        $this->printPDFService = app(PrintPDFService::class);
    }

    /**
     * Get the data collection for export
     */
    public function collection()
    {
        // Convert array to Request object for the service
        $request = new Request($this->searchParams);

        // Get tax receipt data from the service
        $result = $this->printPDFService->getExportCSVData($request);

        // Check if result is empty
        if ($result->isEmpty()) {
            return collect([]);
        }

        // Convert resource collection to array for easier handling
        return collect($result->toArray($request)); // Pass $request instead of request()
    }

    /**
     * Define the headings for the CSV - Tax Receipt specific headers
     */
    public function headings(): array
    {
        return [
            'Tax Credit ID',
            'Tax Credit Date',
            'Supplier Name',
            'Supplier VAT ID',
            'Supplier Address',
            'Our Company Name',
            'Our Company VAT ID',
            'Our Company Address',
            'Order Description',
            'Total Sale Amount',
            'Total VAT Amount',
            'List of CRM IDs',
            'List of Transaction Dates'
        ];
    }

    /**
     * Map each row of data - Tax Receipt specific mapping
     */
    public function map($taxReceipt): array
    {
        // Handle both array and object formats
        $data = is_array($taxReceipt) ? $taxReceipt : $taxReceipt->toArray();

        // Get supplier information from product
        $supplierName = $this->getNestedValue($data, 'product.vat_name') ?: 'N/A';

        $supplierVatId = $this->getNestedValue($data, 'product.vat_id') ?: 'N/A';

        $supplierAddress = $this->getNestedValue($data, 'product.vat_address') ?: 'N/A';

        // Get order description based on product type
        $orderDescription = $this->getNestedValue($data, 'product_type'); // Fixed: removed extra $this->

        // Get CRM IDs and transaction dates
        $getCrmIds = $this->getCrmIds($data);
        $listOfTransactionDates = $this->getListOfTransactionDates($data);

        return [
            $data['tax_credit_id'] ?? '',                                               // Tax Credit ID
            $this->formatDateForExport($this->getNestedValue($data, 'service_end_date')), // Tax Credit Date
            $supplierName,                                                              // Supplier Name
            $supplierVatId,                                                             // Supplier VAT ID
            $supplierAddress,                                                           // Supplier Address
            'TH ANYWHERE CO.LTD.',                                                   // Our Company Name
            '0105565081822',                                                               // Our Company VAT ID
            '143/50, Thepprasit Rd, Pattaya City,
            Bang Lamung District, Chon Buri 20150',                                                   // Our Company Address
            $orderDescription,                                                          // Order Description
            $this->formatAmount($this->getNestedValue($data, 'total_after_tax')),     // Total Sale Amount
            $this->formatAmount($this->getNestedValue($data, 'total_tax_withold')),   // Total VAT Amount
            $getCrmIds,                                                                // List of CRM IDs
            $listOfTransactionDates,                                                   // List of Transaction Dates (was missing!)
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
            'A1:M1' => [ // Changed from O1 to M1 (13 columns instead of 15)
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE2EFDA']
                ]
            ]
        ];
    }

    /**
     * Get nested array value safely
     */
    private function getNestedValue($array, $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get list of CRM IDs
     */
    private function getCrmIds($data)
    {
        foreach ($data['groups'] as $bookingItemGroup) {
            $crmIds[] = $bookingItemGroup['crm_id'];
        }
        return implode(', ', $crmIds);
    }

    /**
     * Get list of transaction dates
     */
    private function getListOfTransactionDates($data)
    {
        $transactionDates = [];

        // Check if all_transactions exist and is array
        if (!isset($data['all_transactions']) || !is_array($data['all_transactions'])) {
            return '';
        }

        foreach ($data['all_transactions'] as $transaction) {
            $date = null;

            if(isset($transaction)) {
                $date = $this->formatDateTimeForExport($transaction);
            }

            $transactionDates[] = $date;
        }

        return implode(', ', $transactionDates);
    }

    /**
     * Format date for export
     */
    private function formatDateForExport($dateString)
    {
        if (!$dateString) return '';

        try {
            $date = new \DateTime($dateString);
            return $date->format('d M Y');
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    private function formatDateTimeForExport($dateString)
    {
        if (!$dateString) return '';

        try {
            $date = new \DateTime($dateString);
            return $date->format('d M Y H:i:s');
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    /**
     * Format amount for export
     */
    private function formatAmount($amount)
    {
        if (!$amount || $amount == 0) return '0.00';
        return number_format($amount, 2);
    }
}
