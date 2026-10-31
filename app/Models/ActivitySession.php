<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'scheduled_on', 'location', 'created_by'])]
class ActivitySession extends Model {}
