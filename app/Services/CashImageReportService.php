<?php
namespace App\Services;

use App\Http\Resources\BookingResource;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\BookingItemGroup;
use App\Models\CashImage;
use App\Models\EntranceTicket;
use App\Models\Hotel;
use App\Models\PrivateVanTour;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CashImageReportService
{
    protected $date;
    protected $start_date;
    protected $end_date;

    public function __construct(string $date)
    {
        $this->date = $date;
        $this->start_date = Carbon::parse($date)->startOfMonth()->format('Y-m-d');
        $this->end_date = Carbon::parse($date)->endOfMonth()->format('Y-m-d');
    }

    /**
     * Get cash image data by agent for the month
     */
    public function getCashImageData($created_by = null): array
    {
        $cash_images = CashImage::query()
            ->whereHasMorph('relatable', [Booking::class], function ($q) use ($created_by) {
                $q->when($created_by, function ($query) use ($created_by) {
                    $query->whereIn('created_by', explode(',', $created_by));
                });
            })
            ->whereBetween('cash_images.created_at', [$this->start_date, $this->end_date])
            ->with(['relatable' => function ($q) {
                $q->select('id', 'created_by');
            }])
            ->select(
                'cash_images.id',
                'cash_images.relatable_id',
                'cash_images.relatable_type',
                'cash_images.amount',
                DB::raw('DATE_FORMAT(cash_images.created_at, "%Y-%m-%d") as cash_date'),
                'cash_images.created_at'
            )
            ->get();

        return $this->generateCashImageResponse($cash_images, $created_by);
    }

    /**
     * Get total cash images received by each agent for the month
     */
    public function getCashImageSummary($created_by = null): array
    {
        $cash_summary = CashImage::query()
            ->join('bookings', function ($join) {
                $join->on('cash_images.relatable_id', '=', 'bookings.id')
                     ->where('cash_images.relatable_type', '=', Booking::class);
            })
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('bookings.created_by', explode(',', $created_by));
            })
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->select(
                'bookings.created_by',
                'cash_images.currency',
                DB::raw('COUNT(cash_images.id) as total_cash_images'),
                DB::raw('SUM(cash_images.amount) as total_cash_amount'),
                DB::raw('DATE_FORMAT(cash_images.date, "%Y-%m-%d") as cash_date')
            )
            ->groupBy('bookings.created_by', 'cash_images.currency', 'cash_date')
            ->get();

        return $this->generateCashImageSummaryResponse($cash_summary, $created_by);
    }

    public function getTodayCashImageSummary($created_by = null): array
    {
        $today = Carbon::now()->format('Y-m-d');

        // Get cash images for Booking::class
        $booking_cash_summary = CashImage::query()
            ->whereDate('cash_images.date', $today)
            ->where('cash_images.relatable_type', 'App\\Models\\Booking')
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->select(
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_amount')
            )
            ->groupBy('cash_images.currency')
            ->get()
            ->pluck('total_amount', 'currency');

        // Get cash images for BookingItemGroup::class and CashBook::class
        $other_cash_summary = CashImage::query()
            ->whereDate('cash_images.date', $today)
            ->whereIn('cash_images.relatable_type', [
                'App\\Models\\BookingItemGroup',
                'App\\Models\\CashBook'
            ])
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->select(
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_amount')
            )
            ->groupBy('cash_images.currency')
            ->get()
            ->pluck('total_amount', 'currency');

        // Return structured response with default 0 values
        return [
            'booking_summary' => [
                'thb' => $booking_cash_summary['THB'] ?? 0,
                'mmk' => $booking_cash_summary['MMK'] ?? 0,
            ],
            'other_summary' => [
                'thb' => $other_cash_summary['THB'] ?? 0,
                'mmk' => $other_cash_summary['MMK'] ?? 0,
            ]
        ];
    }

    private function getDaysOfMonth(): array
    {
        $dates = Carbon::parse($this->date)->startOfMonth()
            ->daysUntil(Carbon::parse($this->date)->endOfMonth())
            ->map(fn ($date) => $date->format('Y-m-d'));

        return iterator_to_array($dates);
    }

    /**
     * Generate cash image response similar to sale response format
     */
    private function generateCashImageResponse($cash_images, $created_by = null): array
    {
        $result = [];
        $agents = Admin::query()
            ->agentAndSaleManager()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('id', explode(',', $created_by));
            })
            ->pluck('name', 'id')
            ->toArray();

        foreach ($this->getDaysOfMonth() as $date) {
            $agent_result = [];

            foreach ($agents as $agent_id => $agent_name) {
                $cash_records = $cash_images->where('cash_date', $date)
                    ->filter(function ($cash_image) use ($agent_id) {
                        return $cash_image->relatable && $cash_image->relatable->created_by == $agent_id;
                    });

                $agent_result[] = [
                    'name' => $agent_name,
                    'total_cash_images' => $cash_records->count(),
                    'total_cash_amount' => $cash_records->sum('amount')
                ];
            }

            $result[] = [
                'date' => $date,
                'agents' => $agent_result
            ];
        }

        return $result;
    }

    /**
     * Get monthly summary of cash images by agent
     * လတစ်လလုံးရဲ့ agent တစ်ယောက်ချင်းစီရဲ့ cash image စုစုပေါင်း (currency အလိုက်)
     */
    /**
     * Get monthly summary of cash images by agent
     * လတစ်လလုံးရဲ့ agent တစ်ယောက်ချင်းစီရဲ့ cash image စုစုပေါင်း (currency အလိုက်)
     */
    public function getMonthlyCashImageSummary($created_by = null): array
    {
        // Original income query (Booking class)
        $monthly_summary = CashImage::query()
            ->join('bookings', function ($join) {
                $join->on('cash_images.relatable_id', '=', 'bookings.id')
                     ->where('cash_images.relatable_type', '=', Booking::class);
            })
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('bookings.created_by', explode(',', $created_by));
            })
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->select(
                'bookings.created_by',
                'cash_images.currency',
                DB::raw('COUNT(cash_images.id) as total_cash_images'),
                DB::raw('SUM(cash_images.amount) as total_cash_amount')
            )
            ->groupBy('bookings.created_by', 'cash_images.currency')
            ->get();

        // Get expense totals for THB and MMK only
        $expense_totals = CashImage::query()
            ->where('cash_images.relatable_type', '!=', Booking::class)
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->select(
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_expense_amount'),
                DB::raw('COUNT(cash_images.id) as total_expense_images')
            )
            ->groupBy('cash_images.currency')
            ->get()
            ->keyBy('currency');

        // NEW: Get income breakdown by interact_bank
        $income_by_interact_bank = CashImage::query()
            ->join('bookings', function ($join) {
                $join->on('cash_images.relatable_id', '=', 'bookings.id')
                     ->where('cash_images.relatable_type', '=', Booking::class);
            })
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('bookings.created_by', explode(',', $created_by));
            })
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->select(
                'cash_images.interact_bank',
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_amount'),
                DB::raw('COUNT(cash_images.id) as total_count')
            )
            ->groupBy('cash_images.interact_bank', 'cash_images.currency')
            ->get();

        // NEW: Get expense breakdown by interact_bank
        $expense_by_interact_bank = CashImage::query()
            ->where('cash_images.relatable_type', '!=', Booking::class)
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->select(
                'cash_images.interact_bank',
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_amount'),
                DB::raw('COUNT(cash_images.id) as total_count')
            )
            ->groupBy('cash_images.interact_bank', 'cash_images.currency')
            ->get();

        return $this->generateMonthlySummaryResponseWithExpenseTotals(
            $monthly_summary,
            $expense_totals,
            $created_by,
            $income_by_interact_bank,
            $expense_by_interact_bank
        );
    }

    /**
     * Generate monthly summary response with expense totals and interact_bank breakdown
     */
    private function generateMonthlySummaryResponseWithExpenseTotals(
        $monthly_summary,
        $expense_totals,
        $created_by = null,
        $income_by_interact_bank = null,
        $expense_by_interact_bank = null
    ): array
    {
        $agents = Admin::query()
            ->agentAndSaleManager()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('id', explode(',', $created_by));
            })
            ->pluck('name', 'id')
            ->toArray();

        $agent_summaries = [];
        $grand_totals_by_currency = [];
        $grand_total_images = 0;

        foreach ($agents as $agent_id => $agent_name) {
            $agent_data_by_currency = $monthly_summary->where('created_by', $agent_id);

            $currencies = [];
            $total_images_for_agent = 0;

            // Group by currency for this agent
            foreach ($agent_data_by_currency as $data) {
                $currency = $data->currency ?? 'Unknown';

                if (!isset($currencies[$currency])) {
                    $currencies[$currency] = [
                        'total_cash_images' => 0,
                        'total_cash_amount' => 0
                    ];
                }

                $currencies[$currency]['total_cash_images'] += $data->total_cash_images;
                $currencies[$currency]['total_cash_amount'] += $data->total_cash_amount;
                $total_images_for_agent += $data->total_cash_images;

                // Add to grand totals
                if (!isset($grand_totals_by_currency[$currency])) {
                    $grand_totals_by_currency[$currency] = [
                        'total_cash_images' => 0,
                        'total_cash_amount' => 0
                    ];
                }
                $grand_totals_by_currency[$currency]['total_cash_images'] += $data->total_cash_images;
                $grand_totals_by_currency[$currency]['total_cash_amount'] += $data->total_cash_amount;
            }

            $grand_total_images += $total_images_for_agent;

            $agent_summaries[] = [
                'agent_id' => $agent_id,
                'name' => $agent_name,
                'total_cash_images' => $total_images_for_agent,
                'currencies' => $currencies,
                // Legacy fields for backward compatibility (will show first currency or 0)
                'total_cash_amount' => !empty($currencies) ? array_values($currencies)[0]['total_cash_amount'] : 0,
            ];
        }

        // Sort by total images descending (အများဆုံးရရှိသူကို အပေါ်မှာ ပြမယ်)
        usort($agent_summaries, function($a, $b) {
            return $b['total_cash_images'] <=> $a['total_cash_images'];
        });

        // NEW: Format income by interact_bank
        $income_interact_bank_summary = [];
        if ($income_by_interact_bank) {
            foreach ($income_by_interact_bank as $item) {
                $bank = $item->interact_bank ?? 'unknown';
                $currency = strtolower($item->currency);

                if (!isset($income_interact_bank_summary[$bank])) {
                    $income_interact_bank_summary[$bank] = [
                        'thb' => ['amount' => 0, 'count' => 0],
                        'mmk' => ['amount' => 0, 'count' => 0],
                    ];
                }

                $income_interact_bank_summary[$bank][$currency]['amount'] = $item->total_amount;
                $income_interact_bank_summary[$bank][$currency]['count'] = $item->total_count;
            }
        }

        // NEW: Format expense by interact_bank
        $expense_interact_bank_summary = [];
        if ($expense_by_interact_bank) {
            foreach ($expense_by_interact_bank as $item) {
                $bank = $item->interact_bank ?? 'unknown';
                $currency = strtolower($item->currency);

                if (!isset($expense_interact_bank_summary[$bank])) {
                    $expense_interact_bank_summary[$bank] = [
                        'thb' => ['amount' => 0, 'count' => 0],
                        'mmk' => ['amount' => 0, 'count' => 0],
                    ];
                }

                $expense_interact_bank_summary[$bank][$currency]['amount'] = $item->total_amount;
                $expense_interact_bank_summary[$bank][$currency]['count'] = $item->total_count;
            }
        }

        return [
            'month' => Carbon::parse($this->date)->format('F Y'),
            'period' => [
                'start_date' => $this->start_date,
                'end_date' => $this->end_date
            ],
            'total_agents' => count($agent_summaries),
            'grand_total_cash_images' => $grand_total_images,
            'grand_totals_by_currency' => $grand_totals_by_currency,
            'grand_total_cash_amount' => !empty($grand_totals_by_currency) ?
                array_values($grand_totals_by_currency)[0]['total_cash_amount'] : 0,
            'expense_summary' => [
                'thb' => [
                    'amount' => $expense_totals['THB']->total_expense_amount ?? 0,
                    'count' => $expense_totals['THB']->total_expense_images ?? 0,
                ],
                'mmk' => [
                    'amount' => $expense_totals['MMK']->total_expense_amount ?? 0,
                    'count' => $expense_totals['MMK']->total_expense_images ?? 0,
                ],
            ],
            // NEW: Add interact_bank breakdown for income
            'income_by_interact_bank' => $income_interact_bank_summary,
            // NEW: Add interact_bank breakdown for expense
            'expense_by_interact_bank' => $expense_interact_bank_summary,
            'agents' => $agent_summaries
        ];
    }

    /**
     * Generate monthly summary response with expense totals added
     */


    /**
     * Generate monthly summary response with currency breakdown
     */
    private function generateMonthlySummaryResponse($monthly_summary, $created_by = null): array
    {
        $agents = Admin::query()
            ->agentAndSaleManager()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('id', explode(',', $created_by));
            })
            ->pluck('name', 'id')
            ->toArray();

        $agent_summaries = [];
        $grand_totals_by_currency = [];
        $grand_total_images = 0;

        foreach ($agents as $agent_id => $agent_name) {
            $agent_data_by_currency = $monthly_summary->where('created_by', $agent_id);

            $currencies = [];
            $total_images_for_agent = 0;

            // Group by currency for this agent
            foreach ($agent_data_by_currency as $data) {
                $currency = $data->currency ?? 'Unknown';

                if (!isset($currencies[$currency])) {
                    $currencies[$currency] = [
                        'total_cash_images' => 0,
                        'total_cash_amount' => 0
                    ];
                }

                $currencies[$currency]['total_cash_images'] += $data->total_cash_images;
                $currencies[$currency]['total_cash_amount'] += $data->total_cash_amount;
                $total_images_for_agent += $data->total_cash_images;

                // Add to grand totals
                if (!isset($grand_totals_by_currency[$currency])) {
                    $grand_totals_by_currency[$currency] = [
                        'total_cash_images' => 0,
                        'total_cash_amount' => 0
                    ];
                }
                $grand_totals_by_currency[$currency]['total_cash_images'] += $data->total_cash_images;
                $grand_totals_by_currency[$currency]['total_cash_amount'] += $data->total_cash_amount;
            }

            $grand_total_images += $total_images_for_agent;

            $agent_summaries[] = [
                'agent_id' => $agent_id,
                'name' => $agent_name,
                'total_cash_images' => $total_images_for_agent,
                'currencies' => $currencies,
                // Legacy fields for backward compatibility (will show first currency or 0)
                'total_cash_amount' => !empty($currencies) ? array_values($currencies)[0]['total_cash_amount'] : 0,
            ];
        }

        // Sort by total images descending (အများဆုံးရရှိသူကို အပေါ်မှာ ပြမယ်)
        usort($agent_summaries, function($a, $b) {
            return $b['total_cash_images'] <=> $a['total_cash_images'];
        });

        return [
            'month' => Carbon::parse($this->date)->format('F Y'), // July 2025
            'period' => [
                'start_date' => $this->start_date,
                'end_date' => $this->end_date
            ],
            'total_agents' => count($agent_summaries),
            'grand_total_cash_images' => $grand_total_images,
            'grand_totals_by_currency' => $grand_totals_by_currency,
            // Legacy field for backward compatibility
            'grand_total_cash_amount' => !empty($grand_totals_by_currency) ?
                array_values($grand_totals_by_currency)[0]['total_cash_amount'] : 0,
            'agents' => $agent_summaries
        ];
    }

    /**
     * Generate cash image summary response with currency breakdown
     */
    private function generateCashImageSummaryResponse($cash_summary, $created_by = null): array
    {
        $result = [];
        $agents = Admin::query()
            ->agentAndSaleManager()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('id', explode(',', $created_by));
            })
            ->pluck('name', 'id')
            ->toArray();

        foreach ($this->getDaysOfMonth() as $date) {
            $agent_result = [];

            foreach ($agents as $agent_id => $agent_name) {
                $cash_records = $cash_summary->where('cash_date', $date)->where('created_by', $agent_id);

                $currencies = [];
                $total_images = 0;

                // Group by currency for this agent on this date
                foreach ($cash_records as $record) {
                    $currency = $record->currency ?? 'Unknown';

                    if (!isset($currencies[$currency])) {
                        $currencies[$currency] = [
                            'total_cash_images' => 0,
                            'total_cash_amount' => 0
                        ];
                    }

                    $currencies[$currency]['total_cash_images'] += $record->total_cash_images;
                    $currencies[$currency]['total_cash_amount'] += $record->total_cash_amount;
                    $total_images += $record->total_cash_images;
                }

                $agent_result[] = [
                    'name' => $agent_name,
                    'total_cash_images' => $total_images,
                    'currencies' => $currencies,
                    // Legacy field for backward compatibility
                    'total_cash_amount' => !empty($currencies) ? array_values($currencies)[0]['total_cash_amount'] : 0,
                ];
            }

            $result[] = [
                'date' => $date,
                'agents' => $agent_result
            ];
        }

        return $result;
    }
}
