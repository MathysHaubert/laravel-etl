<?php

namespace Redesign\ETL\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationTracker extends Model
{
    public $timestamps = false;
    protected $table = 'migration_tracker';

    protected $fillable = [
        'data',
        'table',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function addData(array $row): self
    {
        $data = $this->data ?? [];
        $data[] = $row;
        $this->data = $data;

        return $this;
    }
}
