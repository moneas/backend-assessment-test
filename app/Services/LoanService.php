<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\User;
use App\Models\ScheduledRepayment;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        DB::beginTransaction();
        try {
            $loan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            $this->createScheduledRepayments($loan);

            DB::commit();
            return $loan;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        DB::beginTransaction();
        try {
            $scheduledRepayment = $loan->scheduledRepayments()
                ->where('status', ScheduledRepayment::STATUS_DUE)
                ->orderBy('due_date')
                ->firstOrFail();

            $receivedAmount = min($amount, $scheduledRepayment->outstanding_amount);
            
            // Update scheduled repayment
            if ($receivedAmount >= $scheduledRepayment->outstanding_amount) {
                // Repay fully
                $scheduledRepayment->update([
                    'status' => ScheduledRepayment::STATUS_REPAID,
                    'outstanding_amount' => 0,
                ]);
            } else {
                // Repay partially
                $scheduledRepayment->update([
                    'outstanding_amount' => $scheduledRepayment->outstanding_amount - $receivedAmount,
                ]);
            }

            // Create received repayment record
            $received = ReceivedRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $receivedAmount,
                'currency_code' => $currencyCode,
                'received_at' => $receivedAt,
            ]);

            // Update loan's outstanding_amount after repayment
            $loan->refresh(); // Refresh loan instance to get the latest data from database
            $loan->update([
                'outstanding_amount' => $loan->scheduledRepayments()->where('status', ScheduledRepayment::STATUS_DUE)->sum('outstanding_amount'),
            ]);

            DB::commit();
            return $received;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Create Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @return void
     */
    protected function createScheduledRepayments(Loan $loan): void
    {
        $scheduled = [];
        $terms = $loan->terms;
        $totalAmount = $loan->amount;
        $amountPerTerm = floor($loan->amount / $terms); // Use floor to round down

        $dueDate = Carbon::parse($loan->processed_at);

        for ($i = 0; $i < $terms; $i++) {
            $dueDate = $dueDate->addMonth();

            // For the last term, use the remaining amount
            if ($i === $terms - 1) {
                $amountPerTerm = $totalAmount; // Use the remaining amount
            }

            $scheduled[] = [
                'loan_id' => $loan->id,
                'amount' => $amountPerTerm,
                'outstanding_amount' => $amountPerTerm,
                'currency_code' => $loan->currency_code,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
            ];

            $totalAmount -= $amountPerTerm;
        }

        ScheduledRepayment::insert($scheduled);
    }
}
