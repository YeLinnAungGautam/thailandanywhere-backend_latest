<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'date',
        'income_or_expense',
        'cash_structure_id',
        'interact_bank',
        'description'
    ];

    protected $casts = [
        'date' => 'datetime', // Changed to datetime for date and time
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship with Cash Structure (Many-to-One)
    public function cashStructure()
    {
        return $this->belongsTo(CashStructure::class, 'cash_structure_id');
    }

    // Polymorphic relationship with Cash Images
    public function cashImages()
    {
        return $this->morphMany(CashImage::class, 'relatable');
    }

    public function cashBookImages()
    {
        return $this->hasMany(CashBookImage::class);
    }

    // Relationship with Chart of Accounts (Many-to-Many) - Fixed table name
    public function chartOfAccounts()
    {
        return $this->belongsToMany(
            ChartOfAccount::class,
            'cash_book_chart_of_accounts',
            'cash_book_id',
            'chart_of_account_id'
        )->withPivot(['allocated_amount', 'note'])
         ->withTimestamps();
    }

    // Generate reference number
    public static function generateReferenceNumber()
    {
        $prefix = 'Dr';
        $month = now()->format('m'); // 01-12
        $year = now()->format('y'); // 25 for 2025
        $company = 'Any'; // Default, could be configurable

        // Get the last reference number for this month/year
        $lastRecord = static::where('reference_number', 'like', "{$prefix}-{$month}/{$year}/{$company}-%")
            ->orderBy('reference_number', 'desc')
            ->first();

        $sequence = 1;
        if ($lastRecord) {
            // Extract the sequence number and increment
            $parts = explode('-', $lastRecord->reference_number);
            $sequence = (int) end($parts) + 1;
        }

        return sprintf('%s-%s/%s/%s-%03d', $prefix, $month, $year, $company, $sequence);
    }

}
