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

class CashInvoiceExport implements
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

        // Get cash image data from the service
        $result = $this->cashImageService->getAllParchaseLimitForExport($request);

        // Handle different response formats
        if (is_array($result)) {
            // If it's an array response like your JSON structure
            if (isset($result['status']) && $result['status'] == 1 &&
                isset($result['result']['data']['data']) &&
                is_array($result['result']['data']['data'])) {
                return collect($result['result']['data']['data']);
            }
        }

        // If it's a collection or other format
        if (method_exists($result, 'isEmpty') && $result->isEmpty()) {
            return collect([]);
        }

        // If result is a collection, convert to array if needed
        if (method_exists($result, 'toArray')) {
            return collect($result->toArray($request));
        }

        return collect($result);
    }

    /**
     * Define the headings for the CSV
     */
    public function headings(): array
    {
        return [
            'TrDate',
            'TrTime',
            'Cash Amount',
            'Transferred From',
            'CrmId',
            '',
            'Supplier Name',
            'Supplier Company ID',
            'Supplier Address',
            'Company Name',
            'Company ID',
            'Company Address',
            'Order Description',
            'Expense Value',
            'VAT Value (Estimate)',
            'Invoice Have?',
            'Tax Receipt Have?',
            'Tax Receipt Number'
        ];
    }

    /**
     * Map each row of data based on actual data structure
     */
    public function map($cashImage): array
    {
        // Handle both array and object formats
        $data = is_array($cashImage) ? $cashImage : $cashImage->toArray();

        // Extract transaction date and time
        $transactionDate = $this->formatDateForExport($data['date'] ?? '');
        $transactionTime = $this->formatTimeForExport($data['date'] ?? '');

        // Get supplier information from relatable (BookingItemGroup)
        $supplierName = $data['receiver'] ?? 'N/A'; // Fallback to receiver
        $supplierVatId = 'N/A';
        $supplierAddress = 'N/A';



        // Try to get supplier info from relatable if it exists
        if (isset($data['relatable']) && is_array($data['relatable'])) {
            // Check if items exist and is array
            if (isset($data['relatable']['items']) && is_array($data['relatable']['items']) && count($data['relatable']['items']) > 0) {
                $firstItem = $data['relatable']['items'][0];
                if (isset($firstItem['product']) && is_array($firstItem['product'])) {
                    $supplierName = $firstItem['product']['vat_name'] ?? $supplierName;
                    $supplierVatId = $firstItem['product']['vat_id'] ?? $supplierVatId;
                    $supplierAddress = $firstItem['product']['vat_address'] ?? $supplierAddress;
                }
            }
        }

        // Get order description from product_type
        $orderDescription = $this->formatProductType($data['product_type'] ?? '');

        // Get tax receipt information
        $taxReceiptHave = 'No';
        $taxReceiptNumber = '';
        $taxCrmIds = '';

        // Check tax receipts from the cash image data
        if (isset($data['relatable']) && is_array($data['relatable']['tax_credit']) && count($data['relatable']['tax_credit']) > 0) {
            $taxReceiptHave = 'Yes';
            $taxReceiptNumbers = [];
            foreach ($data['relatable']['tax_credit'] as $receipt) {
                if (isset($receipt['invoice_number'])) {
                    $taxReceiptNumbers[] = $receipt['invoice_number'];
                } elseif (isset($receipt['invoice_number'])) {
                    $taxReceiptNumbers[] = '';
                }
            }
            $taxReceiptNumber = implode(', ', $taxReceiptNumbers);
        }

        // Also check tax_credit in relatable if tax_receipts is empty
        if ($taxReceiptHave == 'No' && isset($data['relatable']['tax_credit']) && is_array($data['relatable']['tax_credit']) && count($data['relatable']['tax_credit']) > 0) {
            $taxReceiptHave = 'Yes';
            $taxReceiptNumbers = [];
            foreach ($data['relatable']['tax_credit'] as $receipt) {
                if (isset($receipt['invoice_number'])) {
                    $taxReceiptNumbers[] = $receipt['invoice_number'];
                }
            }
            $taxReceiptNumber = implode(', ', $taxReceiptNumbers);
        }

        if (isset($data['relatable']) && is_array($data['relatable']['items']) && count($data['relatable']['items']) > 0) {
            $crmIDList = [];
            foreach ($data['relatable']['items'] as $receipt) {
                if (isset($receipt['crm_id'])) {
                    $crmIDList[] = $receipt['crm_id'];
                } elseif (isset($receipt['invoice_number'])) {
                    $crmIDList[] = '';
                }
            }
            $taxCrmIds = implode(', ', $crmIDList);
        }

        // Calculate expense value and VAT
        $amount = floatval($data['amount'] ?? 0);
        $expenseValue = $this->calculateExpense($data);
        $vatEstimate = $this->calculateExpense($data) * 0.07; // 7% VAT

        return [
            $transactionDate,                                    // TrDate
            $transactionTime,                                    // TrTime
            $this->formatAmount($amount),                        // Cash Amount
            $data['sender'] ?? '',                               // Transferred From
            $taxCrmIds ?? '',                              // CrmId
            '',                                                  // Empty column
            $supplierName,                                       // Supplier Name
            $supplierVatId,                                     // Supplier Company ID
            $supplierAddress,                                   // Supplier Address
            'TH ANYWHERE CO.LTD.',                              // Company Name
            '0105565081822',                                    // Company ID
            '143/50, Thepprasit Rd, Pattaya City, Bang Lamung District, Chon Buri 20150', // Company Address
            $orderDescription,                                  // Order Description
            $this->formatAmount($expenseValue),                 // Expense Value
            $this->formatAmount($vatEstimate),                  // VAT Value (Estimate)
            isset($data['has_invoice']) && $data['has_invoice'] ? 'Yes' : 'No', // Invoice Have?
            $taxReceiptHave,                                    // Tax Receipt Have?
            $taxReceiptNumber,                                  // Tax Receipt Number
        ];
    }

    /**
     * Calculate expense value from relatable items
     */
    private function calculateExpense($data)
    {
        $cost = 0;

        // Check if relatable and items exist
        if (isset($data['relatable']) && is_array($data['relatable']) &&
            isset($data['relatable']['items']) && is_array($data['relatable']['items'])) {

            foreach ($data['relatable']['items'] as $item) {
                if (isset($item['total_cost_price'])) {
                    $cost += floatval($item['total_cost_price']);
                }
            }
        }

        // If no relatable items, use the cash amount as expense
        if ($cost == 0) {
            $cost = floatval($data['amount'] ?? 0);
        }

        return $cost;
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
            'A1:R1' => [ // R1 for 18 columns
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE2EFDA']
                ]
            ]
        ];
    }

    /**
     * Format date for export (extract date part)
     */
    private function formatDateForExport($dateString)
    {
        if (!$dateString) return '';

        try {
            $date = new \DateTime($dateString);
            return $date->format('d-m-Y');
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    /**
     * Format time for export (extract time part)
     */
    private function formatTimeForExport($dateString)
    {
        if (!$dateString) return '';

        try {
            $date = new \DateTime($dateString);
            return $date->format('H:i:s');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Format product type for better readability
     */
    private function formatProductType($productType)
    {
        if (!$productType) return 'N/A';

        // Convert model class names to readable format
        $typeMap = [
            'App\\Models\\EntranceTicket' => 'Entrance Ticket Service',
            'App\\Models\\Hotel' => 'Hotel Service',
            'App\\Models\\PrivateVanTour' => 'Private Van Tour',
            'App\\Models\\GroupTour' => 'Group Tour',
            'App\\Models\\Airline' => 'Airline',
        ];

        return $typeMap[$productType] ?? $productType;
    }

    /**
     * Format amount for export
     */
    private function formatAmount($amount)
    {
        if (!$amount || $amount == 0) return '0.00';
        return number_format(floatval($amount), 2);
    }
}
