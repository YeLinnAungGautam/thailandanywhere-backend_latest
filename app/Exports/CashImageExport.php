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

class CashImageExport implements
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
        $result = $this->cashImageService->getAllSummaryForExport($request);

        // Check for error response or empty data
        if ($result['status'] !== 1 || empty($result['result']['data'])) {
            return collect([]);
        }

        return collect($result['result']['data']);
    }

    /**
     * Define the headings for the CSV
     */
    public function headings(): array
    {
        return [
            'Date',
            'Crm Number',
            'Invoice Number',
            'Customer Name',
            'Taxpayer Identification Number',
            'Establishment',
            'Total Value',
            'Amount VAT',
            'Hotel',
            'Restaurant',
            'Ticket',
            'Hotel Amount',
            'Ticket Amount',
            'Profit Share',
            'Cash Amount',
            'Interact Bank',
            'Deposit Count',
            'DateTime',
        ];
    }

    /**
     * Map each row of data
     */
    public function map($cashImage): array
    {
        $customInvoiceNumber = $this->generateCustomInvoiceNumber($cashImage['cash_image_date'] ?? '');
        // Safely access array elements with null coalescing
        return [
            $this->formatDateForExport($cashImage['cash_image_date'] ?? ''),
            $cashImage['crm_id'] ?? '',
            $customInvoiceNumber ?? '',
            $cashImage['customer_name'] ?? '',
            $cashImage['taxpayer_id'] ?? '000000000000', // Make this configurable or from data
            $cashImage['establishment'] ?? '00000', // Make this configurable or from data
            $this->formatCurrency($cashImage['total_sales'] ?? 0, $cashImage['currency'] ?? ''),
            $this->formatAmount($this->calculateVat($cashImage['commission'])),
            $this->hasHotelService($cashImage) ? '✓' : '',
            '', // Restaurant
            $this->hasTicketService($cashImage) ? '✓' : '',
            $this->formatAmount($cashImage['hotel_service_total'] ?? 0),
            $this->formatAmount($cashImage['ticket_service_total'] ?? 0),
            $this->formatAmount($cashImage['commission'] ?? 0),
            $this->formatCurrency($cashImage['cash_amount'] ?? 0, $cashImage['currency'] ?? ''),
            $this->formatBankName($cashImage['bank'] ?? ''),
            $cashImage['deposit'] ?? '',
            $this->formatTimeForExport($cashImage['cash_image_date'] ?? ''),
        ];
    }

    private function generateCustomInvoiceNumber($dateString)
    {
        $month = '07'; // Default month

        if ($dateString) {
            try {
                $date = new \DateTime($dateString);
                $month = $date->format('m'); // Get month as 01-12
            } catch (\Exception $e) {
                $month = '07'; // Fallback to 07 if date parsing fails
            }
        }

        // Format: INV + month + 5-digit sequential number
        $invoiceNumber = 'INV' . $month . str_pad($this->invoiceCounter, 5, '0', STR_PAD_LEFT);

        // Increment counter for next invoice
        $this->invoiceCounter++;

        return $invoiceNumber;
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
            'A1:P1' => [
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

    private function formatTimeForExport($timeString)
    {
        if (!$timeString) return '';

        try {
            $time = new \DateTime($timeString);
            return $time->format('d M y H:i');
        } catch (\Exception $e) {
            return $timeString;
        }
    }

    private function calculateVat($commission)
    {
        $vatAmount = $commission - ($commission / 1.07); // 7% VAT on commission
        return $vatAmount;
    }

    /**
     * Format currency amount with currency code
     */
    private function formatCurrency($amount, $currency)
    {
        if (!$amount || $amount == 0) return '';
        return number_format($amount, 2) . ' ' . strtoupper($currency);
    }

    /**
     * Format numeric amount
     */
    private function formatAmount($amount)
    {
        if (!$amount || $amount == 0) return '';
        return number_format($amount, 2);
    }

    /**
     * Check if item has hotel service
     */
    private function hasHotelService($item)
    {
        return !empty($item['hotel_service_total']) && $item['hotel_service_total'] > 0;
    }

    /**
     * Check if item has ticket service
     */
    private function hasTicketService($item)
    {
        return !empty($item['ticket_service_total']) && $item['ticket_service_total'] > 0;
    }

    /**
     * Format bank name for display
     */
    private function formatBankName($bankName)
    {
        if (!$bankName) return '';

        $formatted = str_replace('_', ' ', $bankName);
        return ucwords($formatted);
    }
}
