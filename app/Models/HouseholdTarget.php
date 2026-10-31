<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reporting_period_id', 'household_id'])]
class HouseholdTarget extends Model {}
