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
     * FIXED: Now uses 'date' field and handles both polymorphic and many-to-many relationships
     */
    public function getCashImageData($created_by = null): array
    {
        // Get cash images with Booking relationship (both polymorphic and many-to-many)
        $cash_images = CashImage::query()
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date]) // FIXED: Use 'date' not 'created_at'
            ->where(function($query) use ($created_by) {
                // Polymorphic relationship (relatable_id > 0)
                $query->whereHasMorph('relatable', [Booking::class], function ($q) use ($created_by) {
                    $q->when($created_by, function ($query) use ($created_by) {
                        $query->whereIn('created_by', explode(',', $created_by));
                    });
                })
                // Many-to-many relationship (relatable_id = 0)
                ->orWhere(function($q) use ($created_by) {
                    $q->where('relatable_id', 0)
                      ->where('relatable_type', Booking::class)
                      ->whereHas('cashBookings', function($subQ) use ($created_by) {
                          $subQ->when($created_by, function ($query) use ($created_by) {
                              $query->whereIn('created_by', explode(',', $created_by));
                          });
                      });
                });
            })
            ->with([
                'relatable' => function ($q) {
                    $q->select('id', 'created_by');
                },
                'cashBookings' => function ($q) {
                    $q->select('bookings.id', 'bookings.created_by');
                }
            ])
            ->select(
                'cash_images.id',
                'cash_images.relatable_id',
                'cash_images.relatable_type',
                'cash_images.amount',
                'cash_images.currency',
                DB::raw('DATE_FORMAT(cash_images.date, "%Y-%m-%d") as cash_date'),
                'cash_images.date'
            )
            ->get();

        return $this->generateCashImageResponse($cash_images, $created_by);
    }

    /**
     * Get total cash images received by each agent for the month
     * FIXED: Now properly handles both relationship types and uses 'date' field
     */
    public function getCashImageSummary($created_by = null): array
    {
        // Polymorphic relationships (relatable_id > 0)
        $polymorphic_summary = CashImage::query()
            ->join('bookings', function ($join) {
                $join->on('cash_images.relatable_id', '=', 'bookings.id')
                     ->where('cash_images.relatable_type', '=', Booking::class)
                     ->where('cash_images.relatable_id', '>', 0); // FIXED: Only polymorphic
            })
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('bookings.created_by', explode(',', $created_by));
            })
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date]) // FIXED: Use 'date'
            ->select(
                'bookings.created_by',
                'cash_images.currency',
                DB::raw('COUNT(cash_images.id) as total_cash_images'),
                DB::raw('SUM(cash_images.amount) as total_cash_amount'),
                DB::raw('DATE_FORMAT(cash_images.date, "%Y-%m-%d") as cash_date')
            )
            ->groupBy('bookings.created_by', 'cash_images.currency', 'cash_date')
            ->get();

        // Many-to-many relationships (relatable_id = 0)
        $many_to_many_summary = CashImage::query()
            ->where('cash_images.relatable_type', Booking::class)
            ->where('cash_images.relatable_id', 0) // FIXED: Only many-to-many
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->whereHas('cashBookings', function($q) use ($created_by) {
                $q->when($created_by, function ($query) use ($created_by) {
                    $query->whereIn('created_by', explode(',', $created_by));
                });
            })
            ->with(['cashBookings' => function($q) {
                $q->select('bookings.id', 'bookings.created_by');
            }])
            ->select(
                'cash_images.id',
                'cash_images.currency',
                'cash_images.amount',
                DB::raw('DATE_FORMAT(cash_images.date, "%Y-%m-%d") as cash_date')
            )
            ->get();

        // Transform many-to-many results to match polymorphic format
        $many_to_many_transformed = $many_to_many_summary->groupBy(function($item) {
            $booking = $item->cashBookings->first();
            return $booking ? $booking->created_by . '_' . $item->currency . '_' . $item->cash_date : null;
        })->map(function($group) {
            $firstItem = $group->first();
            $booking = $firstItem->cashBookings->first();

            return (object)[
                'created_by' => $booking ? $booking->created_by : null,
                'currency' => $firstItem->currency,
                'total_cash_images' => $group->count(),
                'total_cash_amount' => $group->sum('amount'),
                'cash_date' => $firstItem->cash_date,
            ];
        })->filter(function($item) {
            return $item->created_by !== null;
        })->values();

        // Merge both results
        $cash_summary = $polymorphic_summary->concat($many_to_many_transformed);

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
     * FIXED: Now properly gets created_by from both relationship types
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
                        // Check polymorphic relationship
                        if ($cash_image->relatable && $cash_image->relatable->created_by == $agent_id) {
                            return true;
                        }

                        // Check many-to-many relationship
                        if ($cash_image->relatable_id == 0 && $cash_image->cashBookings) {
                            return $cash_image->cashBookings->contains('created_by', $agent_id);
                        }

                        return false;
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
     * FIXED: Now uses 'date' field and handles both relationship types
     */
    public function getMonthlyCashImageSummary($created_by = null): array
    {
        // ============================================
        // 1. INCOME TOTALS (from Bookings) - Polymorphic
        // ============================================
        $polymorphic_summary = CashImage::query()
            ->join('bookings', function ($join) {
                $join->on('cash_images.relatable_id', '=', 'bookings.id')
                     ->where('cash_images.relatable_type', '=', Booking::class)
                     ->where('cash_images.relatable_id', '>', 0);
            })
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('bookings.created_by', explode(',', $created_by));
            })
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date]) // FIXED
            ->select(
                'bookings.created_by',
                'cash_images.currency',
                DB::raw('COUNT(cash_images.id) as total_cash_images'),
                DB::raw('SUM(cash_images.amount) as total_cash_amount')
            )
            ->groupBy('bookings.created_by', 'cash_images.currency')
            ->get();

        // Many-to-many income
        $many_to_many_income = CashImage::query()
            ->where('cash_images.relatable_type', Booking::class)
            ->where('cash_images.relatable_id', 0)
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->whereHas('cashBookings', function($q) use ($created_by) {
                $q->when($created_by, function ($query) use ($created_by) {
                    $query->whereIn('created_by', explode(',', $created_by));
                });
            })
            ->with(['cashBookings' => function($q) {
                $q->select('bookings.id', 'bookings.created_by');
            }])
            ->select('cash_images.id', 'cash_images.currency', 'cash_images.amount')
            ->get()
            ->groupBy(function($item) {
                $booking = $item->cashBookings->first();
                return $booking ? $booking->created_by . '_' . $item->currency : null;
            })
            ->map(function($group) {
                $firstItem = $group->first();
                $booking = $firstItem->cashBookings->first();

                return (object)[
                    'created_by' => $booking ? $booking->created_by : null,
                    'currency' => $firstItem->currency,
                    'total_cash_images' => $group->count(),
                    'total_cash_amount' => $group->sum('amount'),
                ];
            })
            ->filter(function($item) {
                return $item->created_by !== null;
            })
            ->values();

        $monthly_summary = $polymorphic_summary->concat($many_to_many_income);

        // ============================================
        // 2. EXPENSE TOTALS (non-Booking)
        // ============================================
        $expense_totals = CashImage::query()
            ->where('cash_images.relatable_type', '!=', Booking::class)
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date]) // FIXED
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->select(
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_expense_amount'),
                DB::raw('COUNT(cash_images.id) as total_expense_images')
            )
            ->groupBy('cash_images.currency')
            ->get()
            ->keyBy('currency');

        // ============================================
        // 3. INCOME BY BANK (must match total income)
        // ============================================
        $income_by_interact_bank = CashImage::query()
            ->where('cash_images.relatable_type', Booking::class)
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->where(function($query) use ($created_by) {
                // Polymorphic
                $query->where(function($q) use ($created_by) {
                    $q->where('relatable_id', '>', 0)
                      ->whereHas('relatable', function($subQ) use ($created_by) {
                          $subQ->when($created_by, function ($query) use ($created_by) {
                              $query->whereIn('created_by', explode(',', $created_by));
                          });
                      });
                })
                // Many-to-many
                ->orWhere(function($q) use ($created_by) {
                    $q->where('relatable_id', 0)
                      ->whereHas('cashBookings', function($subQ) use ($created_by) {
                          $subQ->when($created_by, function ($query) use ($created_by) {
                              $query->whereIn('created_by', explode(',', $created_by));
                          });
                      });
                });
            })
            ->select(
                DB::raw('COALESCE(cash_images.interact_bank, "Unknown") as interact_bank'),
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_amount'),
                DB::raw('COUNT(cash_images.id) as total_count')
            )
            ->groupBy('interact_bank', 'cash_images.currency')
            ->get();

        // ============================================
        // 4. EXPENSE BY BANK (must match total expense)
        // ============================================
        $expense_by_interact_bank = CashImage::query()
            ->where('cash_images.relatable_type', '!=', Booking::class)
            ->whereBetween('cash_images.date', [$this->start_date, $this->end_date])
            ->whereIn('cash_images.currency', ['THB', 'MMK'])
            ->select(
                DB::raw('COALESCE(cash_images.interact_bank, "Unknown") as interact_bank'),
                'cash_images.currency',
                DB::raw('SUM(cash_images.amount) as total_amount'),
                DB::raw('COUNT(cash_images.id) as total_count')
            )
            ->groupBy('interact_bank', 'cash_images.currency')
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
                'total_cash_amount' => !empty($currencies) ? array_values($currencies)[0]['total_cash_amount'] : 0,
            ];
        }

        // Sort by total images descending
        usort($agent_summaries, function($a, $b) {
            return $b['total_cash_images'] <=> $a['total_cash_images'];
        });

        // ============================================
        // FORMAT: Income by interact_bank
        // ============================================
        $income_interact_bank_summary = [];
        if ($income_by_interact_bank) {
            foreach ($income_by_interact_bank as $item) {
                $bank = $item->interact_bank ?? 'Unknown';
                $currency = strtolower($item->currency);

                if (!isset($income_interact_bank_summary[$bank])) {
                    $income_interact_bank_summary[$bank] = [
                        'thb' => ['amount' => 0, 'count' => 0],
                        'mmk' => ['amount' => 0, 'count' => 0],
                    ];
                }

                $income_interact_bank_summary[$bank][$currency]['amount'] += $item->total_amount;
                $income_interact_bank_summary[$bank][$currency]['count'] += $item->total_count;
            }
        }

        // ============================================
        // FORMAT: Expense by interact_bank
        // ============================================
        $expense_interact_bank_summary = [];
        if ($expense_by_interact_bank) {
            foreach ($expense_by_interact_bank as $item) {
                $bank = $item->interact_bank ?? 'Unknown';
                $currency = strtolower($item->currency);

                if (!isset($expense_interact_bank_summary[$bank])) {
                    $expense_interact_bank_summary[$bank] = [
                        'thb' => ['amount' => 0, 'count' => 0],
                        'mmk' => ['amount' => 0, 'count' => 0],
                    ];
                }

                $expense_interact_bank_summary[$bank][$currency]['amount'] += $item->total_amount;
                $expense_interact_bank_summary[$bank][$currency]['count'] += $item->total_count;
            }
        }

        // ============================================
        // VERIFICATION: Check if bank totals match grand totals
        // ============================================
        $income_bank_totals_thb = 0;
        $income_bank_totals_mmk = 0;
        $income_bank_count_thb = 0;
        $income_bank_count_mmk = 0;

        foreach ($income_interact_bank_summary as $bank => $data) {
            $income_bank_totals_thb += $data['thb']['amount'];
            $income_bank_totals_mmk += $data['mmk']['amount'];
            $income_bank_count_thb += $data['thb']['count'];
            $income_bank_count_mmk += $data['mmk']['count'];
        }

        $expense_bank_totals_thb = 0;
        $expense_bank_totals_mmk = 0;
        $expense_bank_count_thb = 0;
        $expense_bank_count_mmk = 0;

        foreach ($expense_interact_bank_summary as $bank => $data) {
            $expense_bank_totals_thb += $data['thb']['amount'];
            $expense_bank_totals_mmk += $data['mmk']['amount'];
            $expense_bank_count_thb += $data['thb']['count'];
            $expense_bank_count_mmk += $data['mmk']['count'];
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

            // Main expense summary
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

            // Bank breakdowns
            'income_by_interact_bank' => $income_interact_bank_summary,
            'expense_by_interact_bank' => $expense_interact_bank_summary,

            // VERIFICATION DATA (for debugging)
            'verification' => [
                'income' => [
                    'thb' => [
                        'grand_total' => $grand_totals_by_currency['THB']['total_cash_amount'] ?? 0,
                        'bank_total' => $income_bank_totals_thb,
                        'matches' => abs(($grand_totals_by_currency['THB']['total_cash_amount'] ?? 0) - $income_bank_totals_thb) < 0.01,
                        'grand_count' => $grand_totals_by_currency['THB']['total_cash_images'] ?? 0,
                        'bank_count' => $income_bank_count_thb,
                    ],
                    'mmk' => [
                        'grand_total' => $grand_totals_by_currency['MMK']['total_cash_amount'] ?? 0,
                        'bank_total' => $income_bank_totals_mmk,
                        'matches' => abs(($grand_totals_by_currency['MMK']['total_cash_amount'] ?? 0) - $income_bank_totals_mmk) < 0.01,
                        'grand_count' => $grand_totals_by_currency['MMK']['total_cash_images'] ?? 0,
                        'bank_count' => $income_bank_count_mmk,
                    ],
                ],
                'expense' => [
                    'thb' => [
                        'grand_total' => $expense_totals['THB']->total_expense_amount ?? 0,
                        'bank_total' => $expense_bank_totals_thb,
                        'matches' => abs(($expense_totals['THB']->total_expense_amount ?? 0) - $expense_bank_totals_thb) < 0.01,
                        'grand_count' => $expense_totals['THB']->total_expense_images ?? 0,
                        'bank_count' => $expense_bank_count_thb,
                    ],
                    'mmk' => [
                        'grand_total' => $expense_totals['MMK']->total_expense_amount ?? 0,
                        'bank_total' => $expense_bank_totals_mmk,
                        'matches' => abs(($expense_totals['MMK']->total_expense_amount ?? 0) - $expense_bank_totals_mmk) < 0.01,
                        'grand_count' => $expense_totals['MMK']->total_expense_images ?? 0,
                        'bank_count' => $expense_bank_count_mmk,
                    ],
                ],
            ],

            'agents' => $agent_summaries
        ];
    }

    /**
     * Generate cash image summary response with currency breakdown
     * FIXED: Now properly checks both relationship types
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

    // Keep the unused method for backward compatibility
    private function generateMonthlySummaryResponse($monthly_summary, $created_by = null): array
    {
        // This method is no longer used but kept for backward compatibility
        return [];
    }
}
