<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_name',
        'street_address',
        'representative_name',
    ];

    public function products(): HasMany {
        return $this->hasMany(Product::class);
    }

    public static function getCompanyList(): Collection {
        return static::query()->orderBy('id')->get(['id', 'company_name']);
    }

    /**
     * Add missing initial companies without changing existing company data.
     *
     * @param array<int, string> $company_names
     */
    public static function createInitialCompanies(array $company_names): void {
        DB::transaction(function () use ($company_names) {
            foreach ($company_names as $company_name) {
                static::firstOrCreate(['company_name' => $company_name]);
            }
        });
    }
}
