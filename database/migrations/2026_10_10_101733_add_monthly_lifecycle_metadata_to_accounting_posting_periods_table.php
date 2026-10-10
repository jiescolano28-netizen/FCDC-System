<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('accounting_posting_periods', 'period_month')) {
            Schema::table('accounting_posting_periods', function (Blueprint $table) {
                $table->unsignedTinyInteger('period_month')->default(1)->after('fiscal_year');
            });
        }

        Schema::table('accounting_posting_periods', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('employees');
            $table->text('close_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('employees');
            $table->text('reopen_reason')->nullable();
        });

        Schema::table('accounting_posting_periods', function (Blueprint $table) {
            $table->dropUnique(['book_key', 'fiscal_year']);
            $table->unique(
                ['book_key', 'fiscal_year', 'period_month'],
                'acct_post_periods_book_fy_month_unique'
            );
        });

        foreach (DB::table('accounting_posting_periods')->orderBy('id')->get() as $period) {
            $year = (int) $period->fiscal_year;
            $closedAt = now();
            DB::table('accounting_posting_periods')->where('id', $period->id)->update([
                'starts_on' => sprintf('%04d-01-01', $year),
                'ends_on' => sprintf('%04d-01-31', $year),
            ]);
            for ($month = 2; $month <= 12; $month++) {
                $startsOn = sprintf('%04d-%02d-01', $year, $month);
                $endsOn = CarbonImmutable::parse($startsOn)->endOfMonth()->toDateString();
                $id = DB::table('accounting_posting_periods')->insertGetId([
                    'book_key' => $period->book_key,
                    'fiscal_year' => $year,
                    'period_month' => $month,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'status' => $period->status,
                    'created_at' => $closedAt,
                    'updated_at' => $closedAt,
                ]);
                DB::table('accounting_journals')->where('posting_period_id', $period->id)
                    ->whereMonth('accounting_date', $month)->whereYear('accounting_date', $year)
                    ->update(['posting_period_id' => $id]);
                DB::table('cash_disbursements')->where('posting_period_id', $period->id)
                    ->whereMonth('payment_date', $month)->whereYear('payment_date', $year)
                    ->update(['posting_period_id' => $id]);
            }
            DB::table('accounting_journals')->where('posting_period_id', $period->id)
                ->whereYear('accounting_date', '!=', $year)->update(['posting_period_id' => $period->id]);
            DB::table('cash_disbursements')->where('posting_period_id', $period->id)
                ->whereYear('payment_date', '!=', $year)->update(['posting_period_id' => $period->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('accounting_posting_periods')->where('period_month', '!=', 1)->get() as $period) {
            $january = DB::table('accounting_posting_periods')
                ->where('book_key', $period->book_key)->where('fiscal_year', $period->fiscal_year)
                ->where('period_month', 1)->first();
            if ($january) {
                DB::table('accounting_journals')->where('posting_period_id', $period->id)
                    ->update(['posting_period_id' => $january->id]);
                DB::table('cash_disbursements')->where('posting_period_id', $period->id)
                    ->update(['posting_period_id' => $january->id]);
            }
            DB::table('accounting_posting_periods')->where('id', $period->id)->delete();
        }
        foreach (DB::table('accounting_posting_periods')->where('period_month', 1)->get() as $period) {
            DB::table('accounting_posting_periods')->where('id', $period->id)
                ->update(['ends_on' => sprintf('%04d-12-31', $period->fiscal_year)]);
        }
        Schema::table('accounting_posting_periods', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['reopened_by']);
            $table->dropUnique(['book_key', 'fiscal_year', 'period_month']);
            $table->unique(['book_key', 'fiscal_year']);
            $table->dropColumn([
                'period_month', 'closed_at', 'closed_by', 'close_reason',
                'reopened_at', 'reopened_by', 'reopen_reason',
            ]);
        });
    }
};
