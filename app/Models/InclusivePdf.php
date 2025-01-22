<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InclusivePdf extends Model
{
    use HasFactory;

    protected $fillable = ['inclusive_id', 'pdf_path'];
}
